<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Services\TelegramAgent\Tools\ReplyTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReplyToolTest extends TestCase
{
    private const BOT_TOKEN = 'bot123:test-token';
    private const CHAT_ID   = '999888';

    private function makeTool(): ReplyTool
    {
        return new ReplyTool(
            botToken: self::BOT_TOKEN,
            chatId: self::CHAT_ID,
        );
    }

    #[Test]
    public function description_returns_non_empty_string_mentioning_telegram(): void
    {
        $tool = $this->makeTool();

        $this->assertNotEmpty($tool->description());
        $this->assertStringContainsString('Telegram', $tool->description());
    }

    #[Test]
    public function schema_has_text_string_required(): void
    {
        $schema = $this->makeTool()->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('text', $schema);
        $this->assertInstanceOf(StringType::class, $schema['text']);
    }

    #[Test]
    public function successful_send_returns_delivered_true(): void
    {
        Http::fake([
            'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $result = json_decode($this->makeTool()->handle(new Request(['text' => 'Hello, user!'])), true);

        $this->assertTrue($result['delivered']);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function successful_send_hits_correct_telegram_endpoint_with_correct_body(): void
    {
        Http::fake([
            'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $this->makeTool()->handle(new Request(['text' => 'Hello, user!']));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body        = $request->data();
            $expectedUrl = 'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage';

            return $request->url() === $expectedUrl
                && ($body['chat_id'] ?? '') === self::CHAT_ID
                && ($body['text'] ?? '') === 'Hello, user!'
                && ($body['parse_mode'] ?? '') === 'Markdown';
        });
    }

    #[Test]
    public function non_2xx_response_returns_delivered_false(): void
    {
        Http::fake([
            'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $result = json_decode($this->makeTool()->handle(new Request(['text' => 'Hello'])), true);

        $this->assertFalse($result['delivered']);
        $this->assertArrayHasKey('error', $result);
    }

    #[Test]
    public function long_text_is_truncated_to_4096_chars(): void
    {
        Http::fake([
            'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage' => Http::response(['ok' => true], 200),
        ]);

        $longText = str_repeat('a', 5000);

        $this->makeTool()->handle(new Request(['text' => $longText]));

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            $body = $request->data();

            return mb_strlen((string) ($body['text'] ?? '')) === 4096;
        });
    }

    #[Test]
    public function network_exception_returns_delivered_false(): void
    {
        Http::fake([
            'https://api.telegram.org/bot'.self::BOT_TOKEN.'/sendMessage' => function () {
                throw new \RuntimeException('Connection refused');
            },
        ]);

        $result = json_decode($this->makeTool()->handle(new Request(['text' => 'Hello'])), true);

        $this->assertFalse($result['delivered']);
        $this->assertArrayHasKey('error', $result);
    }
}
