<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\Anthropic\AnthropicToolUseClient;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\CancelRunTool;
use App\Services\TelegramAgent\Tools\GetRunStatusTool;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TelegramAgentTest extends TestCase
{
    use RefreshDatabase;

    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const BOT_TOKEN     = 'test-bot-token-123';
    private const CHAT_ID       = '42';

    // The Telegram sendMessage URL pattern for this bot token.
    private function telegramUrl(): string
    {
        return 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';
    }

    /** Build a fresh TelegramAgent instance with all real tools (HTTP is faked). */
    private function makeAgent(): TelegramAgent
    {
        return new TelegramAgent(
            anthropic: new AnthropicToolUseClient(
                apiKey: 'test-key',
                model: 'claude-sonnet-4-6',
            ),
            sessionStore: new AgentSessionStore(),
            slashRouter: new SlashCommandRouter(),
            tools: [
                new ListWorkflowsTool(),
                new RunWorkflowTool(),
                new GetRunStatusTool(),
                new CancelRunTool(),
                new ReplyTool(),
            ],
        );
    }

    /** Seed a triggerable workflow for tests that need one. */
    private function seedTriggerableWorkflow(): Workflow
    {
        return Workflow::create([
            'name'           => 'StoryWriter (per-node gate) – Telegram',
            'document'       => ['nodes' => [], 'edges' => []],
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt.',
            'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers for Anthropic fake response bodies
    // ─────────────────────────────────────────────────────────────────────────

    private function anthropicEndTurn(string $text = 'OK'): array
    {
        return [
            'stop_reason' => 'end_turn',
            'content'     => [['type' => 'text', 'text' => $text]],
        ];
    }

    private function anthropicToolUse(string $id, string $name, array $input): array
    {
        return [
            'stop_reason' => 'tool_use',
            'content'     => [
                [
                    'type'  => 'tool_use',
                    'id'    => $id,
                    'name'  => $name,
                    'input' => $input,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1 — slash command path
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function slash_help_sends_telegram_message_without_calling_anthropic(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        $agent = $this->makeAgent();
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => '/help']],
            botToken: self::BOT_TOKEN,
        );

        // Exactly one Telegram POST (no Anthropic calls).
        Http::assertSentCount(1);
        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'sendMessage');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2 — tool-use happy path
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function tool_use_happy_path_creates_run_and_sends_reply(): void
    {
        Queue::fake();

        $workflow = $this->seedTriggerableWorkflow();

        // Set up a single Http::fake() map with sequences for both URLs.
        Http::fake([
            self::ANTHROPIC_URL => Http::sequence()
                // Round 1: list_workflows
                ->push($this->anthropicToolUse('tu1', 'list_workflows', []), 200)
                // Round 2: run_workflow
                ->push($this->anthropicToolUse('tu2', 'run_workflow', [
                    'slug'   => 'story-writer-gated',
                    'params' => ['productBrief' => 'bánh chocopie'],
                ]), 200)
                // Round 3: reply with confirmation
                ->push($this->anthropicToolUse('tu3', 'reply', [
                    'text' => 'Đã khởi động! Kiểm tra /status.',
                ]), 200)
                // Round 4: end_turn
                ->push($this->anthropicEndTurn('Xong.'), 200),
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        $agent = $this->makeAgent();
        $agent->handle(
            update: [
                'message' => [
                    'chat' => ['id' => self::CHAT_ID],
                    'from' => ['id' => 100],
                    'text' => 'tạo kịch bản video chocopie',
                ],
            ],
            botToken: self::BOT_TOKEN,
        );

        // An ExecutionRun must have been created for the right workflow.
        $this->assertDatabaseHas('execution_runs', [
            'workflow_id' => $workflow->id,
            'trigger'     => 'telegramWebhook',
            'status'      => 'pending',
        ]);

        // RunWorkflowJob must have been pushed exactly once.
        Queue::assertPushed(RunWorkflowJob::class, 1);

        // ReplyTool + final end_turn text sent to Telegram.
        Http::assertSent(function ($req) {
            return str_contains($req->url(), 'sendMessage');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3 — loop cap
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function loop_cap_sends_fallback_and_saves_session(): void
    {
        // Anthropic always returns tool_use for an unknown tool → forces 8 iterations.
        Http::fake([
            self::ANTHROPIC_URL  => Http::response(
                $this->anthropicToolUse('tu_loop', 'nonexistent_tool', []),
                200,
            ),
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        $agent = $this->makeAgent();
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'looping forever']],
            botToken: self::BOT_TOKEN,
        );

        // Vietnamese fallback must have been sent to Telegram.
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'sendMessage')) {
                return false;
            }
            // Body is JSON; decode to check the actual text value.
            $body = json_decode((string) $req->body(), true);
            $text = $body['text'] ?? '';
            return str_contains($text, 'Tôi bị lạc') || str_contains($text, 'T\u00f4i b\u1ecb l\u1ea1c');
        });

        // Anthropic was called exactly 8 times (+ 1 Telegram fallback = 9 total).
        Http::assertSentCount(9);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4 — session persistence
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function session_persists_across_two_messages(): void
    {
        Http::fake([
            self::ANTHROPIC_URL  => Http::sequence()
                // First user message — end_turn immediately.
                ->push($this->anthropicEndTurn('Xin chào!'), 200)
                // Second user message — end_turn immediately.
                ->push($this->anthropicEndTurn('Được rồi.'), 200),
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        $agent = $this->makeAgent();

        // First message.
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'Xin chào']],
            botToken: self::BOT_TOKEN,
        );

        // Second message.
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'Chạy workflow nhé']],
            botToken: self::BOT_TOKEN,
        );

        // Load the session directly to inspect stored messages.
        $store   = new AgentSessionStore();
        $session = $store->load(self::CHAT_ID, self::BOT_TOKEN);

        $roles     = array_column($session->messages, 'role');
        $userCount = count(array_filter($roles, fn ($r) => $r === 'user'));

        // At least 2 user turns must be in the saved session.
        $this->assertGreaterThanOrEqual(2, $userCount, 'Expected at least 2 user messages in saved session');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 5 — empty text update
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function empty_text_update_returns_without_http_calls(): void
    {
        Http::fake();   // record all; any call would be noticed

        $agent = $this->makeAgent();

        // Update with no text field.
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID]]],
            botToken: self::BOT_TOKEN,
        );

        // Photo/sticker update with no text.
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'photo' => [[]]]],
            botToken: self::BOT_TOKEN,
        );

        Http::assertNothingSent();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 6 — unknown slash command
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function unknown_slash_returns_router_error_reply(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        $agent = $this->makeAgent();
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => '/nope']],
            botToken: self::BOT_TOKEN,
        );

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'sendMessage')) {
                return false;
            }
            $body = json_decode((string) $req->body(), true);
            $text = $body['text'] ?? '';
            return str_contains($text, 'Lệnh không hợp lệ');
        });
    }
}
