<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\Execution\ExecutionPlan;
use App\Domain\Execution\ExecutionPlanner;
use App\Domain\RunTrigger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExecutionPlannerTest extends TestCase
{
    private ExecutionPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new ExecutionPlanner();
    }

    // ------------------------------------------------------------------
    //  Helper: build a standard linear 4-node document
    //  node-1 → node-2 → node-3 → node-4
    // ------------------------------------------------------------------
    private function linearDocument(array $disabledIds = []): array
    {
        $nodes = [];
        for ($i = 1; $i <= 4; $i++) {
            $nodes[] = [
                'id' => "node-{$i}",
                'type' => "type-{$i}",
                'disabled' => in_array("node-{$i}", $disabledIds, true),
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => [
                ['source' => 'node-1', 'target' => 'node-2'],
                ['source' => 'node-2', 'target' => 'node-3'],
                ['source' => 'node-3', 'target' => 'node-4'],
            ],
        ];
    }

    // ------------------------------------------------------------------
    //  Helper: diamond-shaped document
    //  node-1 → node-2 → node-4
    //  node-1 → node-3 → node-4
    // ------------------------------------------------------------------
    private function diamondDocument(): array
    {
        return [
            'nodes' => [
                ['id' => 'node-1', 'type' => 'a', 'disabled' => false],
                ['id' => 'node-2', 'type' => 'b', 'disabled' => false],
                ['id' => 'node-3', 'type' => 'c', 'disabled' => false],
                ['id' => 'node-4', 'type' => 'd', 'disabled' => false],
            ],
            'edges' => [
                ['source' => 'node-1', 'target' => 'node-2'],
                ['source' => 'node-1', 'target' => 'node-3'],
                ['source' => 'node-2', 'target' => 'node-4'],
                ['source' => 'node-3', 'target' => 'node-4'],
            ],
        ];
    }

    // ==================== RunWorkflow ====================

    #[Test]
    public function run_workflow_returns_all_nodes_in_topological_order(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunWorkflow);

        $this->assertInstanceOf(ExecutionPlan::class, $plan);
        $this->assertSame(['node-1', 'node-2', 'node-3', 'node-4'], $plan->orderedNodeIds);
        $this->assertSame([], $plan->skippedNodeIds);
        $this->assertSame(RunTrigger::RunWorkflow, $plan->trigger);
        $this->assertNull($plan->targetNodeId);
    }

    #[Test]
    public function run_workflow_with_diamond_graph_produces_valid_topological_order(): void
    {
        $plan = $this->planner->plan($this->diamondDocument(), RunTrigger::RunWorkflow);

        // node-1 must come first, node-4 must come last. node-2 and node-3 can be in either order.
        $this->assertSame('node-1', $plan->orderedNodeIds[0]);
        $this->assertSame('node-4', $plan->orderedNodeIds[3]);
        $this->assertCount(4, $plan->orderedNodeIds);
    }

    // ==================== RunNode ====================

    #[Test]
    public function run_node_returns_only_target_node(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunNode, 'node-2');

        $this->assertSame(['node-2'], $plan->orderedNodeIds);
        $this->assertSame([], $plan->skippedNodeIds);
        $this->assertSame(RunTrigger::RunNode, $plan->trigger);
        $this->assertSame('node-2', $plan->targetNodeId);
    }

    // ==================== RunFromHere ====================

    #[Test]
    public function run_from_here_returns_target_and_all_downstream(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunFromHere, 'node-2');

        $this->assertSame(['node-2', 'node-3', 'node-4'], $plan->orderedNodeIds);
        $this->assertSame([], $plan->skippedNodeIds);
        $this->assertSame('node-2', $plan->targetNodeId);
    }

    #[Test]
    public function run_from_here_on_last_node_returns_only_that_node(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunFromHere, 'node-4');

        $this->assertSame(['node-4'], $plan->orderedNodeIds);
    }

    #[Test]
    public function run_from_here_on_diamond_includes_both_branches(): void
    {
        $plan = $this->planner->plan($this->diamondDocument(), RunTrigger::RunFromHere, 'node-1');

        $this->assertCount(4, $plan->orderedNodeIds);
        $this->assertSame('node-1', $plan->orderedNodeIds[0]);
        $this->assertSame('node-4', $plan->orderedNodeIds[3]);
    }

    // ==================== RunUpToHere ====================

    #[Test]
    public function run_up_to_here_returns_target_and_all_upstream(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunUpToHere, 'node-3');

        $this->assertSame(['node-1', 'node-2', 'node-3'], $plan->orderedNodeIds);
        $this->assertSame([], $plan->skippedNodeIds);
        $this->assertSame('node-3', $plan->targetNodeId);
    }

    #[Test]
    public function run_up_to_here_on_first_node_returns_only_that_node(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunUpToHere, 'node-1');

        $this->assertSame(['node-1'], $plan->orderedNodeIds);
    }

    #[Test]
    public function run_up_to_here_on_diamond_includes_both_upstream_branches(): void
    {
        $plan = $this->planner->plan($this->diamondDocument(), RunTrigger::RunUpToHere, 'node-4');

        $this->assertCount(4, $plan->orderedNodeIds);
        $this->assertSame('node-1', $plan->orderedNodeIds[0]);
        $this->assertSame('node-4', $plan->orderedNodeIds[3]);
    }

    // ==================== Disabled node pruning ====================

    #[Test]
    public function disabled_nodes_are_pruned_to_skipped_ids(): void
    {
        $doc = $this->linearDocument(disabledIds: ['node-2']);

        $plan = $this->planner->plan($doc, RunTrigger::RunWorkflow);

        $this->assertSame(['node-1', 'node-3', 'node-4'], $plan->orderedNodeIds);
        $this->assertSame(['node-2'], $plan->skippedNodeIds);
    }

    #[Test]
    public function multiple_disabled_nodes_are_all_skipped(): void
    {
        $doc = $this->linearDocument(disabledIds: ['node-2', 'node-3']);

        $plan = $this->planner->plan($doc, RunTrigger::RunWorkflow);

        $this->assertSame(['node-1', 'node-4'], $plan->orderedNodeIds);
        $this->assertSame(['node-2', 'node-3'], $plan->skippedNodeIds);
    }

    #[Test]
    public function disabled_target_in_run_from_here_is_skipped(): void
    {
        $doc = $this->linearDocument(disabledIds: ['node-3']);

        $plan = $this->planner->plan($doc, RunTrigger::RunFromHere, 'node-2');

        $this->assertSame(['node-2', 'node-4'], $plan->orderedNodeIds);
        $this->assertSame(['node-3'], $plan->skippedNodeIds);
    }

    // ==================== Cycle detection ====================

    #[Test]
    public function cycle_in_graph_throws_runtime_exception(): void
    {
        $doc = [
            'nodes' => [
                ['id' => 'a', 'type' => 'x', 'disabled' => false],
                ['id' => 'b', 'type' => 'x', 'disabled' => false],
                ['id' => 'c', 'type' => 'x', 'disabled' => false],
            ],
            'edges' => [
                ['source' => 'a', 'target' => 'b'],
                ['source' => 'b', 'target' => 'c'],
                ['source' => 'c', 'target' => 'a'],  // cycle
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cycle detected in workflow graph');

        $this->planner->plan($doc, RunTrigger::RunWorkflow);
    }

    #[Test]
    public function cycle_in_scoped_subset_throws_runtime_exception(): void
    {
        // a → b → c → b (cycle between b and c), d is disconnected
        $doc = [
            'nodes' => [
                ['id' => 'a', 'type' => 'x', 'disabled' => false],
                ['id' => 'b', 'type' => 'x', 'disabled' => false],
                ['id' => 'c', 'type' => 'x', 'disabled' => false],
                ['id' => 'd', 'type' => 'x', 'disabled' => false],
            ],
            'edges' => [
                ['source' => 'a', 'target' => 'b'],
                ['source' => 'b', 'target' => 'c'],
                ['source' => 'c', 'target' => 'b'],  // cycle
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cycle detected in workflow graph');

        $this->planner->plan($doc, RunTrigger::RunFromHere, 'a');
    }

    // ==================== Edge cases ====================

    #[Test]
    public function empty_document_returns_empty_plan(): void
    {
        $plan = $this->planner->plan(['nodes' => [], 'edges' => []], RunTrigger::RunWorkflow);

        $this->assertSame([], $plan->orderedNodeIds);
        $this->assertSame([], $plan->skippedNodeIds);
    }

    #[Test]
    public function single_node_no_edges(): void
    {
        $doc = [
            'nodes' => [['id' => 'solo', 'type' => 'x', 'disabled' => false]],
            'edges' => [],
        ];

        $plan = $this->planner->plan($doc, RunTrigger::RunWorkflow);

        $this->assertSame(['solo'], $plan->orderedNodeIds);
    }

    #[Test]
    public function run_node_with_null_target_returns_empty(): void
    {
        $plan = $this->planner->plan($this->linearDocument(), RunTrigger::RunNode, null);

        $this->assertSame([], $plan->orderedNodeIds);
    }
}
