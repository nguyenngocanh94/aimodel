<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RunCompleted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $runId,
        public readonly string $status,
        public readonly ?string $terminationReason = null,
        public readonly ?string $completedAt = null,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("run.{$this->runId}")];
    }

    public function broadcastAs(): string
    {
        return 'run.completed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'runId' => $this->runId,
            'status' => $this->status,
            'terminationReason' => $this->terminationReason,
            'completedAt' => $this->completedAt,
        ];
    }
}
