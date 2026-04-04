<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FullRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createWorkflowWithPipeline(): Workflow
    {
        return Workflow::create([
            'name' => 'Test Pipeline',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => ['prompt' => 'Create a video about AI']],
                    ['id' => 'n2', 'type' => 'scriptWriter', 'config' => ['provider' => 'stub']],
                    ['id' => 'n3', 'type' => 'sceneSplitter', 'config' => ['provider' => 'stub']],
                ],
                'edges' => [
                    ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'prompt', 'targetHandle' => 'prompt'],
                    ['source' => 'n2', 'target' => 'n3', 'sourceHandle' => 'script', 'targetHandle' => 'script'],
                ],
            ],
        ]);
    }

    #[Test]
    public function full_run_creates_all_node_records(): void
    {
        $workflow = $this->createWorkflowWithPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $workflow->document,
        ]);

        $executor = app(\App\Domain\Execution\RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $this->assertSame('success', $run->status);
        $this->assertNotNull($run->completed_at);

        $records = $run->nodeRunRecords;
        $this->assertCount(3, $records);

        foreach ($records as $record) {
            $this->assertContains($record->status, ['success', 'skipped']);
        }
    }

    #[Test]
    public function api_trigger_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        $workflow = $this->createWorkflowWithPipeline();

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'runWorkflow',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(RunWorkflowJob::class);
    }

    #[Test]
    public function run_node_trigger_executes_only_target(): void
    {
        $workflow = $this->createWorkflowWithPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runNode',
            'target_node_id' => 'n1',
            'document_snapshot' => $workflow->document,
        ]);

        $executor = app(\App\Domain\Execution\RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $records = $run->nodeRunRecords;

        // RunNode only executes the target node
        $this->assertCount(1, $records);
        $this->assertSame('n1', $records->first()->node_id);
    }

    #[Test]
    public function disabled_node_is_skipped(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => ['prompt' => 'test'], 'disabled' => true],
                ],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $workflow->document,
        ]);

        $executor = app(\App\Domain\Execution\RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $record = $run->nodeRunRecords->first();

        $this->assertSame('skipped', $record->status);
        $this->assertSame('disabled', $record->skip_reason);
    }

    #[Test]
    public function cache_hit_on_second_run(): void
    {
        $workflow = Workflow::create([
            'name' => 'Cache Test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => ['prompt' => 'cache test']],
                ],
                'edges' => [],
            ],
        ]);

        $executor = app(\App\Domain\Execution\RunExecutor::class);

        // First run
        $run1 = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $workflow->document,
        ]);
        $executor->execute($run1);

        // Second run with same config
        $run2 = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $workflow->document,
        ]);
        $executor->execute($run2);

        $run2->refresh();
        $record = $run2->nodeRunRecords->first();

        $this->assertTrue($record->used_cache);
    }

    #[Test]
    public function get_run_shows_node_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'userPrompt', 'config' => ['prompt' => 'view test']],
                ],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $workflow->document,
        ]);

        $executor = app(\App\Domain\Execution\RunExecutor::class);
        $executor->execute($run);

        $response = $this->getJson("/api/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonCount(1, 'data.nodeRunRecords');
    }
}
