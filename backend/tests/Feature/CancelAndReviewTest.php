<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\RunCompleted;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CancelAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflowWithRun(string $runStatus = 'running'): array
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => [], 'position' => ['x' => 0, 'y' => 0]],
                    ['id' => 'n2', 'type' => 'scriptWriter', 'config' => [], 'position' => ['x' => 300, 'y' => 0]],
                ],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => $runStatus,
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        return [$workflow, $run];
    }

    // --- Cancel ---

    public function test_cancel_running_run_sets_cancelled_status(): void
    {
        Event::fake();
        [$workflow, $run] = $this->createWorkflowWithRun('running');

        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n1', 'status' => 'success']);
        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n2', 'status' => 'pending']);

        $response = $this->postJson("/api/runs/{$run->id}/cancel");

        $response->assertOk();

        $run->refresh();
        $this->assertSame('cancelled', $run->status);
        $this->assertSame('userCancelled', $run->termination_reason);

        Event::assertDispatched(RunCompleted::class, function (RunCompleted $e) use ($run) {
            return $e->runId === $run->id && $e->status === 'cancelled';
        });
    }

    public function test_cancel_marks_pending_nodes_as_cancelled(): void
    {
        Event::fake();
        [$workflow, $run] = $this->createWorkflowWithRun('running');

        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n1', 'status' => 'success']);
        NodeRunRecord::create(['run_id' => $run->id, 'node_id' => 'n2', 'status' => 'pending']);

        $this->postJson("/api/runs/{$run->id}/cancel");

        $n2 = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n2')->first();
        $this->assertSame('cancelled', $n2->status);

        // Already completed node should not be changed
        $n1 = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n1')->first();
        $this->assertSame('success', $n1->status);
    }

    public function test_cancel_completed_run_returns_422(): void
    {
        [$workflow, $run] = $this->createWorkflowWithRun('success');

        $response = $this->postJson("/api/runs/{$run->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_cancel_awaiting_review_run_succeeds(): void
    {
        Event::fake();
        [$workflow, $run] = $this->createWorkflowWithRun('awaitingReview');

        $response = $this->postJson("/api/runs/{$run->id}/cancel");

        $response->assertOk();
        $run->refresh();
        $this->assertSame('cancelled', $run->status);
    }

    // --- Review ---

    public function test_review_approve_updates_node_record(): void
    {
        [$workflow, $run] = $this->createWorkflowWithRun('awaitingReview');

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n1',
            'status' => 'awaitingReview',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'approve',
            'notes' => 'Looks good',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['decision' => 'approve']);

        $record = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n1')->first();
        $this->assertSame('success', $record->status);
        $this->assertSame('approve', $record->output_payloads['decision']);
        $this->assertSame('Looks good', $record->output_payloads['notes']);
    }

    public function test_review_reject_sets_error_status(): void
    {
        [$workflow, $run] = $this->createWorkflowWithRun('awaitingReview');

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n1',
            'status' => 'awaitingReview',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'reject',
        ]);

        $response->assertOk();

        $record = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n1')->first();
        $this->assertSame('error', $record->status);
    }

    public function test_review_non_awaiting_run_returns_422(): void
    {
        [$workflow, $run] = $this->createWorkflowWithRun('running');

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'approve',
        ]);

        $response->assertStatus(422);
    }

    public function test_review_invalid_decision_returns_422(): void
    {
        [$workflow, $run] = $this->createWorkflowWithRun('awaitingReview');

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'maybe',
        ]);

        $response->assertStatus(422);
    }
}
