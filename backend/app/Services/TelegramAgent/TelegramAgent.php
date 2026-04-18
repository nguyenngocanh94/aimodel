<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Models\Workflow;
use App\Services\Anthropic\AnthropicToolUseClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrator that ties together the Anthropic tool-use client, agent session,
 * slash command router, and individual agent tools into a coherent message handler.
 *
 * Entry point: handle(array $update, string $botToken): void
 *
 * The handle() method:
 *  a) Extracts chat/user/text from the Telegram update.
 *  b) Routes slash commands directly (no LLM).
 *  c) Runs the multi-turn tool-use loop (capped at 8 iterations) for free text.
 *  d) Persists the session after every interaction.
 */
final class TelegramAgent implements HandlesTelegramUpdate
{
    /** Maximum number of tool-use loop iterations before forcing a fallback reply. */
    private const MAX_ITERATIONS = 8;

    /**
     * @param  array<int, AgentTool>  $tools  Ordered list of available tools.
     */
    public function __construct(
        private readonly AnthropicToolUseClient $anthropic,
        private readonly AgentSessionStore $sessionStore,
        private readonly SlashCommandRouter $slashRouter,
        private readonly array $tools,
    ) {}

    /**
     * Handle an inbound Telegram update.
     *
     * @param  array<string, mixed>  $update    Raw decoded Telegram update object.
     * @param  string                $botToken  Bot token (used for Telegram API calls & session key).
     */
    public function handle(array $update, string $botToken): void
    {
        // ── a. Extract fields ──────────────────────────────────────────────────
        $message = $update['message'] ?? $update['channel_post'] ?? [];
        $chatId  = (string) ($message['chat']['id'] ?? '');
        $userId  = isset($message['from']['id']) ? (string) $message['from']['id'] : null;
        $text    = (string) ($message['text'] ?? $message['caption'] ?? '');

        // ── b. Ignore empty messages ───────────────────────────────────────────
        if ($text === '' || $chatId === '') {
            return;
        }

        // ── c. Slash command path ──────────────────────────────────────────────
        if ($text[0] === '/') {
            // /reset must clear Redis before replying.
            $trimmedCmd = strtolower(explode(' ', trim($text))[0]);
            if ($trimmedCmd === '/reset') {
                $this->sessionStore->forget($chatId, $botToken);
            }

            $reply = $this->slashRouter->route($text, $chatId);

            if ($reply !== null) {
                $this->sendTelegramMessage($botToken, $chatId, $reply);
                return;
            }

            // Null means "not a slash command" — shouldn't happen for `/`-prefixed
            // text per T6 contract, but fall through to LLM path as a guard.
        }

        // ── d. Load session ────────────────────────────────────────────────────
        $session = $this->sessionStore->load($chatId, $botToken);

        // ── e. Append user message ─────────────────────────────────────────────
        $session->messages[] = ['role' => 'user', 'content' => $text];

        // ── f. Build context ───────────────────────────────────────────────────
        $ctx = new AgentContext(
            chatId: $chatId,
            userId: $userId,
            sessionId: "{$chatId}:{$botToken}",
            botToken: $botToken,
        );

        // ── g. Build catalog preview ───────────────────────────────────────────
        $catalogPreview = Workflow::triggerable()
            ->get(['slug', 'name', 'nl_description', 'param_schema'])
            ->toArray();

        // ── h. Build system prompt ─────────────────────────────────────────────
        $systemPrompt = SystemPrompt::build($catalogPreview, $chatId);

        // ── i. Build tool definitions ──────────────────────────────────────────
        $toolDefinitions = array_map(
            static fn (AgentTool $t) => $t->definition(),
            $this->tools,
        );

        // ── j. Tool-use loop ───────────────────────────────────────────────────
        $iteration    = 0;
        $normalExit   = false;

        while ($iteration < self::MAX_ITERATIONS) {
            $iteration++;

            $result = $this->anthropic->send($session->messages, $systemPrompt, $toolDefinitions);

            $stopReason = $result->stopReason;

            if ($stopReason === 'end_turn') {
                // Emit any text blocks to the user.
                $text = implode('', $result->textBlocks);
                if ($text !== '') {
                    $this->sendTelegramMessage($botToken, $chatId, $text);
                }

                // Persist full raw assistant message.
                $session->messages[] = [
                    'role'    => 'assistant',
                    'content' => $result->rawAssistantMessage,
                ];

                $normalExit = true;
                break;
            }

            if ($stopReason === 'max_tokens') {
                $text = implode('', $result->textBlocks);
                $suffix = "\n\n_(lời nhắn bị cắt, bạn có muốn tôi tiếp tục không?)_";
                $combined = $text !== '' ? $text . $suffix : $suffix;
                $this->sendTelegramMessage($botToken, $chatId, $combined);

                $session->messages[] = [
                    'role'    => 'assistant',
                    'content' => $result->rawAssistantMessage,
                ];

                $normalExit = true;
                break;
            }

            if ($stopReason === 'tool_use') {
                // Append assistant turn with tool_use blocks.
                $session->messages[] = [
                    'role'    => 'assistant',
                    'content' => $result->rawAssistantMessage,
                ];

                // Execute each tool call and collect tool_result blocks.
                $toolResultBlocks = [];

                foreach ($result->toolCalls as $call) {
                    $toolResult = $this->executeToolCall($call, $ctx);

                    $toolResultBlocks[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $call['id'],
                        'content'     => json_encode($toolResult, JSON_THROW_ON_ERROR),
                    ];
                }

                // Append user turn with all tool_result blocks.
                $session->messages[] = [
                    'role'    => 'user',
                    'content' => $toolResultBlocks,
                ];

                // Continue loop.
                continue;
            }

            // Any other stop reason — log and treat as end_turn.
            Log::warning('TelegramAgent: unexpected stopReason', [
                'stopReason' => $stopReason,
                'chatId'     => $chatId,
                'iteration'  => $iteration,
            ]);

            $text = implode('', $result->textBlocks);
            if ($text !== '') {
                $this->sendTelegramMessage($botToken, $chatId, $text);
            }

            $session->messages[] = [
                'role'    => 'assistant',
                'content' => $result->rawAssistantMessage,
            ];

            $normalExit = true;
            break;
        }

        // ── k. Post-loop cleanup ───────────────────────────────────────────────
        if (! $normalExit) {
            // Cap was hit without an end_turn — send Vietnamese fallback.
            $this->sendTelegramMessage(
                $botToken,
                $chatId,
                'Tôi bị lạc — gõ /reset nếu cần bắt đầu lại.',
            );
        }

        $this->sessionStore->save($session);
    }

