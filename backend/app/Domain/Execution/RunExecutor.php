<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\HumanProposal;
use App\Domain\Nodes\HumanResponse;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\RunTrigger;
use App\Events\NodeStatusChanged;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use App\Services\ArtifactStoreContract;
use App\Services\Memory\RunMemoryStore;

final class RunExecutor
{
    public function __construct(
        private ExecutionPlanner $planner,
        private InputResolver $inputResolver,
        private NodeTemplateRegistry $registry,
        private RunCache $cache,
        private ArtifactStoreContract $artifactStore,
        private ProposalSender $proposalSender,
        private RunMemoryStore $memory,
    ) {}

    /**
     * Resolve the workflow slug for a run.
     *
     * Prefers a real `slug` column when it exists (pending catalog migration),
     * falls back to the workflow UUID so memory scope is always deterministic.
     */
    private function workflowSlug(ExecutionRun $run): ?string
    {
        $workflow = $run->workflow;
        if ($workflow === null) {
            return null;
        }

        /** @var mixed $slug */
        $slug = $workflow->slug ?? null;
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        return (string) $workflow->id;
    }

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

            $nodeConfig = $node['data']['config'] ?? $node['config'] ?? [];

            if ($template->needsHumanLoop($nodeConfig)) {
                $this->executeHumanLoop($run, $node, $template, $document, $nodeMap, $nodeRunRecords, $record);
                // Node returned awaitingHuman — stop the pipeline
                // resume() will continue it later
                return;
            }

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
                    artifactStore: $this->artifactStore,
                    memory: $this->memory,
                    workflowSlug: $this->workflowSlug($run),
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
                // Legacy: HumanGateTemplate still throws this
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

                broadcast(new \App\Events\NodeStatusChanged(
                    runId: $run->id,
                    nodeId: $nodeId,
                    status: 'awaitingReview',
                ));

                return; // Exit execution — webhook will re-dispatch when response arrives

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
     * Resume execution after a human responds to a proposal.
     * Called by the webhook handler when a human response arrives.
     */
    public function resume(string $runId, string $nodeId, HumanResponse $response): void
    {
        $run = ExecutionRun::findOrFail($runId);
        $document = $run->document_snapshot ?? $run->workflow->document;

        // Find the pending interaction
        $pending = PendingInteraction::where('run_id', $runId)
            ->where('node_id', $nodeId)
            ->waiting()
            ->latest()
            ->firstOrFail();

        // Find the node run record
        $record = NodeRunRecord::where('run_id', $runId)
            ->where('node_id', $nodeId)
            ->firstOrFail();

        // Build node map
        $nodeMap = [];
        foreach ($document['nodes'] ?? [] as $node) {
            $nodeMap[$node['id']] = $node;
        }

        $node = $nodeMap[$nodeId] ?? null;
        if ($node === null) {
            throw new \RuntimeException("Node {$nodeId} not found in workflow document");
        }

        $template = $this->registry->get($node['type'] ?? '');
        if ($template === null) {
            throw new \RuntimeException("Unknown node type: {$node['type']}");
        }

        // Rebuild inputs from the saved input_payloads on the record
        $savedInputs = $record->input_payloads ?? [];
        $inputs = [];
        foreach ($savedInputs as $key => $payloadArr) {
            $inputs[$key] = \App\Domain\PortPayload::fromArray($payloadArr);
        }

        $ctx = new NodeExecutionContext(
            nodeId: $nodeId,
            config: $node['data']['config'] ?? $node['config'] ?? [],
            inputs: $inputs,
            runId: $runId,
            artifactStore: $this->artifactStore,
            humanProposalState: $pending->node_state ?? [],
            memory: $this->memory,
            workflowSlug: $this->workflowSlug($run),
        );

        // Mark the old pending interaction as responded
        $pending->markResponded($response->toArray());

        try {
            $result = $template->handleResponse($ctx, $response);
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $run->update(['status' => 'error', 'completed_at' => now()]);
            return;
        }

        if ($result instanceof HumanProposal) {
            // Loop: save new pending interaction, stay in awaitingHuman
            $newPending = $this->savePendingInteraction($run, $nodeId, $result, $savedInputs);

            // Send new proposal to channel
            $channelConfig = $node['data']['config'] ?? $node['config'] ?? [];
            $this->proposalSender->send($newPending, $channelConfig);

            broadcast(new \App\Events\NodeStatusChanged(
                runId: $runId,
                nodeId: $nodeId,
                status: 'awaitingHuman',
            ));
            return;
        }

        // Complete: save outputs and continue pipeline
        $outputArrays = [];
        foreach ($result as $key => $payload) {
            $outputArrays[$key] = is_array($payload) ? $payload : $payload->toArray();
        }

        $record->update([
            'status' => 'success',
            'output_payloads' => $outputArrays,
            'completed_at' => now(),
        ]);

        broadcast(new \App\Events\NodeStatusChanged(
            runId: $runId,
            nodeId: $nodeId,
            status: 'success',
            outputPayloads: $outputArrays,
        ));

        // Continue executing remaining pipeline nodes
        $this->continueAfterResume($run);
    }

