<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\Tools\ReplyTool;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReplyToolTest extends TestCase
{
    private const TELEGRAM_BASE = 'https://api.telegram.org/bot*';
    private const BOT_TOKEN = 'bot123:test-token';

    private AgentContext $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = new AgentContext(
            chatId: '999888',
            userId: 'user-1',
            sessionId: 'session-abc',
            botToken: self::BOT_TOKEN,
        );
    }

    #[Test]
    public function definition_returns_correct_tool_definition(): void
    {
        $tool = new ReplyTool();
        $def = $tool->definition();

        $this->assertSame('reply', $def->name);
        $this->assertStringContainsString('Telegram', $def->description);
    }

    #[Test]
    public function successful_send_returns_delivered_true(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tool = new ReplyTool();
        $result = $tool->execute(['text' => 'Hello, user!'], $this->ctx);

        $this->assertTrue($result['delivered']);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function successful_send_hits_correct_telegram_endpoint_with_correct_body(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $tool = new ReplyTool();
        $tool->execute(['text' => 'Hello, user!'], $this->ctx);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body = $request->data();
            $expectedUrl = 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';

            return $request->url() === $expectedUrl
                && ($body['chat_id'] ?? '') === '999888'
                && ($body['text'] ?? '') === 'Hello, user!'
                && ($body['parse_mode'] ?? '') === 'Markdown';
        });
    }

    #[Test]
    public function non_2xx_response_returns_delivered_false(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $tool = new ReplyTool();
        $result = $tool->execute(['text' => 'Hello'], $this->ctx);

        $this->assertFalse($result['delivered']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function long_text_is_truncated_to_4096_chars(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $longText = str_repeat('a', 5000);

        $tool = new ReplyTool();
        $tool->execute(['text' => $longText], $this->ctx);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body = $request->data();
            return mb_strlen((string) ($body['text'] ?? '')) === 4096;
        });
    }

    #[Test]
    public function network_exception_returns_delivered_false(): void
    {
        Http::fake([
            'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage' => function () {
                throw new \RuntimeException('Connection refused');
            },
        ]);

        $tool = new ReplyTool();
        $result = $tool->execute(['text' => 'Hello'], $this->ctx);

        $this->assertFalse($result['delivered']);
        $this->assertArrayHasKey('error', $result);
    }
}
