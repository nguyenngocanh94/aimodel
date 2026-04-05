<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Execution\RunExecutor;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReviewCheckpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createWorkflowWithReview(): Workflow
    {
        return Workflow::create([
            'name' => 'Review Test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => ['prompt' => 'test prompt']],
                    ['id' => 'n2', 'type' => 'reviewCheckpoint', 'config' => ['approved' => false]],
                ],
                'edges' => [
                    ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'prompt', 'targetHandle' => 'data'],
                ],
            ],
        ]);
    }

    #[Test]
    public function review_endpoint_approves_node(): void
    {
        $workflow = $this->createWorkflowWithReview();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        $record = NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'approve',
            'notes' => 'Looks good!',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'approve')
            ->assertJsonPath('nodeId', 'n2');

        $record->refresh();
        $this->assertSame('success', $record->status);
        $this->assertSame('approve', $record->output_payloads['decision']);
        $this->assertNotNull($record->completed_at);
    }

    #[Test]
    public function review_endpoint_rejects_node(): void
    {
        $workflow = $this->createWorkflowWithReview();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'reject',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'reject');

        $record = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n2')->first();
        $this->assertSame('error', $record->status);
    }

    #[Test]
    public function review_rejects_when_run_not_awaiting(): void
    {
        $workflow = $this->createWorkflowWithReview();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'approve',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Run is not awaiting review');
    }

    #[Test]
    public function review_rejects_invalid_decision(): void
    {
        $workflow = $this->createWorkflowWithReview();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'maybe',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['decision']);
    }

    #[Test]
    public function cancel_during_awaiting_review(): void
    {
        $workflow = $this->createWorkflowWithReview();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
            'started_at' => now(),
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/cancel");

        $response->assertOk();

        $run->refresh();
        $this->assertSame('cancelled', $run->status);
        $this->assertSame('userCancelled', $run->termination_reason);

        $record = NodeRunRecord::where('run_id', $run->id)->where('node_id', 'n2')->first();
        $this->assertSame('cancelled', $record->status);
    }
}
