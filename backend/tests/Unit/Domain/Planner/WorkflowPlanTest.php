<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Planner\PlanEdge;
use App\Domain\Planner\PlanNode;
use App\Domain\Planner\WorkflowPlan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowPlanTest extends TestCase
{
    #[Test]
    public function plan_node_requires_non_empty_id_and_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanNode(id: '', type: 'scriptWriter', config: [], reason: 'x');
    }

    #[Test]
    public function plan_edge_requires_non_empty_endpoints(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanEdge(
            sourceNodeId: 'a',
            sourcePortKey: '',
            targetNodeId: 'b',
            targetPortKey: 'in',
            reason: 'x',
        );
    }

    #[Test]
    public function workflow_plan_rejects_non_plan_node_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line — deliberate bad input */
        new WorkflowPlan(
            intent: 'x',
            vibeMode: 'funny_storytelling',
            nodes: [['id' => 'a']],
            edges: [],
            assumptions: [],
            rationale: 'why',
        );
    }

    #[Test]
    public function plan_round_trips_to_and_from_array(): void
    {
        $plan = $this->samplePlan();
        $out = WorkflowPlan::fromArray($plan->toArray());

        $this->assertSame($plan->intent, $out->intent);
        $this->assertSame($plan->vibeMode, $out->vibeMode);
        $this->assertCount(count($plan->nodes), $out->nodes);
        $this->assertCount(count($plan->edges), $out->edges);
        $this->assertSame($plan->nodes[0]->id, $out->nodes[0]->id);
        $this->assertSame($plan->edges[0]->sourcePortKey, $out->edges[0]->sourcePortKey);
        $this->assertSame($plan->assumptions, $out->assumptions);
        $this->assertSame($plan->rationale, $out->rationale);
        $this->assertSame($plan->meta, $out->meta);
    }

    #[Test]
    public function plan_round_trips_through_json(): void
    {
        $plan = $this->samplePlan();
        $out = WorkflowPlan::fromJson($plan->toJson());
        $this->assertSame($plan->toArray(), $out->toArray());
    }

    #[Test]
    public function plan_node_preserves_reason_and_optional_label(): void
    {
        $node = new PlanNode(
            id: 'n1',
            type: 'scriptWriter',
            config: ['style' => 'fast'],
            reason: 'Need narrative driver before image prompts',
            label: 'Funny writer',
        );

        $round = PlanNode::fromArray($node->toArray());
        $this->assertSame('Need narrative driver before image prompts', $round->reason);
        $this->assertSame('Funny writer', $round->label);
    }

    #[Test]
    public function plan_edge_serializes_with_source_node_id_keys(): void
    {
        $edge = new PlanEdge(
            sourceNodeId: 'a',
            sourcePortKey: 'script',
            targetNodeId: 'b',
            targetPortKey: 'prompt',
            reason: 'wiring',
        );

        $arr = $edge->toArray();
        $this->assertArrayHasKey('sourceNodeId', $arr);
        $this->assertArrayHasKey('sourcePortKey', $arr);
        $this->assertArrayHasKey('targetNodeId', $arr);
        $this->assertArrayHasKey('targetPortKey', $arr);
    }

    private function samplePlan(): WorkflowPlan
    {
        return new WorkflowPlan(
            intent: 'Funny genz storytelling for TikTok Vietnam',
            vibeMode: 'funny_storytelling',
            nodes: [
                new PlanNode('n1', 'userPrompt', ['prompt' => 'hello'], 'Seed'),
                new PlanNode('n2', 'scriptWriter', [
                    'style' => 'fast',
                    'structure' => 'three_act',
                    'includeHook' => true,
                    'includeCTA' => true,
                    'targetDurationSeconds' => 30,
                    'provider' => 'stub',
                ], 'Need narrative driver'),
            ],
            edges: [
                new PlanEdge('n1', 'prompt', 'n2', 'prompt', 'wire prompt'),
            ],
            assumptions: ['platform=tiktok', 'duration<=30s'],
            rationale: 'Simple two-step plan',
            meta: ['plannerVersion' => 'v0.1.0'],
        );
    }
}
