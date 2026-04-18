<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Services\Anthropic\ToolDefinition;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\AgentTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ReplyTool implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'reply',
            description: 'Send a text message back to the user on Telegram. Call this when you want to talk to the user.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],
                ],
                'required' => ['text'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AgentContext $ctx): array
    {
        $text = mb_substr((string) ($input['text'] ?? ''), 0, 4096);

        try {
            $response = Http::post(
                "https://api.telegram.org/bot{$ctx->botToken}/sendMessage",
                [
                    'chat_id' => $ctx->chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ],
            );

            if (! $response->successful()) {
                Log::warning('ReplyTool: Telegram sendMessage returned non-2xx', [
                    'chatId' => $ctx->chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['delivered' => false, 'error' => $response->body()];
            }

            return ['delivered' => true];
        } catch (\Throwable $e) {
            Log::warning('ReplyTool: Failed to send Telegram message', [
                'chatId' => $ctx->chatId,
                'error' => $e->getMessage(),
            ]);

            return ['delivered' => false, 'error' => $e->getMessage()];
        }
    }
}
