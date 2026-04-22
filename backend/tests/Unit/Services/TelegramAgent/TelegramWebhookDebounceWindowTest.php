<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Http\Controllers\TelegramWebhookController;
use App\Jobs\ProcessTelegramBatchJob;
use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\SlashCommandRouter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TG-04: Debounce window selector — 5s when pending draft, 30s otherwise.
 *
 * AgentSessionStore is final, so we seed Redis directly to control its state
 * rather than mocking the class.
 */
final class TelegramWebhookDebounceWindowTest extends TestCase
{
    private const BOT_TOKEN = 'test-debounce-bot';
    private const CHAT_ID   = '77001';

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del("telegram_intake:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        Redis::del("telegram_batch_job:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
    }

    protected function tearDown(): void
    {
        Redis::del("telegram_intake:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        Redis::del("telegram_batch_job:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        parent::tearDown();
    }

    private function webhookUrl(): string
    {
        return '/api/telegram/webhook/' . self::BOT_TOKEN;
    }

    private function buildController(): TelegramWebhookController
    {
        return new TelegramWebhookController(
            slashRouter: new SlashCommandRouter(),
            agentFactory: fn(string $chatId, string $botToken) => null,
            sessionStore: null, // use real store; Redis seeded in each test
        );
    }

    #[Test]
    public function dispatches_with_30s_delay_when_no_pending_draft(): void
    {
        Queue::fake();

        // No pending draft — Redis key doesn't exist (setUp cleared it).
        $this->app->bind(
            TelegramWebhookController::class,
            fn() => $this->buildController(),
        );

        $response = $this->postJson($this->webhookUrl(), [
            'message' => [
                'chat' => ['id' => (int) self::CHAT_ID],
                'text' => 'tạo kịch bản video tiktok',
            ],
        ]);

        $response->assertOk();

        Queue::assertPushed(ProcessTelegramBatchJob::class, function ($job) {
            // The job should have a ~30s delay.
            $delay = $job->delay;
            if ($delay instanceof \DateTimeInterface || $delay instanceof \Carbon\Carbon) {
                $seconds = now()->diffInSeconds($delay, false);
                return $seconds >= 28 && $seconds <= 32;
            }
            return false;
        });
    }

    #[Test]
    public function dispatches_with_5s_delay_when_pending_draft_exists(): void
    {
        Queue::fake();

        // Seed a pending draft so readPendingDraft() returns true.
        $store   = app(AgentSessionStore::class);
        $session = new AgentSession(chatId: self::CHAT_ID, botToken: self::BOT_TOKEN);
        $session->pendingPlan = [
            'intent'      => 'test',
            'vibeMode'    => 'clean',
            'nodes'       => [],
            'edges'       => [],
            'assumptions' => [],
            'rationale'   => 'test',
            'meta'        => [],
        ];
        $store->save($session);

        $this->app->bind(
            TelegramWebhookController::class,
            fn() => $this->buildController(),
        );

        $response = $this->postJson($this->webhookUrl(), [
            'message' => [
                'chat' => ['id' => (int) self::CHAT_ID],
                'text' => 'ok',
            ],
        ]);

        $response->assertOk();

        Queue::assertPushed(ProcessTelegramBatchJob::class, function ($job) {
            $delay = $job->delay;
            if ($delay instanceof \DateTimeInterface || $delay instanceof \Carbon\Carbon) {
                $seconds = now()->diffInSeconds($delay, false);
                return $seconds >= 3 && $seconds <= 7;
            }
            return false;
        });
    }
}
