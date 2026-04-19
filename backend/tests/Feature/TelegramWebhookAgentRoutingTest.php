<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\TelegramWebhookController;
use App\Models\ExecutionRun;
use App\Models\PendingInteraction;
use App\Models\Workflow;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that the TelegramWebhookController correctly routes messages:
 *  - Priority 0: callback_query  → handleCallbackQuery (NOT the agent)
 *  - Priority 1: reply-to-pending → tryResumePendingByMessage (NOT the agent)
 *  - Priority 1b: bare text + single pending → tryResumePendingByChat (NOT the agent)
 *  - Otherwise: free text + no pending → TelegramAgent::handle()
 *  - Slash command → TelegramAgent::handle() (agent owns slash routing)
 *
 * Seam: the controller accepts an optional $agentFactory \Closure in its
 * constructor. Tests swap this closure for one returning a spy object,
 * letting us assert handle() was / was not called without touching Redis or
 * the real LLM stack.
 *
 * Because TelegramAgent is final (cannot be Mockery-mocked), we use a
 * lightweight spy that records calls and is swapped in via the factory.
 */
final class TelegramWebhookAgentRoutingTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-routing-bot-token';
    private const CHAT_ID   = '999';

    private function webhookUrl(): string
    {
        return '/api/telegram/webhook/' . self::BOT_TOKEN;
    }

    /**
     * Install a spy in the controller's agentFactory.
     *
     * Returns a AgentSpy object whose $calls property records every
     * (update, botToken) pair passed to handle().
     *
     * Because TelegramAgent is final it cannot be Mockery-mocked.
     * We build a lightweight spy with the same handle() signature and
     * swap it in via the factory closure.
     */
    private function installAgentSpy(): object
    {
        $spy = new class {
            /** @var list<array{update: array, botToken: string}> */
            public array $calls = [];

            public function handle(array $update, string $botToken): void
            {
                $this->calls[] = ['update' => $update, 'botToken' => $botToken];
            }
        };

        $this->app->bind(
            TelegramWebhookController::class,
            fn() => new TelegramWebhookController(
                slashRouter: new SlashCommandRouter(),
                agentFactory: fn(string $chatId, string $botToken) => $spy,
            ),
        );

        return $spy;
    }

    /** Seed a workflow and a waiting PendingInteraction for the given chat. */
    private function seedPendingInteraction(string $channelMessageId = '77'): PendingInteraction
    {
        $workflow = Workflow::create([
            'name'     => 'Gate Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id'        => $workflow->id,
            'trigger'            => 'telegramWebhook',
            'status'             => 'awaitingReview',
            'document_snapshot'  => $workflow->document,
            'document_hash'      => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        return PendingInteraction::create([
            'run_id'             => $run->id,
            'node_id'            => 'node-gate-1',
            'channel'            => 'telegram',
            'channel_message_id' => $channelMessageId,
            'chat_id'            => self::CHAT_ID,
            'status'             => 'waiting',
            'proposal_payload'   => ['options' => ['approve', 'reject']],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 1 — callback_query hits handleCallbackQuery, NOT the agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function callback_query_routes_to_handle_callback_not_agent(): void
    {
        Queue::fake();

        $pending = $this->seedPendingInteraction('88');
        $spy = $this->installAgentSpy();

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson($this->webhookUrl(), [
            'callback_query' => [
                'id'      => 'cb-1',
                'data'    => "g:{$pending->run_id}:{$pending->node_id}:pick:0",
                'message' => [
                    'message_id' => 88,
                    'chat'       => ['id' => (int) self::CHAT_ID],
                    'text'       => 'Pick one:',
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertCount(0, $spy->calls, 'Agent must NOT be invoked for callback_query');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 2 — reply-to a pending gate routes to tryResumePendingByMessage, NOT agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reply_to_pending_message_routes_to_resume_not_agent(): void
    {
        Queue::fake();

        $this->seedPendingInteraction('55');
        $spy = $this->installAgentSpy();

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson($this->webhookUrl(), [
            'message' => [
                'chat'             => ['id' => (int) self::CHAT_ID],
                'text'             => 'approve',
                'reply_to_message' => ['message_id' => 55],
            ],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        Queue::assertPushed(\App\Jobs\ResumeWorkflowJob::class);
        $this->assertCount(0, $spy->calls, 'Agent must NOT be invoked for reply-to-pending');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 3 — bare text with exactly one pending in this chat → tryResumePendingByChat
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function bare_text_with_single_pending_routes_to_resume_by_chat_not_agent(): void
    {
        Queue::fake();

        // Seed exactly one pending for this chat
        $this->seedPendingInteraction('66');
        $spy = $this->installAgentSpy();

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson($this->webhookUrl(), [
            'message' => [
                'chat' => ['id' => (int) self::CHAT_ID],
                'text' => '1',
            ],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        Queue::assertPushed(\App\Jobs\ResumeWorkflowJob::class);
        $this->assertCount(0, $spy->calls, 'Agent must NOT be invoked when pending gate consumes the message');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 4 — free text with NO pending gate routes to agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function free_text_with_no_pending_routes_to_agent(): void
    {
        $update = [
            'message' => [
                'chat' => ['id' => (int) self::CHAT_ID],
                'text' => 'tạo kịch bản',
            ],
        ];

        $spy = $this->installAgentSpy();

        $response = $this->postJson($this->webhookUrl(), $update);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertCount(1, $spy->calls, 'Agent must be invoked exactly once for free text with no pending');
        $this->assertSame($update, $spy->calls[0]['update']);
        $this->assertSame(self::BOT_TOKEN, $spy->calls[0]['botToken']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 5 — /status slash command routes to the agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function slash_command_routes_to_agent(): void
    {
        $update = [
            'message' => [
                'chat' => ['id' => (int) self::CHAT_ID],
                'text' => '/status',
            ],
        ];

        $spy = $this->installAgentSpy();

        $response = $this->postJson($this->webhookUrl(), $update);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertCount(1, $spy->calls, 'Agent must be invoked exactly once for slash commands');
        $this->assertSame($update, $spy->calls[0]['update']);
        $this->assertSame(self::BOT_TOKEN, $spy->calls[0]['botToken']);
    }
}
