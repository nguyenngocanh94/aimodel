<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ReplyTool implements Tool
{
    public function __construct(
        public readonly string $botToken,
        public readonly string $chatId,
    ) {}

    public function description(): string
    {
        return 'Send a text message back to the user on Telegram. Call this when you want to talk to the user.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'text' => $schema->string()->description('The message text to send to the user')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $text = mb_substr((string) $request->string('text', ''), 0, 4096);

        try {
            $response = Http::post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'chat_id'    => $this->chatId,
                    'text'       => $text,
                    'parse_mode' => 'Markdown',
                ],
            );

            if (! $response->successful()) {
                Log::warning('ReplyTool: Telegram sendMessage returned non-2xx', [
                    'chatId' => $this->chatId,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return json_encode(['delivered' => false, 'error' => $response->body()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }

            return json_encode(['delivered' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::warning('ReplyTool: Failed to send Telegram message', [
                'chatId' => $this->chatId,
                'error'  => $e->getMessage(),
            ]);

            return json_encode(['delivered' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
}
