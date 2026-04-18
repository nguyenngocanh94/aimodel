<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExecutionRun;
use App\Models\PendingInteraction;
use App\Models\Workflow;
use App\Services\TelegramAgent\HandlesTelegramUpdate;
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

    /** Seed a workflow and a waiting PendingInteraction for the given chat. */
    private function seedPendingInteraction(string $channelMessageId = '77'): PendingInteraction
    {
        $workflow = Workflow::create([
            'name'     => 'Gate Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id'       => $workflow->id,
            'trigger'           => 'telegramWebhook',
            'status'            => 'awaitingReview',
            'document_snapshot' => $workflow->document,
            'document_hash'     => hash('sha256', 'test'),
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

        $agentMock = $this->mock(HandlesTelegramUpdate::class);
        $agentMock->shouldNotReceive('handle');

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
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 2 — reply-to a pending gate routes to tryResumePendingByMessage, NOT agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function reply_to_pending_message_routes_to_resume_not_agent(): void
    {
        Queue::fake();

        $this->seedPendingInteraction('55');

        $agentMock = $this->mock(HandlesTelegramUpdate::class);
        $agentMock->shouldNotReceive('handle');

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

        $agentMock = $this->mock(HandlesTelegramUpdate::class);
        $agentMock->shouldNotReceive('handle');

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

        $agentMock = $this->mock(HandlesTelegramUpdate::class);
        $agentMock->shouldReceive('handle')
            ->once()
            ->withArgs(function (array $receivedUpdate, string $botToken) use ($update): bool {
                return $receivedUpdate === $update && $botToken === self::BOT_TOKEN;
            });

        $response = $this->postJson($this->webhookUrl(), $update);

        $response->assertOk()->assertJson(['ok' => true]);
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

        $agentMock = $this->mock(HandlesTelegramUpdate::class);
        $agentMock->shouldReceive('handle')
            ->once()
            ->withArgs(function (array $receivedUpdate, string $botToken) use ($update): bool {
                return $receivedUpdate === $update && $botToken === self::BOT_TOKEN;
            });

        $response = $this->postJson($this->webhookUrl(), $update);

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
