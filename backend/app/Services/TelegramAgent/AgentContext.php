<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

final readonly class AgentContext
{
    public function __construct(
        public string $chatId,
        public ?string $userId,
        public string $sessionId,
        public string $botToken,
    ) {}
}
