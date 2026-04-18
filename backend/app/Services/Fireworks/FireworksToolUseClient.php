<?php

declare(strict_types=1);

namespace App\Services\Fireworks;

use App\Services\Anthropic\ToolDefinition;
use App\Services\Anthropic\ToolUseClientContract;
use App\Services\Anthropic\ToolUseResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fireworks.ai tool-use client.
 *
 * Fireworks speaks the OpenAI chat/completions schema. This client accepts
 * Anthropic-shaped messages (so callers like TelegramAgent don't need to
 * care about the provider) and translates on the wire in both directions.
 *
 * Format mapping summary:
 *   Outbound messages:
 *     - Anthropic {role:user, content:string}                                → OpenAI {role:user, content:string}
 *     - Anthropic {role:user, content:[{type:tool_result,tool_use_id,content}]} → OpenAI one {role:tool, tool_call_id, content} per block
 *     - Anthropic {role:assistant, content:string}                          → OpenAI {role:assistant, content:string}
 *     - Anthropic {role:assistant, content:[{type:text|tool_use,...}]}      → OpenAI {role:assistant, content:<joined text>, tool_calls:[{id,type:function,function:{name,arguments:json}}]}
 *
 *   Tool definitions:
 *     Anthropic {name,description,input_schema} → OpenAI {type:function, function:{name,description,parameters}}
 *
 *   Response:
 *     OpenAI choices[0].message.content string → one {type:text} block
 *     OpenAI choices[0].message.tool_calls     → {type:tool_use,id,name,input} blocks (arguments JSON-decoded)
 *     OpenAI finish_reason: stop→end_turn, tool_calls→tool_use, length→max_tokens
 */
final class FireworksToolUseClient implements ToolUseClientContract
{
    private const API_URL = 'https://api.fireworks.ai/inference/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'accounts/fireworks/models/minimax-m2p7',
        private readonly int $maxTokens = 1024,
    ) {}

    public function send(array $messages, string $systemPrompt, array $tools): ToolUseResult
    {
        $openAiMessages = $this->translateMessages($systemPrompt, $messages);

        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => $openAiMessages,
        ];

        if ($tools !== []) {
            $body['tools'] = array_map(
                fn (ToolDefinition $t): array => $this->translateTool($t),
                $tools,
            );
            $body['tool_choice'] = 'auto';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->post(self::API_URL, $body);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf(
                    'Fireworks API request failed with status %d: %s',
                    $response->status(),
                    $response->body(),
                ),
            );
        }

        $data = $response->json();

        if (! isset($data['choices'][0]['message']) || ! is_array($data['choices'][0]['message'])) {
            throw new RuntimeException(
                'Fireworks API returned a malformed response: missing choices[0].message.',
            );
        }

        $message = $data['choices'][0]['message'];
        $finishReason = (string) ($data['choices'][0]['finish_reason'] ?? 'stop');

        return $this->translateResponse($message, $finishReason);
    }

    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @return list<array<string, mixed>>
     */
    private function translateMessages(string $systemPrompt, array $messages): array
    {
        $out = [];

        if ($systemPrompt !== '') {
            $out[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $msg) {
            $role = (string) ($msg['role'] ?? 'user');
            $content = $msg['content'] ?? '';

            if (is_string($content)) {
                $out[] = ['role' => $role, 'content' => $content];
                continue;
            }

            if (! is_array($content)) {
                $out[] = ['role' => $role, 'content' => (string) $content];
                continue;
            }

            if ($role === 'assistant') {
                $text = '';
                $toolCalls = [];
                foreach ($content as $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    $type = $block['type'] ?? null;
                    if ($type === 'text') {
                        $text .= (string) ($block['text'] ?? '');
                    } elseif ($type === 'tool_use') {
                        $input = $block['input'] ?? [];
                        $argsJson = (is_array($input) && $input === [])
                            ? '{}'
                            : json_encode($input, JSON_UNESCAPED_UNICODE);

                        $toolCalls[] = [
                            'id' => (string) ($block['id'] ?? ''),
                            'type' => 'function',
                            'function' => [
                                'name' => (string) ($block['name'] ?? ''),
                                'arguments' => $argsJson,
                            ],
                        ];
                    }
                }

                $assistantMsg = ['role' => 'assistant'];
                $assistantMsg['content'] = $text !== '' ? $text : null;
                if ($toolCalls !== []) {
                    $assistantMsg['tool_calls'] = $toolCalls;
                }
                $out[] = $assistantMsg;
                continue;
            }

            // role === 'user' with structured content — expect tool_result blocks
            foreach ($content as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (($block['type'] ?? null) === 'tool_result') {
                    $toolContent = $block['content'] ?? '';
                    if (is_array($toolContent)) {
                        $toolContent = json_encode($toolContent, JSON_UNESCAPED_UNICODE);
                    }
                    $out[] = [
                        'role' => 'tool',
                        'tool_call_id' => (string) ($block['tool_use_id'] ?? ''),
                        'content' => (string) $toolContent,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function translateTool(ToolDefinition $tool): array
    {
        $anthropic = $tool->toArray();

        return [
            'type' => 'function',
            'function' => [
                'name' => $anthropic['name'],
                'description' => $anthropic['description'] ?? '',
                'parameters' => $anthropic['input_schema'] ?? ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function translateResponse(array $message, string $finishReason): ToolUseResult
    {
        $stopReason = match ($finishReason) {
            'tool_calls' => 'tool_use',
            'length' => 'max_tokens',
            'stop' => 'end_turn',
            default => $finishReason,
        };

        $rawBlocks = [];
        $textBlocks = [];
        $toolCalls = [];

        $text = (string) ($message['content'] ?? '');
        if ($text !== '') {
            $rawBlocks[] = ['type' => 'text', 'text' => $text];
            $textBlocks[] = $text;
        }

        foreach ((array) ($message['tool_calls'] ?? []) as $tc) {
            if (! is_array($tc)) {
                continue;
            }
            $fn = $tc['function'] ?? [];
            $name = (string) ($fn['name'] ?? '');
            $argsRaw = $fn['arguments'] ?? '{}';
            $input = is_string($argsRaw) ? json_decode($argsRaw, true) : $argsRaw;
            if (! is_array($input)) {
                $input = [];
            }
            $id = (string) ($tc['id'] ?? '');
            $rawBlocks[] = [
                'type' => 'tool_use',
                'id' => $id,
                'name' => $name,
                'input' => $input,
            ];
            $toolCalls[] = [
                'id' => $id,
                'name' => $name,
                'input' => $input,
            ];
        }

        // If the model returned tool_calls but finish_reason was 'stop', upgrade to 'tool_use'.
        if ($toolCalls !== [] && $stopReason === 'end_turn') {
            $stopReason = 'tool_use';
        }

        return new ToolUseResult(
            stopReason: $stopReason,
            toolCalls: $toolCalls,
            textBlocks: $textBlocks,
            rawAssistantMessage: $rawBlocks,
        );
    }
}
