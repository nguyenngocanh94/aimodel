<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RunStarted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $runId,
        public readonly string $status,
        public readonly array $plannedNodeIds,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("run.{$this->runId}")];
    }

    public function broadcastAs(): string
    {
        return 'run.started';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'runId' => $this->runId,
            'status' => $this->status,
            'plannedNodeIds' => $this->plannedNodeIds,
        ];
    }
}
