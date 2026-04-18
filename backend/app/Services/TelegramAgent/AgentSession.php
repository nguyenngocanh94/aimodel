<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use Carbon\CarbonImmutable;

/**
 * Mutable chat-scoped session for the TelegramAgent.
 *
 * One session per (chatId, botToken), stored in Redis at
 * telegram_agent:{chatId}:{botToken} with a 1-hour TTL.
 */
class AgentSession
{
    public string $chatId;
    public string $botToken;

    /**
     * List of Anthropic-wire-format message entries:
     *   [{role: 'user'|'assistant', content: string|array}, ...]
     */
    public array $messages;

    public CarbonImmutable $lastActiveAt;

    /** The workflow slug the agent is currently trying to run, if any. */
    public ?string $pendingWorkflowSlug;

    /** Accumulated parameters collected across conversation turns. */
    public array $collectedParams;

    public function __construct(
        string $chatId,
        string $botToken,
        array $messages = [],
        ?CarbonImmutable $lastActiveAt = null,
        ?string $pendingWorkflowSlug = null,
        array $collectedParams = [],
    ) {
        $this->chatId = $chatId;
        $this->botToken = $botToken;
        $this->messages = $messages;
        $this->lastActiveAt = $lastActiveAt ?? CarbonImmutable::now();
        $this->pendingWorkflowSlug = $pendingWorkflowSlug;
        $this->collectedParams = $collectedParams;
    }

    /**
     * Append a message in Anthropic wire format.
     *
     * @param  string  $role  'user' or 'assistant'
     * @param  string|array  $content  plain string or structured content blocks
     */
    public function appendMessage(string $role, mixed $content): void
    {
        $this->messages[] = ['role' => $role, 'content' => $content];
    }

    /**
     * Trim messages to the last $max entries, dropping the oldest.
     */
    public function trimMessages(int $max = 20): void
    {
        if (count($this->messages) > $max) {
            $this->messages = array_values(array_slice($this->messages, -$max));
        }
    }

    /**
     * Serialize to an array suitable for JSON encoding.
     */
    public function toArray(): array
    {
        return [
            'chatId'               => $this->chatId,
            'botToken'             => $this->botToken,
            'messages'             => $this->messages,
            'lastActiveAt'         => $this->lastActiveAt->toIso8601String(),
            'pendingWorkflowSlug'  => $this->pendingWorkflowSlug,
            'collectedParams'      => $this->collectedParams,
        ];
    }

    /**
     * Reconstruct an AgentSession from a previously serialized array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            chatId: $data['chatId'],
            botToken: $data['botToken'],
            messages: $data['messages'] ?? [],
            lastActiveAt: CarbonImmutable::parse($data['lastActiveAt']),
            pendingWorkflowSlug: $data['pendingWorkflowSlug'] ?? null,
            collectedParams: $data['collectedParams'] ?? [],
        );
    }
}
