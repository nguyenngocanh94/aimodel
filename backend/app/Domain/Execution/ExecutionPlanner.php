<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\RunTrigger;

final class ExecutionPlanner
{
    /**
     * Build an execution plan for the given workflow document and trigger.
     *
     * @param  array{nodes: list<array{id: string, disabled?: bool}>, edges: list<array{source: string, target: string}>}  $document
     *
     * @throws \RuntimeException When a cycle is detected in the workflow graph.
     */
    public function plan(array $document, RunTrigger $trigger, ?string $targetNodeId = null): ExecutionPlan
    {
        $nodes = $document['nodes'] ?? [];
        $edges = $document['edges'] ?? [];

        // Index nodes by ID for quick lookup.
        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[$node['id']] = $node;
        }

        // Build adjacency lists (source → targets) and reverse (target → sources).
        $forward = [];  // nodeId => [downstream nodeIds]
        $reverse = [];  // nodeId => [upstream nodeIds]
        foreach (array_keys($nodeMap) as $id) {
            $forward[$id] = [];
            $reverse[$id] = [];
        }
        foreach ($edges as $edge) {
            $src = $edge['source'] ?? $edge['sourceNodeId'] ?? '';
            $tgt = $edge['target'] ?? $edge['targetNodeId'] ?? '';
            if ($src && $tgt && isset($nodeMap[$src], $nodeMap[$tgt])) {
                $forward[$src][] = $tgt;
                $reverse[$tgt][] = $src;
            }
        }

        // Determine candidate node IDs based on the trigger.
        $candidateIds = match ($trigger) {
            RunTrigger::RunWorkflow, RunTrigger::TelegramWebhook, RunTrigger::WebhookTrigger => array_keys($nodeMap),
            RunTrigger::RunNode => $targetNodeId !== null ? [$targetNodeId] : [],
            RunTrigger::RunFromHere => $this->collectDownstream($targetNodeId, $forward),
            RunTrigger::RunUpToHere => $this->collectUpstream($targetNodeId, $reverse),
        };

        // Prune disabled nodes into skipped list.
        $activeIds = [];
        $skippedIds = [];
        foreach ($candidateIds as $id) {
            if (isset($nodeMap[$id]) && ! empty($nodeMap[$id]['disabled'])) {
                $skippedIds[] = $id;
            } else {
                $activeIds[] = $id;
            }
        }

        // Topological sort via Kahn's algorithm (scoped to active IDs).
        $orderedIds = $this->topologicalSort($activeIds, $forward);

        return new ExecutionPlan(
            orderedNodeIds: $orderedIds,
            skippedNodeIds: array_values($skippedIds),
            trigger: $trigger,
            targetNodeId: $targetNodeId,
        );
    }

    /**
     * Collect the target node and all its downstream (transitive) descendants via BFS.
     *
     * @param  array<string, list<string>>  $forward
     * @return list<string>
     */
    private function collectDownstream(?string $startId, array $forward): array
    {
        if ($startId === null) {
            return [];
        }

        $visited = [];
        $queue = [$startId];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($forward[$current] ?? [] as $child) {
                if (! isset($visited[$child])) {
                    $queue[] = $child;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * Collect the target node and all its upstream (transitive) ancestors via BFS.
     *
     * @param  array<string, list<string>>  $reverse
     * @return list<string>
     */
    private function collectUpstream(?string $startId, array $reverse): array
    {
        if ($startId === null) {
            return [];
        }

        $visited = [];
        $queue = [$startId];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($reverse[$current] ?? [] as $parent) {
                if (! isset($visited[$parent])) {
                    $queue[] = $parent;
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * Kahn's algorithm scoped to a subset of node IDs.
     *
     * @param  list<string>  $scopeIds
     * @param  array<string, list<string>>  $forward
     * @return list<string>
     *
     * @throws \RuntimeException When a cycle is detected.
     */
    private function topologicalSort(array $scopeIds, array $forward): array
    {
        if ($scopeIds === []) {
            return [];
        }

        $scopeSet = array_flip($scopeIds);

        // Build in-degree map scoped to the active subset.
        $inDegree = [];
        foreach ($scopeIds as $id) {
            $inDegree[$id] = 0;
        }
        foreach ($scopeIds as $id) {
            foreach ($forward[$id] ?? [] as $neighbor) {
                if (isset($scopeSet[$neighbor])) {
                    $inDegree[$neighbor]++;
                }
            }
        }

        // Seed the queue with zero in-degree nodes.
        $queue = [];
        foreach ($inDegree as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $sorted = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($forward[$current] ?? [] as $neighbor) {
                if (! isset($scopeSet[$neighbor])) {
                    continue;
                }
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if (count($sorted) !== count($scopeIds)) {
            throw new \RuntimeException('Cycle detected in workflow graph');
        }

        return $sorted;
    }
}
