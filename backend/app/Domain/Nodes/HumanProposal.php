<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

/**
 * Returned by a node's propose() or handleResponse() to request human input.
 * The executor sends this to the appropriate channel and pauses execution.
 */
readonly class HumanProposal
{
    /**
     * @param string $message Human-readable message to display
     * @param string $channel Delivery channel: telegram, ui, mcp
     * @param array<string, mixed> $payload Structured data (versions, options, etc.)
     * @param array<string, mixed> $state Serializable node state for next handleResponse call
     */
    public function __construct(
        public string $message,
        public string $channel = 'telegram',
        public array $payload = [],
        public array $state = [],
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'channel' => $this->channel,
            'payload' => $this->payload,
            'state' => $this->state,
        ];
    }
}
