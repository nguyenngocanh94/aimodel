<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * AI-generated workflow proposal — the OUTPUT surface of the workflow-designer
 * planner agent (see docs/plans/2026-04-16-workflow-designer-framework.md).
 *
 * A WorkflowPlan is not directly runnable. Lifecycle:
 *
 *   planner --> WorkflowPlan --> WorkflowPlanValidator --> WorkflowPlanToDocumentConverter --> WorkflowDocument --> RunExecutor
 *
 * Everything the planner "chose" (nodes, edges) carries a free-text `reason`
 * so drift-eval and humans can critique the creative judgement, not just the
 * structural validity.
 */
final readonly class WorkflowPlan
{
    /**
     * @param string $intent The user's brief, verbatim.
     * @param string $vibeMode Vibe archetype, e.g. 'funny_storytelling', 'clean_education', 'aesthetic_mood', 'raw_authentic'.
     * @param list<PlanNode> $nodes
     * @param list<PlanEdge> $edges
     * @param list<string> $assumptions What the planner assumed about the brief (platform, market, duration, tone...).
     * @param string $rationale Free-text explanation tying the nodes+edges back to intent+vibeMode.
     * @param array<string, mixed> $meta Open-ended: {modelUsed, plannerVersion, generatedAt, tokensIn, tokensOut, ...}
     */
    public function __construct(
        public string $intent,
        public string $vibeMode,
        public array $nodes,
        public array $edges,
        public array $assumptions,
        public string $rationale,
        public array $meta = [],
    ) {
        foreach ($nodes as $i => $node) {
            if (!$node instanceof PlanNode) {
                throw new \InvalidArgumentException("nodes[{$i}] must be a PlanNode instance");
            }
        }
        foreach ($edges as $i => $edge) {
            if (!$edge instanceof PlanEdge) {
                throw new \InvalidArgumentException("edges[{$i}] must be a PlanEdge instance");
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'vibeMode' => $this->vibeMode,
            'nodes' => array_map(fn (PlanNode $n) => $n->toArray(), $this->nodes),
            'edges' => array_map(fn (PlanEdge $e) => $e->toArray(), $this->edges),
            'assumptions' => $this->assumptions,
            'rationale' => $this->rationale,
            'meta' => $this->meta,
        ];
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray(), $flags | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $nodes = array_map(
            fn (array $n) => PlanNode::fromArray($n),
            (array) ($data['nodes'] ?? []),
        );
        $edges = array_map(
            fn (array $e) => PlanEdge::fromArray($e),
            (array) ($data['edges'] ?? []),
        );

        return new self(
            intent: (string) ($data['intent'] ?? ''),
            vibeMode: (string) ($data['vibeMode'] ?? ''),
            nodes: array_values($nodes),
            edges: array_values($edges),
            assumptions: array_values((array) ($data['assumptions'] ?? [])),
            rationale: (string) ($data['rationale'] ?? ''),
            meta: (array) ($data['meta'] ?? []),
        );
    }

    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        return self::fromArray($data);
    }
}
