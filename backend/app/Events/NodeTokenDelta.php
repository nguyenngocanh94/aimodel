<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast on `run.{runId}` every time a streaming text-gen node emits a
 * token. The run-stream SSE controller forwards any Redis pub/sub message
 * on the channel, so the frontend sees `event: node.token.delta` frames
 * interleaved with `node.status` without any controller changes (LP-C2).
 *
 * `seq` is a per-(runId, nodeId) monotonically increasing counter so the
 * frontend can reorder out-of-order frames (the SSE forwarder preserves
 * order, but Redis pub/sub on a shared channel does not guarantee it under
 * concurrent writers).
 */
final class NodeTokenDelta implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly string $runId,
        public readonly string $nodeId,
        public readonly string $messageId,
        public readonly string $delta,
        public readonly int $seq,
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel("run.{$this->runId}")];
    }

    public function broadcastAs(): string
    {
        return 'node.token.delta';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'runId' => $this->runId,
            'nodeId' => $this->nodeId,
            'messageId' => $this->messageId,
            'delta' => $this->delta,
            'seq' => $this->seq,
        ];
    }
}
