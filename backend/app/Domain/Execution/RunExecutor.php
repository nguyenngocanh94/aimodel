<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Providers\ProviderRouter;
use App\Domain\RunTrigger;
use App\Events\NodeStatusChanged;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Services\ArtifactStoreContract;

final class RunExecutor
{
    public function __construct(
        private ExecutionPlanner $planner,
        private InputResolver $inputResolver,
        private NodeTemplateRegistry $registry,
        private RunCache $cache,
        private ProviderRouter $providerRouter,
        private ArtifactStoreContract $artifactStore,
    ) {}

    public function execute(ExecutionRun $run): void
    {
        $document = $run->document_snapshot ?? $run->workflow->document;

        // 1. Plan
        $plan = $this->planner->plan(
            $document,
            RunTrigger::from($run->trigger),
            $run->target_node_id,
        );

        // Update run with planned IDs
        $run->update([
            'planned_node_ids' => $plan->orderedNodeIds,
            'status' => 'running',
            'started_at' => now(),
        ]);

        // Write skip records
        foreach ($plan->skippedNodeIds as $nodeId) {
            NodeRunRecord::create([
                'run_id' => $run->id,
                'node_id' => $nodeId,
                'status' => 'skipped',
                'skip_reason' => 'disabled',
            ]);
        }

        // Build node map for document lookups
        $nodeMap = [];
        foreach ($document['nodes'] ?? [] as $node) {
            $nodeMap[$node['id']] = $node;
        }

        // 3. Load existing node run records (for resumed runs)
        $nodeRunRecords = [];
        $existingRecords = NodeRunRecord::where('run_id', $run->id)->get();
        foreach ($existingRecords as $existing) {
            $nodeRunRecords[$existing->node_id] = $existing->toArray();
        }

        foreach ($plan->orderedNodeIds as $nodeId) {
            // Skip nodes that already completed successfully (resume scenario)
            $existingRecord = $nodeRunRecords[$nodeId] ?? null;
            if ($existingRecord && in_array($existingRecord['status'], ['success', 'error', 'skipped'])) {
                continue;
            }

            // Cooperative cancellation check
            $run->refresh();
            if ($run->status === 'cancelled') {
                break;
            }

            $node = $nodeMap[$nodeId] ?? null;
            if ($node === null) {
                continue;
            }

            $template = $this->registry->get($node['type'] ?? '');
            if ($template === null) {
                $record = NodeRunRecord::create([
                    'run_id' => $run->id,
                    'node_id' => $nodeId,
                    'status' => 'error',
                    'error_message' => "Unknown node type: {$node['type']}",
                ]);
                $nodeRunRecords[$nodeId] = $record->toArray();
                continue;
            }

            // Create pending record or reuse existing one (resume scenario)
            $existingModel = $existingRecord
                ? NodeRunRecord::where('run_id', $run->id)->where('node_id', $nodeId)->first()
                : null;

            $record = $existingModel ?? NodeRunRecord::create([
                'run_id' => $run->id,
                'node_id' => $nodeId,
                'status' => 'running',
                'started_at' => now(),
            ]);

            try {
                // Resolve inputs
                $inputResult = $this->inputResolver->resolve(
                    $node,
                    $template,
                    $document,
                    $nodeRunRecords,
                );

                if (!$inputResult['ok']) {
                    $record->update([
                        'status' => 'error',
                        'error_message' => $inputResult['reason'] ?? 'Input resolution failed',
                        'blocked_by_node_ids' => $inputResult['blockedBy'] ?? [],
                        'completed_at' => now(),
                    ]);
                    $nodeRunRecords[$nodeId] = $record->fresh()->toArray();
                    continue;
                }

                $inputs = $inputResult['inputs'] ?? [];

                // Check cache
                $cacheKey = $this->cache->buildKey(
                    $template->type,
                    $template->version,
                    1, // schema version
                    $node['data']['config'] ?? $node['config'] ?? [],
                    array_map(fn ($p) => is_array($p) ? $p : $p->toArray(), $inputs),
                );

                $cachedOutput = $this->cache->get($cacheKey);
                if ($cachedOutput !== null) {
                    $record->update([
                        'status' => 'success',
                        'output_payloads' => $cachedOutput,
                        'used_cache' => true,
                        'completed_at' => now(),
                        'duration_ms' => 0,
                    ]);
                    $nodeRunRecords[$nodeId] = $record->fresh()->toArray();
                    continue;
                }

                // Execute template
                $startTime = hrtime(true);

                $ctx = new NodeExecutionContext(
                    nodeId: $nodeId,
                    config: $node['data']['config'] ?? $node['config'] ?? [],
                    inputs: $inputs,
                    runId: $run->id,
                    providerRouter: $this->providerRouter,
                    artifactStore: $this->artifactStore,
                );

                $outputs = $template->execute($ctx);

                $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

                // Convert outputs to arrays for storage
                $outputArrays = [];
                foreach ($outputs as $key => $payload) {
                    $outputArrays[$key] = is_array($payload) ? $payload : $payload->toArray();
                }

                // Store in cache
                $this->cache->put($cacheKey, $template->type, $template->version, $outputArrays);

                $record->update([
                    'status' => 'success',
                    'input_payloads' => array_map(fn ($p) => is_array($p) ? $p : $p->toArray(), $inputs),
                    'output_payloads' => $outputArrays,
                    'used_cache' => false,
                    'duration_ms' => $durationMs,
                    'completed_at' => now(),
                ]);

                $nodeRunRecords[$nodeId] = $record->fresh()->toArray();

            } catch (ReviewPendingException $e) {
                // Store input payloads for review context
                $inputArrays = array_map(
                    fn ($p) => is_array($p) ? $p : $p->toArray(),
                    $inputs,
                );

                $record->update([
                    'status' => 'awaitingReview',
                    'input_payloads' => $inputArrays,
                    'completed_at' => null,
                ]);
                $nodeRunRecords[$nodeId] = $record->fresh()->toArray();

                $run->update(['status' => 'awaitingReview']);

                // Broadcast awaitingReview status
                broadcast(new \App\Events\NodeStatusChanged(
                    runId: $run->id,
                    nodeId: $nodeId,
                    status: 'awaitingReview',
                ));

                // For HumanGate nodes: stop execution here, resume via webhook/API
                $nodeType = $node['type'] ?? '';
                if ($nodeType === 'humanGate') {
                    return; // Exit execution — webhook will re-dispatch when response arrives
                }

                // For legacy ReviewCheckpoint: poll synchronously
                $pollResult = $this->pollForReviewDecision($run->id, $nodeId);

                if ($pollResult['cancelled']) {
                    // Run was cancelled during review - break to let deriveTerminalStatus handle it
                    break;
                }

                if ($pollResult['timedOut']) {
                    // Auto-reject after timeout
                    $record->update([
                        'status' => 'error',
                        'error_message' => 'Review timeout - auto-rejected after 1 hour',
                        'completed_at' => now(),
                        'output_payloads' => [
                            'decision' => 'reject',
                            'reason' => 'reviewTimeout',
                            'timedOutAt' => now()->toIso8601String(),
                        ],
                    ]);
                    $nodeRunRecords[$nodeId] = $record->fresh()->toArray();
                    broadcast(new \App\Events\NodeStatusChanged(
                        runId: $run->id,
                        nodeId: $nodeId,
                        status: 'error',
                        errorMessage: 'Review timeout - auto-rejected after 1 hour',
                    ));
                    continue;
                }

                if ($pollResult['rejected']) {
                    // Review rejected
                    $record->update([
                        'status' => 'error',
                        'error_message' => 'Review rejected by user',
                        'completed_at' => now(),
                    ]);
                    $nodeRunRecords[$nodeId] = $record->fresh()->toArray();
                    broadcast(new \App\Events\NodeStatusChanged(
                        runId: $run->id,
                        nodeId: $nodeId,
                        status: 'error',
                        errorMessage: 'Review rejected by user',
                    ));
                    continue;
                }

                // Review approved - extract decision from output_payloads and continue
                $record->refresh();
                $outputArrays = $record->output_payloads ?? [];
                $nodeRunRecords[$nodeId] = $record->toArray();

                broadcast(new \App\Events\NodeStatusChanged(
                    runId: $run->id,
                    nodeId: $nodeId,
                    status: 'success',
                    outputPayloads: $outputArrays,
                ));

            } catch (\Throwable $e) {
                $record->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
                $nodeRunRecords[$nodeId] = $record->fresh()->toArray();
            }
        }

        // Derive terminal status
        $this->deriveTerminalStatus($run);
    }

