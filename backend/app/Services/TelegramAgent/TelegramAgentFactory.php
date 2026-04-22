<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Services\TelegramAgent\SlashCommandRouter;

/**
 * Thin per-request factory for {@see TelegramAgent}.
 *
 * NOT a singleton — each call to make() returns a fresh TelegramAgent
 * instance so that chatId and botToken stay isolated per webhook hit.
 */
final class TelegramAgentFactory
{
    public function make(string $chatId, string $botToken): TelegramAgent
    {
        return app()->make(TelegramAgent::class, [
            'chatId'      => $chatId,
            'botToken'    => $botToken,
            'slashRouter' => app(SlashCommandRouter::class),
        ]);
    }
}
