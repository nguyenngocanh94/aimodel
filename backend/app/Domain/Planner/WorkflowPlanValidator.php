<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use App\Domain\Execution\TypeCompatibility;
use App\Domain\Nodes\ConfigSchemaTranspiler;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Support\Facades\Validator;

/**
 * Validates AI-proposed {@see WorkflowPlan}s before they become runnable
 * WorkflowDocuments.
 *
 * Checks (in order of severity-first surfacing):
 *  1. structural integrity (unique ids, edge endpoints exist, ports referenced, no cycles)
 *  2. node type existence against NodeTemplateRegistry
 *  3. per-node config validity (via Laravel Validator + configRules())
 *  4. active-port and port compatibility (TypeCompatibility; scalar→list coerce = warning)
 *  5. required-input satisfaction (edge feeding OR config-supplied default)
 *
 * Error shape: `{path, code, message, context?}` — see {@see WorkflowPlanValidation}.
 * Paths are dot-notation with bracketed numeric indices, e.g.
 *   "nodes[2].config.channel"
 *   "edges[0].targetPortKey"
 */
final class WorkflowPlanValidator
{
    // Stable error codes (machine-consumable).
    public const CODE_DUPLICATE_NODE_ID       = 'duplicate_node_id';
    public const CODE_UNKNOWN_NODE_TYPE       = 'unknown_node_type';
    public const CODE_EDGE_UNKNOWN_NODE       = 'edge_unknown_node';
    public const CODE_EDGE_SELF_LOOP          = 'edge_self_loop';
    public const CODE_EDGE_UNKNOWN_PORT       = 'edge_unknown_port';
    public const CODE_EDGE_INACTIVE_PORT      = 'edge_inactive_port';
    public const CODE_DUPLICATE_EDGE          = 'duplicate_edge';
    public const CODE_CYCLE_DETECTED          = 'cycle_detected';
    public const CODE_CONFIG_INVALID          = 'config_invalid';
    public const CODE_TYPE_INCOMPATIBLE       = 'type_incompatible';
    public const CODE_TYPE_COERCION_WARNING   = 'type_coercion';
    public const CODE_REQUIRED_INPUT_MISSING  = 'required_input_missing';
    public const CODE_ORPHAN_NODE             = 'orphan_node';
    public const CODE_EMPTY_PLAN              = 'empty_plan';
    public const CODE_MISSING_REASON          = 'missing_reason';

    public function __construct(
        private readonly NodeTemplateRegistry $registry,
        private readonly TypeCompatibility $typeCompat,
        private readonly ConfigSchemaTranspiler $transpiler,
    ) {}

    public function validate(WorkflowPlan $plan): WorkflowPlanValidation
    {
        /** @var list<array{path:string, code:string, message:string, context?: array<string,mixed>}> $errors */
        $errors = [];
        /** @var list<array{path:string, code:string, message:string, context?: array<string,mixed>}> $warnings */
        $warnings = [];

        // 0. Empty plan — nothing more to check.
        if (count($plan->nodes) === 0) {
            $errors[] = [
                'path' => 'nodes',
                'code' => self::CODE_EMPTY_PLAN,
                'message' => 'Plan must contain at least one node',
            ];
            return WorkflowPlanValidation::withErrors($errors, $warnings);
        }

        // 1. Node id uniqueness + type existence.
        [$nodeById, $nodeIndexById, $idErrors, $typeErrors] = $this->checkNodeIdsAndTypes($plan);
        array_push($errors, ...$idErrors, ...$typeErrors);

        // 2. Reason hygiene (warning — planner should carry reasons for drift-eval).
        foreach ($plan->nodes as $idx => $node) {
            if (trim($node->reason) === '') {
                $warnings[] = [
                    'path' => "nodes[{$idx}].reason",
                    'code' => self::CODE_MISSING_REASON,
                    'message' => "Node '{$node->id}' has no reason — drift-eval will lose explanatory signal",
                ];
            }
        }
        foreach ($plan->edges as $idx => $edge) {
            if (trim($edge->reason) === '') {
                $warnings[] = [
                    'path' => "edges[{$idx}].reason",
                    'code' => self::CODE_MISSING_REASON,
                    'message' => "Edge {$edge->sourceNodeId}.{$edge->sourcePortKey} → {$edge->targetNodeId}.{$edge->targetPortKey} has no reason",
                ];
            }
        }

        // 3. Per-node config validation.
        foreach ($plan->nodes as $idx => $node) {
            $template = $nodeById[$node->id] ?? null;
            if (!$template instanceof NodeTemplate) {
                // Unknown type — already flagged above.
                continue;
            }
            $configErrors = $this->validateNodeConfig($template, $node, $idx);
            array_push($errors, ...$configErrors);
        }

        // 4. Edge structural + port + compatibility checks.
        [$edgeErrors, $edgeWarnings, $incomingByTarget] = $this->checkEdges(
            $plan,
            $nodeById,
            $nodeIndexById,
        );
        array_push($errors, ...$edgeErrors);
        array_push($warnings, ...$edgeWarnings);

        // 5. Cycle detection (only meaningful if edges reference existing nodes).
        if ($this->allEdgesReferValidNodes($plan, $nodeById)) {
            $cycleErrors = $this->detectCycles($plan);
            array_push($errors, ...$cycleErrors);
        }

        // 6. Required input satisfaction.
        foreach ($plan->nodes as $idx => $node) {
            $template = $nodeById[$node->id] ?? null;
            if (!$template instanceof NodeTemplate) {
                continue;
            }
            $missing = $this->checkRequiredInputs($template, $node, $idx, $incomingByTarget);
            array_push($errors, ...$missing);
        }

        // 7. Orphan nodes (warning, matches WorkflowValidator behaviour for > 1 node).
        if (count($plan->nodes) > 1) {
            $connected = [];
            foreach ($plan->edges as $edge) {
                $connected[$edge->sourceNodeId] = true;
                $connected[$edge->targetNodeId] = true;
            }
            foreach ($plan->nodes as $idx => $node) {
                if (!isset($connected[$node->id])) {
                    $warnings[] = [
                        'path' => "nodes[{$idx}]",
                        'code' => self::CODE_ORPHAN_NODE,
                        'message' => "Node '{$node->id}' has no connections",
                    ];
                }
            }
        }

        return $errors === []
            ? WorkflowPlanValidation::ok($warnings)
            : WorkflowPlanValidation::withErrors($errors, $warnings);
    }

