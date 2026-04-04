<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function trigger_run_returns_202(): void
    {
        Queue::fake();

        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => [
                'nodes' => [['id' => 'n1', 'type' => 'test', 'config' => ['key' => 'val']]],
                'edges' => [],
            ],
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'runWorkflow',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => ['id', 'workflowId', 'trigger', 'status'],
            ])
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.trigger', 'runWorkflow');

        Queue::assertPushed(RunWorkflowJob::class);

        $this->assertDatabaseHas('execution_runs', [
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function trigger_run_with_target_node(): void
    {
        Queue::fake();

        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [['id' => 'n1', 'type' => 'test']], 'edges' => []],
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'runFromHere',
            'targetNodeId' => 'n1',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.targetNodeId', 'n1');
    }

    #[Test]
    public function invalid_trigger_returns_422(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'invalidTrigger',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trigger']);
    }

    #[Test]
    public function show_run_with_node_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'success',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n1',
            'status' => 'success',
        ]);

        $response = $this->getJson("/api/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonCount(1, 'data.nodeRunRecords');
    }

    #[Test]
    public function show_returns_404_for_missing_run(): void
    {
        $response = $this->getJson('/api/runs/00000000-0000-0000-0000-000000000000');
        $response->assertNotFound();
    }
}
