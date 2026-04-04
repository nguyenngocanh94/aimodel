<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Support\Facades\Validator;

class WorkflowValidator
{
    private TypeCompatibility $typeChecker;

    public function __construct()
    {
        $this->typeChecker = new TypeCompatibility();
    }

    /**
     * Validate a workflow document and return a list of issues.
     *
     * @return array<int, array{type: string, severity: string, nodeId: ?string, message: string}>
     */
    public function validate(array $document, NodeTemplateRegistry $registry): array
    {
        $nodes = $document['nodes'] ?? [];
        $edges = $document['edges'] ?? [];
        $issues = [];

        // Build lookup maps
        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[$node['id']] = $node;
        }

        // 1. Cycle detection
        $cycleIssues = $this->detectCycles($nodes, $edges);
        array_push($issues, ...$cycleIssues);

        // 2. Unknown node types
        foreach ($nodes as $node) {
            $template = $registry->get($node['type'] ?? '');
            if ($template === null) {
                $issues[] = [
                    'type' => 'unknown_type',
                    'severity' => 'error',
                    'nodeId' => $node['id'],
                    'message' => "Unknown node type: '{$node['type']}'",
                ];
            }
        }

        // 3. Config validation per node
        foreach ($nodes as $node) {
            $template = $registry->get($node['type'] ?? '');
            if ($template === null) {
                continue;
            }

            $config = $node['config'] ?? [];
            $rules = $template->configRules();

            if (!empty($rules)) {
                $validator = Validator::make($config, $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $error) {
                        $issues[] = [
                            'type' => 'config_invalid',
                            'severity' => 'error',
                            'nodeId' => $node['id'],
                            'message' => $error,
                        ];
                    }
                }
            }
        }

        // 4. Port compatibility per edge
        foreach ($edges as $edge) {
            $sourceNode = $nodeMap[$edge['source']] ?? null;
            $targetNode = $nodeMap[$edge['target']] ?? null;

            if ($sourceNode === null || $targetNode === null) {
                continue;
            }

            $sourceTemplate = $registry->get($sourceNode['type'] ?? '');
            $targetTemplate = $registry->get($targetNode['type'] ?? '');

            if ($sourceTemplate === null || $targetTemplate === null) {
                continue;
            }

            $sourcePortKey = $edge['sourceHandle'] ?? $edge['sourcePort'] ?? null;
            $targetPortKey = $edge['targetHandle'] ?? $edge['targetPort'] ?? null;

            if ($sourcePortKey === null || $targetPortKey === null) {
                continue;
            }

            $sourcePorts = $sourceTemplate->activePorts($sourceNode['config'] ?? []);
            $targetPorts = $targetTemplate->activePorts($targetNode['config'] ?? []);

            $sourcePort = $sourcePorts->getOutput($sourcePortKey);
            $targetPort = $targetPorts->getInput($targetPortKey);

            if ($sourcePort !== null && $targetPort !== null) {
                $result = $this->typeChecker->check($sourcePort->dataType, $targetPort->dataType);
                if ($result->isError()) {
                    $issues[] = [
                        'type' => 'type_incompatible',
                        'severity' => 'error',
                        'nodeId' => $edge['target'],
                        'message' => "Edge {$edge['source']}.{$sourcePortKey} → {$edge['target']}.{$targetPortKey}: {$result->message}",
                    ];
                } elseif ($result->isWarning()) {
                    $issues[] = [
                        'type' => 'type_coercion',
                        'severity' => 'warning',
                        'nodeId' => $edge['target'],
                        'message' => "Edge {$edge['source']}.{$sourcePortKey} → {$edge['target']}.{$targetPortKey}: {$result->message}",
                    ];
                }
            }
        }

        // 5. Missing required inputs
        $incomingEdges = [];
        foreach ($edges as $edge) {
            $targetPortKey = $edge['targetHandle'] ?? $edge['targetPort'] ?? 'default';
            $key = $edge['target'] . ':' . $targetPortKey;
            $incomingEdges[$key] = true;
        }

        foreach ($nodes as $node) {
            $template = $registry->get($node['type'] ?? '');
            if ($template === null) {
                continue;
            }

            $ports = $template->activePorts($node['config'] ?? []);
            foreach ($ports->inputs as $input) {
                if ($input->required) {
                    $key = $node['id'] . ':' . $input->key;
                    if (!isset($incomingEdges[$key])) {
                        $issues[] = [
                            'type' => 'missing_input',
                            'severity' => 'error',
                            'nodeId' => $node['id'],
                            'message' => "Required input '{$input->key}' has no incoming connection",
                        ];
                    }
                }
            }
        }

        // 6. Inactive port connections
        foreach ($edges as $edge) {
            $sourceNode = $nodeMap[$edge['source']] ?? null;
            $targetNode = $nodeMap[$edge['target']] ?? null;

            if ($sourceNode === null || $targetNode === null) {
                continue;
            }

            $sourcePortKey = $edge['sourceHandle'] ?? $edge['sourcePort'] ?? null;
            $targetPortKey = $edge['targetHandle'] ?? $edge['targetPort'] ?? null;

            if ($sourcePortKey !== null) {
                $sourceTemplate = $registry->get($sourceNode['type'] ?? '');
                if ($sourceTemplate !== null) {
                    $activePorts = $sourceTemplate->activePorts($sourceNode['config'] ?? []);
                    if (!$activePorts->hasOutput($sourcePortKey)) {
                        $issues[] = [
                            'type' => 'inactive_port',
                            'severity' => 'error',
                            'nodeId' => $edge['source'],
                            'message' => "Output port '{$sourcePortKey}' is not active for current config",
                        ];
                    }
                }
            }

            if ($targetPortKey !== null) {
                $targetTemplate = $registry->get($targetNode['type'] ?? '');
                if ($targetTemplate !== null) {
                    $activePorts = $targetTemplate->activePorts($targetNode['config'] ?? []);
                    if (!$activePorts->hasInput($targetPortKey)) {
                        $issues[] = [
                            'type' => 'inactive_port',
                            'severity' => 'error',
                            'nodeId' => $edge['target'],
                            'message' => "Input port '{$targetPortKey}' is not active for current config",
                        ];
                    }
                }
            }
        }

        // 7. Orphan nodes (warning only)
        $connectedNodes = [];
        foreach ($edges as $edge) {
            $connectedNodes[$edge['source']] = true;
            $connectedNodes[$edge['target']] = true;
        }

        if (count($nodes) > 1) {
            foreach ($nodes as $node) {
                if (!isset($connectedNodes[$node['id']])) {
                    $issues[] = [
                        'type' => 'orphan_node',
                        'severity' => 'warning',
                        'nodeId' => $node['id'],
                        'message' => "Node '{$node['id']}' has no connections",
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{type: string, severity: string, nodeId: ?string, message: string}>
     */
    private function detectCycles(array $nodes, array $edges): array
    {
        $adjacency = [];
        $inDegree = [];

        foreach ($nodes as $node) {
            $id = $node['id'];
            $adjacency[$id] = [];
            $inDegree[$id] = 0;
        }

        foreach ($edges as $edge) {
            $source = $edge['source'];
            $target = $edge['target'];

            if (!isset($adjacency[$source]) || !isset($adjacency[$target])) {
                continue;
            }

            $adjacency[$source][] = $target;
            $inDegree[$target]++;
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        $visited = 0;
        while (!empty($queue)) {
            $current = array_shift($queue);
            $visited++;

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        if ($visited < count($nodes)) {
            return [[
                'type' => 'cycle_detected',
                'severity' => 'error',
                'nodeId' => null,
                'message' => 'Workflow contains a cycle',
            ]];
        }

        return [];
    }
}
