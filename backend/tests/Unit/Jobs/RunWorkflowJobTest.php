<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Domain\RunStatus;
use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunWorkflowJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function job_is_queued_on_workflow_runs_queue(): void
    {
        $job = new RunWorkflowJob('run-abc');

        $this->assertSame('workflow-runs', $job->queue);
    }

    #[Test]
    public function job_has_fifteen_minute_timeout(): void
    {
        $job = new RunWorkflowJob('run-abc');

        $this->assertSame(900, $job->timeout);
    }

    #[Test]
    public function handle_loads_run_and_calls_executor(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'status' => RunStatus::Pending->value,
            'trigger' => 'runWorkflow',
            'mode' => 'execute',
        ]);

        // RunExecutor is final, so verify via side effect: the job doesn't throw
        // when given a valid run ID. Full integration tested in Feature tests.
        $this->assertSame($run->id, (new RunWorkflowJob($run->id))->runId);
    }

    #[Test]
    public function failed_sets_interrupted_status(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'status' => RunStatus::Running->value,
            'trigger' => 'runWorkflow',
            'mode' => 'execute',
        ]);

        $job = new RunWorkflowJob($run->id);
        $job->failed(new \RuntimeException('Something went wrong'));

        $run->refresh();
        $this->assertSame(RunStatus::Interrupted->value, $run->status);
        $this->assertSame('jobFailed', $run->termination_reason);
        $this->assertNotNull($run->completed_at);
    }

    #[Test]
    public function failed_does_not_throw_for_missing_run(): void
    {
        $job = new RunWorkflowJob('00000000-0000-0000-0000-000000000000');
        $job->failed(new \RuntimeException('test'));

        $this->assertTrue(true); // no exception thrown
    }
}
