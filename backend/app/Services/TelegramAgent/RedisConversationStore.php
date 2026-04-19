<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Redis-backed ConversationStore for TelegramAgent.
 *
 * Key layout:
 *   ai_convo:latest:{userId}  → string (conversationId)
 *   ai_convo:{conversationId} → JSON array of message objects
 *
 * TTL: 3600 s (refreshed on every write).
 */
final class RedisConversationStore implements ConversationStore
{
    private const TTL = 3600;
    private const MAX_MESSAGES = 100;
    private const PREFIX = 'ai_convo';

    public function latestConversationId(string|int $userId): ?string
    {
        $val = Redis::get(self::PREFIX . ":latest:{$userId}");
        return ($val === null || $val === false) ? null : (string) $val;
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        $id = (string) Str::uuid7();
        Redis::set(self::PREFIX . ":latest:{$userId}", $id, 'EX', self::TTL);
        Redis::set(self::PREFIX . ":{$id}", json_encode([]), 'EX', self::TTL);
        return $id;
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        $this->appendMessage($conversationId, ['role' => 'user', 'content' => $prompt->prompt]);
        return (string) Str::uuid7();
    }

    public function storeAssistantMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt, AgentResponse $response): string
    {
        $this->appendMessage($conversationId, ['role' => 'assistant', 'content' => $response->text]);
        return (string) Str::uuid7();
    }

    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        $raw = Redis::get(self::PREFIX . ":{$conversationId}");
        if ($raw === null || $raw === false) {
            return new Collection;
        }

        $all = json_decode($raw, true) ?? [];
        $kept = array_slice($all, -$limit);

        return (new Collection($kept))->map(function (array $msg): Message {
            return $msg['role'] === 'assistant'
                ? new AssistantMessage($msg['content'] ?? '')
                : new Message($msg['role'], $msg['content'] ?? '');
        });
    }

    /**
     * Delete all conversation data for the given userId (used by /reset).
     */
    public function forgetUser(string|int $userId): void
    {
        $conversationId = $this->latestConversationId($userId);
        if ($conversationId !== null) {
            Redis::del(self::PREFIX . ":{$conversationId}");
        }
        Redis::del(self::PREFIX . ":latest:{$userId}");
    }

    private function appendMessage(string $conversationId, array $message): void
    {
        $key = self::PREFIX . ":{$conversationId}";
        $raw = Redis::get($key);
        $messages = ($raw !== null && $raw !== false) ? (json_decode($raw, true) ?? []) : [];
        $messages[] = $message;

        if (count($messages) > self::MAX_MESSAGES) {
            $messages = array_slice($messages, -self::MAX_MESSAGES);
        }

        Redis::set($key, json_encode($messages), 'EX', self::TTL);

        // Refresh the latest pointer TTL too.
        $latestKey = self::PREFIX . ':latest:*';
        // We don't know the userId here; the TTL is already set on storeConversation.
        // The conversation key itself is refreshed above.
    }
}