    // ── Per-section checks ────────────────────────────────────────────────

    /**
     * @return array{0: array<string, NodeTemplate|null>, 1: array<string, int>, 2: list<array>, 3: list<array>}
     */
    private function checkNodeIdsAndTypes(WorkflowPlan $plan): array
    {
        $nodeById = [];
        $nodeIndexById = [];
        $idErrors = [];
        $typeErrors = [];
        $seen = [];

        foreach ($plan->nodes as $idx => $node) {
            if (isset($seen[$node->id])) {
                $idErrors[] = [
                    'path' => "nodes[{$idx}].id",
                    'code' => self::CODE_DUPLICATE_NODE_ID,
                    'message' => "Duplicate node id '{$node->id}' (also at nodes[{$seen[$node->id]}])",
                    'context' => ['firstOccurrence' => $seen[$node->id]],
                ];
                continue;
            }
            $seen[$node->id] = $idx;

            $template = $this->registry->get($node->type);
            if ($template === null) {
                $typeErrors[] = [
                    'path' => "nodes[{$idx}].type",
                    'code' => self::CODE_UNKNOWN_NODE_TYPE,
                    'message' => "Unknown node type '{$node->type}' (not registered in NodeTemplateRegistry)",
                    'context' => ['nodeId' => $node->id],
                ];
                $nodeById[$node->id] = null;
            } else {
                $nodeById[$node->id] = $template;
            }
            $nodeIndexById[$node->id] = $idx;
        }

        return [$nodeById, $nodeIndexById, $idErrors, $typeErrors];
    }

