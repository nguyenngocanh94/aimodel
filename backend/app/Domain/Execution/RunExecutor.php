<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Providers\ProviderRouter;
use App\Domain\RunTrigger;
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

        // 3. Iterate ordered nodes
        $nodeRunRecords = [];

        foreach ($plan->orderedNodeIds as $nodeId) {
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

            // Create pending record
            $record = NodeRunRecord::create([
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
                    $node['config'] ?? [],
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
                    config: $node['config'] ?? [],
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
                $record->update([
                    'status' => 'awaitingReview',
                    'completed_at' => null,
                ]);
                $nodeRunRecords[$nodeId] = $record->fresh()->toArray();

                $run->update(['status' => 'awaitingReview']);
                return; // Pause execution — AiModel-597 handles resumption

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
}
