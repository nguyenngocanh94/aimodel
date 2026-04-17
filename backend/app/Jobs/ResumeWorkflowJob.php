<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Execution\RunExecutor;
use App\Domain\Nodes\HumanResponse;
use App\Models\ExecutionRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResumeWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public readonly string $runId,
        public readonly string $nodeId,
        public readonly array $responseData,
    ) {
        $this->onQueue('workflow-runs');
    }

    public function handle(RunExecutor $executor): void
    {
        $response = HumanResponse::fromArray($this->responseData);
        $executor->resume($this->runId, $this->nodeId, $response);
    }

    public function failed(\Throwable $exception): void
    {
        $run = ExecutionRun::find($this->runId);

        if ($run !== null) {
            $run->update([
                'status' => 'error',
                'termination_reason' => 'resumeJobFailed',
                'completed_at' => now(),
            ]);
        }
    }
}