    private function deriveTerminalStatus(ExecutionRun $run): void
    {
        $run->refresh();

        if ($run->status === 'cancelled') {
            $run->update([
                'completed_at' => now(),
                'termination_reason' => 'cancelled',
            ]);
            return;
        }

        $records = $run->nodeRunRecords;
        $hasError = $records->contains('status', 'error');
        $hasAwaiting = $records->contains('status', 'awaitingReview');

        if ($hasAwaiting) {
            $run->update(['status' => 'awaitingReview']);
        } elseif ($hasError) {
            $run->update([
                'status' => 'error',
                'completed_at' => now(),
                'termination_reason' => 'node_error',
            ]);
        } else {
            $run->update([
                'status' => 'success',
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Poll for review decision on a node.
     *
     * @return array{cancelled: bool, timedOut: bool, rejected: bool}
     */
    private function pollForReviewDecision(string $runId, string $nodeId): array
    {
        $timeoutAt = now()->addHour();
        $pollIntervalSeconds = 2;

        while (now()->lt($timeoutAt)) {
            // Check if run was cancelled
            $run = ExecutionRun::find($runId);
            if ($run === null || $run->status === 'cancelled') {
                return ['cancelled' => true, 'timedOut' => false, 'rejected' => false];
            }

            // Check node status
            $record = NodeRunRecord::where('run_id', $runId)
                ->where('node_id', $nodeId)
                ->first();

            if ($record === null) {
                // Record deleted - treat as cancelled
                return ['cancelled' => true, 'timedOut' => false, 'rejected' => false];
            }

            if ($record->status === 'success') {
                // Approved
                return ['cancelled' => false, 'timedOut' => false, 'rejected' => false];
            }

            if ($record->status === 'error') {
                // Rejected
                return ['cancelled' => false, 'timedOut' => false, 'rejected' => true];
            }

            if ($record->status !== 'awaitingReview') {
                // Unexpected status - treat as cancelled
                return ['cancelled' => true, 'timedOut' => false, 'rejected' => false];
            }

            // Still awaiting review - wait and poll again
            sleep($pollIntervalSeconds);
        }

        // Timeout reached
        return ['cancelled' => false, 'timedOut' => true, 'rejected' => false];
    }
}
