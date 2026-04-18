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
     *
     * Tool-use/tool-result come in pairs (assistant.tool_use → user.tool_result).
     * A naive tail-slice can cut through a pair and leave a tool_result message
     * at the head with no preceding assistant tool_call, which OpenAI-compatible
     * providers (Fireworks etc.) reject at the template-rendering step. After
     * slicing we walk forward past any orphan tool_result user messages so the
     * kept window always starts on a clean boundary.
     */
    public function trimMessages(int $max = 20): void
    {
        if (count($this->messages) <= $max) {
            return;
        }

        $kept = array_values(array_slice($this->messages, -$max));

        while ($kept !== [] && $this->isOrphanToolResult($kept[0])) {
            array_shift($kept);
        }

        $this->messages = $kept;
    }

    /**
     * A user message whose content is an array whose first block is a tool_result
     * is a "tool_result user message" — it must be preceded by an assistant tool_use
     * or it's an orphan.
     *
     * @param  array{role: string, content: mixed}  $msg
     */
    private function isOrphanToolResult(array $msg): bool
    {
        if (($msg['role'] ?? null) !== 'user') {
            return false;
        }

        $content = $msg['content'] ?? null;
        if (! is_array($content) || $content === []) {
            return false;
        }

        $first = $content[0] ?? null;
        return is_array($first) && ($first['type'] ?? null) === 'tool_result';
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
