<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use App\Domain\Nodes\NodeTemplateRegistry;

/**
 * Convert a validated {@see WorkflowPlan} into the runnable WorkflowDocument
 * array shape (matches `frontend/src/features/workflows/domain/workflow-types.ts`
 * and the shape the existing {@see \App\Domain\Execution\WorkflowValidator} and
 * {@see \App\Domain\Execution\RunExecutor} consume).
 *
 * Edge shape produced uses BOTH key spellings so the output is accepted by:
 *   - Frontend canvas (`sourceNodeId`, `sourcePortKey`, ...)
 *   - Existing backend {@see \App\Domain\Execution\WorkflowValidator} (`source`, `target`, `sourceHandle`, `targetHandle`)
 * This redundancy is deliberate and removes a whole class of naming drift bugs.
 *
 * This converter does NOT re-validate — callers should run
 * {@see WorkflowPlanValidator::validate()} first.
 */
final class WorkflowPlanToDocumentConverter
{
    private const SCHEMA_VERSION = 1;
    private const LAYOUT_STEP_X  = 320;
    private const LAYOUT_STEP_Y  = 180;

    public function __construct(
        private readonly NodeTemplateRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed> WorkflowDocument array.
     */
    public function convert(WorkflowPlan $plan, ?string $workflowId = null, ?string $name = null): array
    {
        $now = gmdate('c');
        $id = $workflowId ?? self::generateUuid();

        $nodes = [];
        foreach ($plan->nodes as $idx => $node) {
            $template = $this->registry->get($node->type);
            $label = $node->label
                ?? ($template !== null ? $template->title : $node->type);

            $nodes[] = [
                'id' => $node->id,
                'type' => $node->type,
                'label' => $label,
                'position' => [
                    'x' => ($idx % 5) * self::LAYOUT_STEP_X,
                    'y' => intdiv($idx, 5) * self::LAYOUT_STEP_Y,
                ],
                'config' => $node->config,
                'notes' => $node->reason !== '' ? $node->reason : null,
            ];
        }

        $edges = [];
        foreach ($plan->edges as $idx => $edge) {
            $edges[] = [
                // Frontend-canonical keys.
                'id' => "e{$idx}-{$edge->sourceNodeId}-{$edge->targetNodeId}",
                'sourceNodeId' => $edge->sourceNodeId,
                'sourcePortKey' => $edge->sourcePortKey,
                'targetNodeId' => $edge->targetNodeId,
                'targetPortKey' => $edge->targetPortKey,
                // Backend-legacy aliases (consumed by WorkflowValidator + RunExecutor).
                'source' => $edge->sourceNodeId,
                'target' => $edge->targetNodeId,
                'sourceHandle' => $edge->sourcePortKey,
                'targetHandle' => $edge->targetPortKey,
            ];
        }

        return [
            'id' => $id,
            'schemaVersion' => self::SCHEMA_VERSION,
            'name' => $name ?? self::deriveName($plan),
            'description' => trim($plan->rationale) !== '' ? $plan->rationale : $plan->intent,
            'tags' => array_values(array_filter([$plan->vibeMode !== '' ? $plan->vibeMode : null])),
            'nodes' => $nodes,
            'edges' => $edges,
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            'createdAt' => $now,
            'updatedAt' => $now,
            'meta' => [
                'source' => 'workflow-designer',
                'intent' => $plan->intent,
                'vibeMode' => $plan->vibeMode,
                'assumptions' => $plan->assumptions,
                'planner' => $plan->meta,
            ],
        ];
    }

    private static function deriveName(WorkflowPlan $plan): string
    {
        $base = trim($plan->intent);
        if ($base === '') {
            return 'AI-planned workflow';
        }
        $slice = mb_substr($base, 0, 60);
        return $slice === $base ? $slice : ($slice . '…');
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
