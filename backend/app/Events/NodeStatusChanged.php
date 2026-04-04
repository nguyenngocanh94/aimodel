<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class NodeStatusChanged implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $runId,
        public readonly string $nodeId,
        public readonly string $status,
        public readonly ?array $outputPayloads = null,
        public readonly ?int $durationMs = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $skipReason = null,
        public readonly bool $usedCache = false,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("run.{$this->runId}")];
    }

    public function broadcastAs(): string
    {
        return 'node.status';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'runId' => $this->runId,
            'nodeId' => $this->nodeId,
            'status' => $this->status,
            'outputPayloads' => $this->outputPayloads,
            'durationMs' => $this->durationMs,
            'errorMessage' => $this->errorMessage,
            'skipReason' => $this->skipReason,
            'usedCache' => $this->usedCache,
        ];
    }
}
