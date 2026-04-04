<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Nodes\NodeTemplate;
use App\Domain\PortPayload;

final class InputResolver
{
    public function __construct(
        private RunCache $cache,
    ) {}

    /**
     * Resolve inputs for a node from upstream outputs, cache, or document data.
     *
     * @param array $node The node definition from the document
     * @param NodeTemplate $template The node's template
     * @param array $document The full workflow document
     * @param array<string, array> $nodeRunRecords Map of nodeId → run record (with output_payloads)
     * @return array{ok: bool, inputs?: array<string, PortPayload>, reason?: string, blockedBy?: string[]}
     */
    public function resolve(
        array $node,
        NodeTemplate $template,
        array $document,
        array $nodeRunRecords,
    ): array {
        $activePorts = $template->activePorts($node['config'] ?? []);
        $edges = $document['edges'] ?? [];
        $nodeMap = [];
        foreach ($document['nodes'] ?? [] as $n) {
            $nodeMap[$n['id']] = $n;
        }

        $inputs = [];
        $blockedBy = [];

        foreach ($activePorts->inputs as $inputPort) {
            // Find edge connecting to this input port
            $edge = $this->findEdgeForInput($edges, $node['id'], $inputPort->key);

            if ($edge === null) {
                if ($inputPort->required) {
                    return [
                        'ok' => false,
                        'reason' => "Required input '{$inputPort->key}' has no connection",
                        'blockedBy' => [],
                    ];
                }
                continue;
            }

            $sourceNodeId = $edge['source'];
            $sourcePortKey = $edge['sourceHandle'] ?? $edge['sourcePort'] ?? 'out';

            // Priority 1: Successful upstream output from current run
            $sourceRecord = $nodeRunRecords[$sourceNodeId] ?? null;
            if ($sourceRecord !== null && ($sourceRecord['status'] ?? '') === 'success') {
                $outputPayloads = $sourceRecord['output_payloads'] ?? [];
                if (isset($outputPayloads[$sourcePortKey])) {
                    $payload = $outputPayloads[$sourcePortKey];
                    $inputs[$inputPort->key] = $payload instanceof PortPayload
                        ? $payload
                        : PortPayload::fromArray($payload);
                    continue;
                }
            }

            // Priority 2: Cache hit
            $cachedOutputs = $this->tryCache($sourceNodeId, $nodeMap, $nodeRunRecords);
            if ($cachedOutputs !== null && isset($cachedOutputs[$sourcePortKey])) {
                $payload = $cachedOutputs[$sourcePortKey];
                $inputs[$inputPort->key] = $payload instanceof PortPayload
                    ? $payload
                    : PortPayload::fromArray($payload);
                continue;
            }

            // Priority 3: Preview data from document (for non-executable nodes or defaults)
            $sourceNode = $nodeMap[$sourceNodeId] ?? null;
            if ($sourceNode !== null) {
                $previewData = $sourceNode['data'][$sourcePortKey] ?? $sourceNode['preview'][$sourcePortKey] ?? null;
                if ($previewData !== null) {
                    $inputs[$inputPort->key] = $previewData instanceof PortPayload
                        ? $previewData
                        : PortPayload::fromArray($previewData);
                    continue;
                }
            }

            // Required port could not be resolved
            if ($inputPort->required) {
                $blockedBy[] = $sourceNodeId;
            }
        }

        if (!empty($blockedBy)) {
            return [
                'ok' => false,
                'reason' => 'Blocked by upstream nodes that have not produced output',
                'blockedBy' => $blockedBy,
            ];
        }

        return [
            'ok' => true,
            'inputs' => $inputs,
        ];
    }

    private function findEdgeForInput(array $edges, string $targetNodeId, string $targetPortKey): ?array
    {
        foreach ($edges as $edge) {
            $edgeTarget = $edge['target'] ?? '';
            $edgeTargetPort = $edge['targetHandle'] ?? $edge['targetPort'] ?? 'in';

            if ($edgeTarget === $targetNodeId && $edgeTargetPort === $targetPortKey) {
                return $edge;
            }
        }

        return null;
    }

    private function tryCache(string $sourceNodeId, array $nodeMap, array $nodeRunRecords): ?array
    {
        // Only attempt cache if we have record of the source node's config
        $sourceNode = $nodeMap[$sourceNodeId] ?? null;
        if ($sourceNode === null) {
            return null;
        }

        $sourceRecord = $nodeRunRecords[$sourceNodeId] ?? null;
        if ($sourceRecord === null) {
            return null;
        }

        // If the source had a cache key, try to retrieve
        $cacheKey = $sourceRecord['cache_key'] ?? null;
        if ($cacheKey === null) {
            return null;
        }

        return $this->cache->get($cacheKey);
    }
}