    /**
     * @return list<array{path:string, code:string, message:string, context?: array<string,mixed>}>
     */
    private function validateNodeConfig(NodeTemplate $template, PlanNode $node, int $idx): array
    {
        $rules = $template->configRules();
        if ($rules === []) {
            return [];
        }

        $validator = Validator::make($node->config, $rules);
        if (!$validator->fails()) {
            return [];
        }

        $errors = [];
        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'path' => "nodes[{$idx}].config.{$field}",
                    'code' => self::CODE_CONFIG_INVALID,
                    'message' => $message,
                    'context' => [
                        'nodeId' => $node->id,
                        'nodeType' => $node->type,
                        'field' => $field,
                    ],
                ];
            }
        }
        return $errors;
    }

    /**
     * @param array<string, NodeTemplate|null> $nodeById
     * @param array<string, int> $nodeIndexById
     * @return array{0: list<array>, 1: list<array>, 2: array<string, true>} [errors, warnings, incomingByTarget]
     */
    private function checkEdges(WorkflowPlan $plan, array $nodeById, array $nodeIndexById): array
    {
        $errors = [];
        $warnings = [];
        /** @var array<string, true> $incoming */
        $incoming = [];
        /** @var array<string, int> $edgeSeen */
        $edgeSeen = [];

        foreach ($plan->edges as $idx => $edge) {
            $basePath = "edges[{$idx}]";

            // Duplicate edge (same 4-tuple)
            $sig = "{$edge->sourceNodeId}::{$edge->sourcePortKey}->{$edge->targetNodeId}::{$edge->targetPortKey}";
            if (isset($edgeSeen[$sig])) {
                $errors[] = [
                    'path' => $basePath,
                    'code' => self::CODE_DUPLICATE_EDGE,
                    'message' => "Duplicate edge (same source/target port pair exists at edges[{$edgeSeen[$sig]}])",
                    'context' => ['firstOccurrence' => $edgeSeen[$sig]],
                ];
                continue;
            }
            $edgeSeen[$sig] = $idx;

            // Self-loop
            if ($edge->sourceNodeId === $edge->targetNodeId) {
                $errors[] = [
                    'path' => $basePath,
                    'code' => self::CODE_EDGE_SELF_LOOP,
                    'message' => "Edge connects node '{$edge->sourceNodeId}' to itself",
                ];
                continue;
            }

            // Endpoint existence
            if (!array_key_exists($edge->sourceNodeId, $nodeById)) {
                $errors[] = [
                    'path' => "{$basePath}.sourceNodeId",
                    'code' => self::CODE_EDGE_UNKNOWN_NODE,
                    'message' => "Edge source '{$edge->sourceNodeId}' does not exist in plan.nodes",
                ];
            }
            if (!array_key_exists($edge->targetNodeId, $nodeById)) {
                $errors[] = [
                    'path' => "{$basePath}.targetNodeId",
                    'code' => self::CODE_EDGE_UNKNOWN_NODE,
                    'message' => "Edge target '{$edge->targetNodeId}' does not exist in plan.nodes",
                ];
            }

            $sourceTpl = $nodeById[$edge->sourceNodeId] ?? null;
            $targetTpl = $nodeById[$edge->targetNodeId] ?? null;
            if (!$sourceTpl instanceof NodeTemplate || !$targetTpl instanceof NodeTemplate) {
                continue;
            }

            $sourceIdx = $nodeIndexById[$edge->sourceNodeId];
            $targetIdx = $nodeIndexById[$edge->targetNodeId];
            $sourceNode = $plan->nodes[$sourceIdx];
            $targetNode = $plan->nodes[$targetIdx];

            $sourcePorts = $sourceTpl->activePorts($sourceNode->config);
            $targetPorts = $targetTpl->activePorts($targetNode->config);

            $sourcePort = $sourcePorts->getOutput($edge->sourcePortKey);
            $targetPort = $targetPorts->getInput($edge->targetPortKey);

            // Port existence + activeness on source.
            if ($sourcePort === null) {
                $staticSource = $sourceTpl->ports()->getOutput($edge->sourcePortKey);
                $code = $staticSource === null ? self::CODE_EDGE_UNKNOWN_PORT : self::CODE_EDGE_INACTIVE_PORT;
                $why = $staticSource === null ? 'not defined on template' : 'not active for current config';
                $errors[] = [
                    'path' => "{$basePath}.sourcePortKey",
                    'code' => $code,
                    'message' => "Output port '{$edge->sourcePortKey}' on node '{$edge->sourceNodeId}' ({$sourceNode->type}) is {$why}",
                ];
            }
            if ($targetPort === null) {
                $staticTarget = $targetTpl->ports()->getInput($edge->targetPortKey);
                $code = $staticTarget === null ? self::CODE_EDGE_UNKNOWN_PORT : self::CODE_EDGE_INACTIVE_PORT;
                $why = $staticTarget === null ? 'not defined on template' : 'not active for current config';
                $errors[] = [
                    'path' => "{$basePath}.targetPortKey",
                    'code' => $code,
                    'message' => "Input port '{$edge->targetPortKey}' on node '{$edge->targetNodeId}' ({$targetNode->type}) is {$why}",
                ];
            }

            if ($sourcePort === null || $targetPort === null) {
                continue;
            }

            $result = $this->typeCompat->check($sourcePort->dataType, $targetPort->dataType);
            if ($result->isError()) {
                $errors[] = [
                    'path' => $basePath,
                    'code' => self::CODE_TYPE_INCOMPATIBLE,
                    'message' => "Edge {$edge->sourceNodeId}.{$edge->sourcePortKey} → {$edge->targetNodeId}.{$edge->targetPortKey}: {$result->message}",
                    'context' => [
                        'sourceType' => $sourcePort->dataType->value,
                        'targetType' => $targetPort->dataType->value,
                    ],
                ];
            } elseif ($result->isWarning()) {
                $warnings[] = [
                    'path' => $basePath,
                    'code' => self::CODE_TYPE_COERCION_WARNING,
                    'message' => "Edge {$edge->sourceNodeId}.{$edge->sourcePortKey} → {$edge->targetNodeId}.{$edge->targetPortKey}: {$result->message}",
                    'context' => [
                        'sourceType' => $sourcePort->dataType->value,
                        'targetType' => $targetPort->dataType->value,
                        'coercionType' => $result->coercionType,
                    ],
                ];
            }

            // Record incoming edges (used for required-input check).
            $incoming["{$edge->targetNodeId}:{$edge->targetPortKey}"] = true;
        }

        return [$errors, $warnings, $incoming];
    }

    /**
     * @param array<string, NodeTemplate|null> $nodeById
     */
    private function allEdgesReferValidNodes(WorkflowPlan $plan, array $nodeById): bool
    {
        foreach ($plan->edges as $edge) {
            if (!isset($nodeById[$edge->sourceNodeId]) || !isset($nodeById[$edge->targetNodeId])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Kahn's-algorithm cycle detection. Returns one error per cycle detection event
     * (we don't enumerate individual cycles — message lists participating nodes).
     *
     * @return list<array{path:string, code:string, message:string, context?: array<string,mixed>}>
     */
    private function detectCycles(WorkflowPlan $plan): array
    {
        /** @var array<string, list<string>> $adj */
        $adj = [];
        /** @var array<string, int> $inDeg */
        $inDeg = [];

        foreach ($plan->nodes as $node) {
            $adj[$node->id] = [];
            $inDeg[$node->id] = 0;
        }

        foreach ($plan->edges as $edge) {
            if ($edge->sourceNodeId === $edge->targetNodeId) {
                continue; // self-loops reported separately
            }
            if (!isset($adj[$edge->sourceNodeId]) || !isset($adj[$edge->targetNodeId])) {
                continue;
            }
            $adj[$edge->sourceNodeId][] = $edge->targetNodeId;
            $inDeg[$edge->targetNodeId]++;
        }

        $queue = [];
        foreach ($inDeg as $id => $deg) {
            if ($deg === 0) {
                $queue[] = $id;
            }
        }

        $visited = 0;
        while ($queue !== []) {
            $current = array_shift($queue);
            $visited++;
            foreach ($adj[$current] as $next) {
                $inDeg[$next]--;
                if ($inDeg[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if ($visited >= count($plan->nodes)) {
            return [];
        }

        $stuck = [];
        foreach ($inDeg as $id => $deg) {
            if ($deg > 0) {
                $stuck[] = $id;
            }
        }

        return [[
            'path' => 'edges',
            'code' => self::CODE_CYCLE_DETECTED,
            'message' => 'Plan contains a cycle involving: ' . implode(', ', $stuck),
            'context' => ['nodeIds' => $stuck],
        ]];
    }

    /**
     * @param array<string, true> $incomingByTarget
     * @return list<array{path:string, code:string, message:string, context?: array<string,mixed>}>
     */
    private function checkRequiredInputs(NodeTemplate $template, PlanNode $node, int $idx, array $incomingByTarget): array
    {
        $errors = [];
        $ports = $template->activePorts($node->config);
        foreach ($ports->inputs as $input) {
            if (!$input->required) {
                continue;
            }
            $key = "{$node->id}:{$input->key}";
            if (isset($incomingByTarget[$key])) {
                continue;
            }
            // Optional config-supplied default: allow `config.inputs.<key>` OR `config.<key>` to satisfy.
            if ($this->hasConfigSuppliedInput($node->config, $input->key)) {
                continue;
            }
            $errors[] = [
                'path' => "nodes[{$idx}].inputs.{$input->key}",
                'code' => self::CODE_REQUIRED_INPUT_MISSING,
                'message' => "Required input '{$input->key}' on node '{$node->id}' ({$node->type}) has no incoming edge and no config default",
                'context' => [
                    'nodeId' => $node->id,
                    'portKey' => $input->key,
                    'dataType' => $input->dataType->value,
                ],
            ];
        }
        return $errors;
    }

    /**
     * Allow a required input to be satisfied by:
     *   - config.inputs.<key>  (explicit planner convention)
     *   - config.<key>         (some templates alias config to input default, e.g. userPrompt)
     *
     * @param array<string, mixed> $config
     */
    private function hasConfigSuppliedInput(array $config, string $portKey): bool
    {
        if (isset($config['inputs']) && is_array($config['inputs']) && array_key_exists($portKey, $config['inputs'])) {
            $value = $config['inputs'][$portKey];
            return $value !== null && $value !== '';
        }
        if (array_key_exists($portKey, $config)) {
            $value = $config[$portKey];
            return $value !== null && $value !== '';
        }
        return false;
    }

    /**
     * Expose the transpiler (used by external tooling for UI schema introspection).
     *
     * @return array<string, mixed>|null JSON Schema for the template's config rules.
     */
    public function configSchemaFor(string $nodeType): ?array
    {
        $template = $this->registry->get($nodeType);
        if ($template === null) {
            return null;
        }
        return $this->transpiler->transpile($template->configRules(), $template->defaultConfig());
    }
}
