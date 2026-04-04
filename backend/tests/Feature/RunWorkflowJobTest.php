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

final class RunWorkflowJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_can_be_dispatched(): void
    {
        Queue::fake();

        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
        ]);

        RunWorkflowJob::dispatch($run->id);

        Queue::assertPushed(RunWorkflowJob::class, function ($job) use ($run) {
            return $job->runId === $run->id && $job->queue === 'workflow-runs';
        });
    }

    #[Test]
    public function job_executes_run(): void
    {
        Storage::fake('local');

        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => ['nodes' => [], 'edges' => []],
        ]);

        $job = new RunWorkflowJob($run->id);
        $job->handle(app(\App\Domain\Execution\RunExecutor::class));

        $run->refresh();
        $this->assertSame('success', $run->status);
    }

    #[Test]
    public function failed_sets_interrupted_status(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $job = new RunWorkflowJob($run->id);
        $job->failed(new \RuntimeException('Queue timeout'));

        $run->refresh();
        $this->assertSame('interrupted', $run->status);
        $this->assertSame('jobFailed', $run->termination_reason);
        $this->assertNotNull($run->completed_at);
    }
}
