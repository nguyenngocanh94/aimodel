<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed persistence for {@see AgentSession}.
 *
 * Key layout:
 *   ai_session:{chatId}:{botToken} → JSON blob of AgentSession::toArray()
 *
 * TTL: 3600 s (refreshed on every save). Independent from the laravel/ai
 * conversation history store so message replay + session state can evolve
 * separately.
 */
final class AgentSessionStore
{
    private const TTL = 3600;
    private const PREFIX = 'ai_session';

    public function load(string $chatId, string $botToken): AgentSession
    {
        $key = $this->key($chatId, $botToken);
        $raw = Redis::get($key);

        if ($raw === null || $raw === false) {
            return new AgentSession(chatId: $chatId, botToken: $botToken);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return new AgentSession(chatId: $chatId, botToken: $botToken);
        }

        return AgentSession::fromArray($data, $chatId, $botToken);
    }

    public function save(AgentSession $session): void
    {
        Redis::set(
            $this->key($session->chatId, $session->botToken),
            json_encode($session->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'EX',
            self::TTL,
        );
    }

    public function forget(string $chatId, string $botToken): void
    {
        Redis::del($this->key($chatId, $botToken));
    }

    private function key(string $chatId, string $botToken): string
    {
        return self::PREFIX . ":{$chatId}:{$botToken}";
    }
}
