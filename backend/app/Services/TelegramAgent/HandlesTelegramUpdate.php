<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

interface HandlesTelegramUpdate
{
    /**
     * Handle an inbound Telegram update.
     *
     * @param  array<string, mixed>  $update    Raw decoded Telegram update object.
     * @param  string                $botToken  Bot token for Telegram API calls and session key.
     */
    public function handle(array $update, string $botToken): void;
}
