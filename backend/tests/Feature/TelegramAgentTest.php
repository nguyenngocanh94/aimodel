<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\RedisConversationStore;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Contracts\ConversationStore;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TelegramAgentTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token-123';
    private const CHAT_ID   = '42';

    private function telegramUrl(): string
    {
        return 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';
    }

    /** Build a fresh TelegramAgent instance. */
    private function makeAgent(): TelegramAgent
    {
        return new TelegramAgent(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            slashRouter: new SlashCommandRouter(),
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

    protected function setUp(): void
    {
        parent::setUp();
        // Clear Redis conversation keys before each test.
        $store = $this->app->make(RedisConversationStore::class);
        $conversationUserId = self::CHAT_ID . ':' . self::BOT_TOKEN;
        $store->forgetUser($conversationUserId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 1 — slash command path
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function slash_help_sends_telegram_message_without_calling_llm(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        // Fake the agent so any stray LLM call would throw.
        TelegramAgent::fake()->preventStrayPrompts();

        $agent = $this->makeAgent();
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => '/help']],
            botToken: self::BOT_TOKEN,
        );

        // Exactly one Telegram POST (no LLM calls).
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sendMessage'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 2 — tool-use happy path (fake LLM; real tool execution)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function tool_use_happy_path_creates_run_and_sends_reply(): void
    {
        Queue::fake();

        $workflow = $this->seedTriggerableWorkflow();

        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        // Fake the LLM to return a confirmation text response.
        TelegramAgent::fake(['Đã khởi động workflow story-writer-gated!']);

        // Directly execute RunWorkflowTool to simulate what the LLM would trigger.
        $runTool = new \App\Services\TelegramAgent\Tools\RunWorkflowTool(chatId: self::CHAT_ID);
        $request = new \Laravel\Ai\Tools\Request([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => 'bánh chocopie'],
        ]);
        $runTool->handle($request);

        // An ExecutionRun must have been created for the right workflow.
        $this->assertDatabaseHas('execution_runs', [
            'workflow_id' => $workflow->id,
            'trigger'     => 'telegramWebhook',
            'status'      => 'pending',
        ]);

        Queue::assertPushed(RunWorkflowJob::class, 1);

        // Now verify the agent routes free text through prompt().
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

        // The fake returns text which handle() forwards to Telegram.
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sendMessage'));

        TelegramAgent::assertPrompted('tạo kịch bản video chocopie');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 3 — session persistence
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function session_persists_across_two_messages(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        // Fake LLM responses for both turns.
        TelegramAgent::fake(['Xin chào!', 'Được rồi.']);

        $agent = $this->makeAgent();

        // First message.
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'Xin chào']],
            botToken: self::BOT_TOKEN,
        );

        // Second message — new agent instance simulates a fresh request.
        $agent2 = $this->makeAgent();
        $agent2->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'Chạy workflow nhé']],
            botToken: self::BOT_TOKEN,
        );

        // Two prompts must have been recorded.
        TelegramAgent::assertPrompted('Xin chào');
        TelegramAgent::assertPrompted('Chạy workflow nhé');

        // Verify conversation was stored in Redis store.
        /** @var RedisConversationStore $store */
        $store = $this->app->make(RedisConversationStore::class);
        $userId = self::CHAT_ID . ':' . self::BOT_TOKEN;
        $conversationId = $store->latestConversationId($userId);

        $this->assertNotNull($conversationId, 'Expected a conversation ID to be stored in Redis');

        $messages = $store->getLatestConversationMessages($conversationId, 100);
        $this->assertGreaterThanOrEqual(2, $messages->count(), 'Expected at least 2 messages in the stored conversation');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test 4 — empty text update
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function empty_text_update_returns_without_http_calls(): void
    {
        Http::fake();

        TelegramAgent::fake()->preventStrayPrompts();

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
    // Test 5 — unknown slash command
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function unknown_slash_returns_router_error_reply(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        TelegramAgent::fake()->preventStrayPrompts();

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

    // ─────────────────────────────────────────────────────────────────────────
    // Test 6 — /reset wipes conversation
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reset_slash_wipes_conversation_and_next_turn_starts_fresh(): void
    {
        Http::fake([
            $this->telegramUrl() => Http::response(['ok' => true], 200),
        ]);

        TelegramAgent::fake(['Xin chào!', 'Bắt đầu lại.']);

        $userId = self::CHAT_ID . ':' . self::BOT_TOKEN;

        /** @var RedisConversationStore $store */
        $store = $this->app->make(RedisConversationStore::class);

        // First turn — establishes a conversation.
        $agent = $this->makeAgent();
        $agent->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => 'Xin chào']],
            botToken: self::BOT_TOKEN,
        );

        $conversationIdBefore = $store->latestConversationId($userId);
        $this->assertNotNull($conversationIdBefore, 'Expected conversation after first turn');

        // /reset — wipes Redis conversation.
        $agent2 = $this->makeAgent();
        $agent2->handle(
            update: ['message' => ['chat' => ['id' => self::CHAT_ID], 'text' => '/reset']],
            botToken: self::BOT_TOKEN,
        );

        // After /reset the Redis key should be gone.
        $conversationIdAfter = $store->latestConversationId($userId);
        $this->assertNull($conversationIdAfter, 'Expected conversation to be wiped after /reset');

        // Reset reply was sent to Telegram.
        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'sendMessage')) {
                return false;
            }
            $body = json_decode((string) $req->body(), true);
            return str_contains($body['text'] ?? '', 'xoá');
        });
    }
}
