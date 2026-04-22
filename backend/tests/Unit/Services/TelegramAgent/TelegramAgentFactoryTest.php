<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\TelegramAgentFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TG-01: TelegramAgentFactory returns a fresh TelegramAgent per call.
 */
final class TelegramAgentFactoryTest extends TestCase
{
    #[Test]
    public function factory_returns_a_telegram_agent_instance(): void
    {
        $factory = app(TelegramAgentFactory::class);

        $agent = $factory->make('chat-123', 'bot-token-abc');

        $this->assertInstanceOf(TelegramAgent::class, $agent);
    }

    #[Test]
    public function factory_sets_chat_id_and_bot_token(): void
    {
        $factory = app(TelegramAgentFactory::class);

        $agent = $factory->make('chat-456', 'bot-token-xyz');

        $this->assertSame('chat-456', $agent->chatId);
        $this->assertSame('bot-token-xyz', $agent->botToken);
    }

    #[Test]
    public function factory_returns_fresh_instance_per_call(): void
    {
        $factory = app(TelegramAgentFactory::class);

        $agentA = $factory->make('chat-1', 'token-1');
        $agentB = $factory->make('chat-2', 'token-2');

        $this->assertNotSame($agentA, $agentB);
        $this->assertSame('chat-1', $agentA->chatId);
        $this->assertSame('chat-2', $agentB->chatId);
    }
}