    private function executeHumanLoop(
        ExecutionRun $run,
        array $node,
        NodeTemplate $template,
        array $document,
        array $nodeMap,
        array &$nodeRunRecords,
        NodeRunRecord $record,
    ): void {
        // Resolve inputs (same as normal execution)
        $inputResult = $this->inputResolver->resolve($node, $template, $document, $nodeRunRecords);

        if (!$inputResult['ok']) {
            $record->update([
                'status' => 'error',
                'error_message' => $inputResult['reason'] ?? 'Input resolution failed',
                'blocked_by_node_ids' => $inputResult['blockedBy'] ?? [],
                'completed_at' => now(),
            ]);
            $nodeRunRecords[$node['id']] = $record->fresh()->toArray();
            return;
        }

        $inputs = $inputResult['inputs'] ?? [];

        $ctx = new NodeExecutionContext(
            nodeId: $node['id'],
            config: $node['data']['config'] ?? $node['config'] ?? [],
            inputs: $inputs,
            runId: $run->id,
            artifactStore: $this->artifactStore,
            memory: $this->memory,
            workflowSlug: $this->workflowSlug($run),
        );

        try {
            $proposal = $template->propose($ctx);
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            $nodeRunRecords[$node['id']] = $record->fresh()->toArray();
            return;
        }

        $pending = $this->savePendingInteraction($run, $node['id'], $proposal, $inputs);

        // Send proposal to the channel
        $channelConfig = $node['data']['config'] ?? $node['config'] ?? [];
        $this->proposalSender->send($pending, $channelConfig);

        $record->update([
            'status' => 'awaitingHuman',
            'input_payloads' => array_map(fn ($p) => is_array($p) ? $p : $p->toArray(), $inputs),
            'completed_at' => null,
        ]);
        $nodeRunRecords[$node['id']] = $record->fresh()->toArray();

        $run->update(['status' => 'awaitingHuman']);

        broadcast(new \App\Events\NodeStatusChanged(
            runId: $run->id,
            nodeId: $node['id'],
            status: 'awaitingHuman',
        ));
    }

    private function savePendingInteraction(
        ExecutionRun $run,
        string $nodeId,
        HumanProposal $proposal,
        array $inputs,
    ): PendingInteraction {
        return PendingInteraction::create([
            'run_id' => $run->id,
            'node_id' => $nodeId,
            'channel' => $proposal->channel,
            'status' => 'waiting',
            'proposal_payload' => $proposal->toArray(),
            'node_state' => $proposal->state,
        ]);
    }

    private function continueAfterResume(ExecutionRun $run): void
    {
        $run->update(['status' => 'running']);
        $this->execute($run); // Re-enters execute() — already-completed nodes are skipped
    }
}
