<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

/**
 * In-memory ConversationStore for PHPUnit (no Redis required).
 */
final class MemoryConversationStore implements ConversationStore
{
    /** @var array<string, string> */
    private array $latestByUser = [];

    /** @var array<string, list<array{role: string, content: string}>> */
    private array $conversations = [];

    public function latestConversationId(string|int $userId): ?string
    {
        return $this->latestByUser[(string) $userId] ?? null;
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        $id = (string) Str::uuid7();
        $this->latestByUser[(string) $userId] = $id;
        $this->conversations[$id] = [];

        return $id;
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        $this->append($conversationId, ['role' => 'user', 'content' => $prompt->prompt]);

        return (string) Str::uuid7();
    }

    public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
    {
        $this->append($conversationId, ['role' => 'assistant', 'content' => $response->text]);

        return (string) Str::uuid7();
    }

    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        $all  = $this->conversations[$conversationId] ?? [];
        $kept = array_slice($all, -$limit);

        return (new Collection($kept))->map(function (array $msg): Message {
            return $msg['role'] === 'assistant'
                ? new AssistantMessage($msg['content'] ?? '')
                : new Message($msg['role'], $msg['content'] ?? '');
        });
    }

    public function forgetUser(string|int $userId): void
    {
        $cid = $this->latestByUser[(string) $userId] ?? null;
        unset($this->latestByUser[(string) $userId]);
        if ($cid !== null) {
            unset($this->conversations[$cid]);
        }
    }

    /** @param array{role: string, content: string} $message */
    private function append(string $conversationId, array $message): void
    {
        $this->conversations[$conversationId]   ??= [];
        $this->conversations[$conversationId][] = $message;
    }
}
