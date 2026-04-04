<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Execution\RunExecutor;
use App\Domain\RunStatus;
use App\Models\ExecutionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public readonly string $runId,
    ) {
        $this->onQueue('workflow-runs');
    }

    public function handle(RunExecutor $executor): void
    {
        $run = ExecutionRun::findOrFail($this->runId);
        $executor->execute($run);
    }

    public function failed(\Throwable $exception): void
    {
        $run = ExecutionRun::find($this->runId);

        if ($run !== null) {
            $run->update([
                'status' => RunStatus::Interrupted->value,
                'termination_reason' => 'jobFailed',
                'completed_at' => now(),
            ]);
        }
    }
}
