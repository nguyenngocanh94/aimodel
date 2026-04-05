<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunListTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(): Workflow
    {
        return Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);
    }

    public function test_returns_runs_for_workflow(): void
    {
        $workflow = $this->createWorkflow();

        ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'success',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
        ]);

        ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runNode',
            'target_node_id' => 'n1',
            'status' => 'error',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now()->subMinutes(4),
        ]);

        $response = $this->getJson("/api/workflows/{$workflow->id}/runs");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.status', 'error'); // Most recent first
        $response->assertJsonPath('data.1.status', 'success');
    }

    public function test_returns_empty_for_workflow_with_no_runs(): void
    {
        $workflow = $this->createWorkflow();

        $response = $this->getJson("/api/workflows/{$workflow->id}/runs");

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_does_not_include_runs_from_other_workflows(): void
    {
        $workflow1 = $this->createWorkflow();
        $workflow2 = $this->createWorkflow();

        ExecutionRun::create([
            'workflow_id' => $workflow1->id,
            'trigger' => 'runWorkflow',
            'status' => 'success',
        ]);

        ExecutionRun::create([
            'workflow_id' => $workflow2->id,
            'trigger' => 'runWorkflow',
            'status' => 'error',
        ]);

        $response = $this->getJson("/api/workflows/{$workflow1->id}/runs");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.workflowId', $workflow1->id);
    }

    public function test_includes_summary_stats(): void
    {
        $workflow = $this->createWorkflow();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'error',
            'started_at' => now(),
        ]);

        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n1', 'status' => 'success']);
        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n2', 'status' => 'success']);
        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n3', 'status' => 'error']);
        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n4', 'status' => 'skipped']);

        $response = $this->getJson("/api/workflows/{$workflow->id}/runs");

        $response->assertOk();
        $response->assertJsonPath('data.0.summary.successCount', 2);
        $response->assertJsonPath('data.0.summary.errorCount', 1);
        $response->assertJsonPath('data.0.summary.skippedCount', 1);
        $response->assertJsonPath('data.0.summary.totalCount', 4);
    }

    public function test_does_not_include_node_run_records(): void
    {
        $workflow = $this->createWorkflow();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'success',
        ]);

        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n1', 'status' => 'success']);

        $response = $this->getJson("/api/workflows/{$workflow->id}/runs");

        $response->assertOk();
        // nodeRunRecords should not be present in list endpoint
        $this->assertArrayNotHasKey('nodeRunRecords', $response->json('data.0'));
    }
}
