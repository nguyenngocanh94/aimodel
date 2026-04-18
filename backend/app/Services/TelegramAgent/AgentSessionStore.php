<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed persistence for TelegramAgent sessions.
 *
 * Key format : telegram_agent:{chatId}:{botToken}
 * TTL        : 3600 seconds (1 hour), refreshed on every save
 * Retention  : messages are trimmed to the last 20 entries on save
 */
class AgentSessionStore
{
    private const KEY_PREFIX = 'telegram_agent';
    private const TTL        = 3600;
    private const MAX_MESSAGES = 20;

    private function key(string $chatId, string $botToken): string
    {
        return self::KEY_PREFIX . ":{$chatId}:{$botToken}";
    }

    /**
     * Load an existing session from Redis, or return a fresh empty one.
     */
    public function load(string $chatId, string $botToken): AgentSession
    {
        $raw = Redis::get($this->key($chatId, $botToken));

        if ($raw === null || $raw === false) {
            return new AgentSession(
                chatId: $chatId,
                botToken: $botToken,
            );
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return new AgentSession(chatId: $chatId, botToken: $botToken);
        }

        return AgentSession::fromArray($data);
    }

    /**
     * Persist a session to Redis.
     * Trims messages to the last 20 and refreshes lastActiveAt before writing.
     */
    public function save(AgentSession $session): void
    {
        $session->trimMessages(self::MAX_MESSAGES);
        $session->lastActiveAt = CarbonImmutable::now();

        $json = json_encode($session->toArray(), JSON_THROW_ON_ERROR);

        Redis::set($this->key($session->chatId, $session->botToken), $json, 'EX', self::TTL);
    }

    /**
     * Delete a session key from Redis.
     * A subsequent load() for the same (chatId, botToken) will return a fresh empty session.
     */
    public function forget(string $chatId, string $botToken): void
    {
        Redis::del($this->key($chatId, $botToken));
    }
}