    /**
     * Find the matching tool by name and execute it.
     * Returns the tool result payload (always an array).
     *
     * @param  array{id: string, name: string, input: array}  $call
     * @return array<string, mixed>
     */
    private function executeToolCall(array $call, AgentContext $ctx): array
    {
        $toolName = $call['name'];

        foreach ($this->tools as $tool) {
            if ($tool->definition()->name === $toolName) {
                try {
                    return $tool->execute($call['input'], $ctx);
                } catch (Throwable $e) {
                    Log::warning('TelegramAgent: tool threw an exception', [
                        'tool'    => $toolName,
                        'error'   => $e->getMessage(),
                        'chatId'  => $ctx->chatId,
                    ]);

                    return ['error' => $e->getMessage()];
                }
            }
        }

        // No matching tool found.
        return ['error' => 'unknown_tool', 'name' => $toolName];
    }

    /**
     * Send a plain Telegram message, truncating to 4096 characters.
     * Errors are logged and swallowed — this is best-effort delivery.
     */
    private function sendTelegramMessage(string $botToken, string $chatId, string $text): void
    {
        try {
            Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id'    => $chatId,
                    'text'       => mb_substr($text, 0, 4096),
                    'parse_mode' => 'Markdown',
                ],
            );
        } catch (Throwable $e) {
            Log::warning('TelegramAgent: failed to send Telegram message', [
                'chatId' => $chatId,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
