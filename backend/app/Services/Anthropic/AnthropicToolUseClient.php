<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the Anthropic Messages API that supports tool use.
 *
 * The caller is responsible for driving the multi-turn tool-use loop:
 *   1. Call send() with the current message history.
 *   2. If stopReason === 'tool_use', execute each tool call, append both the
 *      assistant content (rawAssistantMessage) and a tool_result user message,
 *      then call send() again.
 *   3. Repeat until stopReason === 'end_turn' or 'max_tokens'.
 */
final class AnthropicToolUseClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly int $maxTokens = 1024,
    ) {}

    /**
     * Send a single round-trip to the Anthropic Messages API.
     *
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, ToolDefinition>  $tools
     *
     * @throws RuntimeException on non-2xx responses or malformed payloads
     */
    public function send(array $messages, string $systemPrompt, array $tools): ToolUseResult
    {
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $body['tools'] = array_map(
                static fn (ToolDefinition $t): array => $t->toArray(),
                $tools,
            );
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])->post(self::API_URL, $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf(
                    'Anthropic API request failed with status %d: %s',
                    $response->status(),
                    $response->body(),
                ),
            );
        }

        $data = $response->json();

        if (! isset($data['content']) || ! is_array($data['content'])) {
            throw new RuntimeException(
                'Anthropic API returned a malformed response: missing or invalid "content" field.',
            );
        }

        $stopReason = $data['stop_reason'] ?? 'end_turn';
        $textBlocks = [];
        $toolCalls = [];

        foreach ($data['content'] as $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }

            if ($block['type'] === 'text') {
                $textBlocks[] = (string) ($block['text'] ?? '');
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? ''),
                    'name' => (string) ($block['name'] ?? ''),
                    'input' => is_array($block['input'] ?? null) ? $block['input'] : [],
                ];
            }
        }

        return new ToolUseResult(
            stopReason: (string) $stopReason,
            toolCalls: $toolCalls,
            textBlocks: $textBlocks,
            rawAssistantMessage: $data['content'],
        );
    }
}
