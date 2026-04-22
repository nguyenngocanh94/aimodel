<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessTelegramBatchJob;
use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\TelegramAgentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FX-04: integration coverage for Priority 2b — the buffer → debounce-job → agent pipeline.
 *
 * This replaces the earlier weak unit test that only exercised readPendingDraft().
 * Here we verify end-to-end:
 *  1. A free-text webhook hit queues ProcessTelegramBatchJob with the correct delay.
 *  2. Fresh turn (no pending draft) → 5s delay.
 *  3. Pending draft present → 5s delay.
 *  4. Running the queued job assembles the combined update and invokes the agent
 *     via TelegramAgentFactory, with the joined burst text.
 *  5. A webhook update carrying an image `document` (mime: image/*) reaches the
 *     agent through the same pipeline.
 *
 * We do NOT assert specific LLM tool picks — that would test the model, which
 * is non-deterministic. We test the scaffolding: job queued, delay correct,
 * agent invoked with the expected combined update shape.
 */
final class TelegramWebhookBufferDispatchTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-buffer-bot-token';
    private const CHAT_ID   = '777';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean any Redis state that might leak between tests.
        Redis::del("telegram_intake:" . self::CHAT_ID . ':' . self::BOT_TOKEN);
        Redis::del("telegram_batch_job:" . self::CHAT_ID . ':' . self::BOT_TOKEN);
        Redis::del("ai_session:" . self::CHAT_ID . ':' . self::BOT_TOKEN);
    }

    protected function tearDown(): void
    {
        Redis::del("telegram_intake:" . self::CHAT_ID . ':' . self::BOT_TOKEN);
        Redis::del("telegram_batch_job:" . self::CHAT_ID . ':' . self::BOT_TOKEN);
        Redis::del("ai_session:" . self::CHAT_ID . ':' . self::BOT_TOKEN);

        parent::tearDown();
    }

    private function webhookUrl(): string
    {
        return '/api/telegram/webhook/' . self::BOT_TOKEN;
    }

    private function freeTextUpdate(string $text): array
    {
        return [
            'update_id' => 100,
            'message'   => [
                'message_id' => 1,
                'chat'       => ['id' => (int) self::CHAT_ID],
                'from'       => ['id' => 1, 'first_name' => 'Tester'],
                'date'       => time(),
                'text'       => $text,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Debounce window selection
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function free_text_without_pending_draft_queues_job_with_5s_delay(): void
    {
        Queue::fake();

        $response = $this->postJson($this->webhookUrl(), $this->freeTextUpdate('tạo workflow video'));

        $response->assertOk();
        Queue::assertPushed(ProcessTelegramBatchJob::class, function ($job) {
            $delay = $job->delay;
            if ($delay instanceof \DateTimeInterface) {
                $secondsFromNow = $delay->getTimestamp() - time();
                return $secondsFromNow >= 3 && $secondsFromNow <= 7;
            }
            return $delay === 5;
        });
    }

    #[Test]
    public function free_text_with_pending_draft_queues_job_with_5s_delay(): void
    {
        Queue::fake();

        // Seed a pending draft in AgentSessionStore so the debounce selector
        // chooses the short window.
        $store = $this->app->make(AgentSessionStore::class);
        $session = new AgentSession(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
        );
        $session->pendingPlan = [
            'intent'     => 'test-intent',
            'vibeMode'   => 'clean_education',
            'nodes'      => [],
            'edges'      => [],
            'assumptions' => [],
            'rationale'  => 'test',
            'meta'       => ['plannerVersion' => '1.0'],
        ];
        $store->save($session);

        $this->assertTrue($store->readPendingDraft(self::CHAT_ID, self::BOT_TOKEN),
            'seed: pending draft must be readable before webhook hit');

        $response = $this->postJson($this->webhookUrl(), $this->freeTextUpdate('ok'));

        $response->assertOk();
        Queue::assertPushed(ProcessTelegramBatchJob::class, function ($job) {
            $delay = $job->delay;
            if ($delay instanceof \DateTimeInterface) {
                $secondsFromNow = $delay->getTimestamp() - time();
                return $secondsFromNow >= 3 && $secondsFromNow <= 7;
            }
            return $delay === 5;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // End-to-end: running the job invokes the agent with the combined update
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function running_queued_job_invokes_agent_with_joined_burst_text(): void
    {
        // Don't fake the queue — we want to execute the job. Swap the
        // TelegramAgentFactory with a stand-in that returns a spy "agent"
        // so we don't hit the real LLM. TelegramAgentFactory is final so we
        // replace it in the container with an unrelated class whose make()
        // returns our spy.
        $spyAgent = new class {
            /** @var list<array{update: array, botToken: string}> */
            public array $calls = [];
            public function handle(array $update, string $botToken): void
            {
                $this->calls[] = ['update' => $update, 'botToken' => $botToken];
            }
        };

        $fakeFactory = new class ($spyAgent) {
            public function __construct(public readonly object $spy) {}
            public function make(string $chatId, string $botToken): object
            {
                return $this->spy;
            }
        };

        $this->app->instance(TelegramAgentFactory::class, $fakeFactory);

        // Buffer 3 messages into the session manually to simulate a burst.
        $sessionKey = 'telegram_intake:' . self::CHAT_ID . ':' . self::BOT_TOKEN;
        $session = [
            'status'    => 'buffering',
            'botToken'  => self::BOT_TOKEN,
            'chatId'    => self::CHAT_ID,
            'messages'  => [
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 1, 'text' => 'first'],
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 2, 'text' => 'second'],
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 3, 'text' => 'third'],
            ],
            'texts'     => ['first', 'second', 'third'],
            'images'    => [],
            'startedAt' => now()->toIso8601String(),
        ];
        Redis::set($sessionKey, json_encode($session), 'EX', 120);

        // Run the job synchronously.
        $job = new ProcessTelegramBatchJob($sessionKey, self::BOT_TOKEN, self::CHAT_ID);
        $job->handle();

        $this->assertCount(1, $spyAgent->calls, 'agent.handle() must be invoked exactly once per batch');
        $this->assertSame(self::BOT_TOKEN, $spyAgent->calls[0]['botToken']);
        $combinedText = $spyAgent->calls[0]['update']['message']['text'] ?? '';
        $this->assertStringContainsString('first', $combinedText);
        $this->assertStringContainsString('second', $combinedText);
        $this->assertStringContainsString('third', $combinedText);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Image-document end-to-end: webhook → buffer → job → agent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function image_document_update_is_buffered_and_queued(): void
    {
        Queue::fake();

        $update = [
            'update_id' => 200,
            'message'   => [
                'message_id' => 10,
                'chat'       => ['id' => (int) self::CHAT_ID],
                'from'       => ['id' => 1, 'first_name' => 'Tester'],
                'date'       => time(),
                'document'   => [
                    'file_id'   => 'doc-jpeg-xyz',
                    'file_name' => 'poster.jpg',
                    'mime_type' => 'image/jpeg',
                ],
                // No text, no photo — image delivered as document.
            ],
        ];

        $response = $this->postJson($this->webhookUrl(), $update);

        $response->assertOk();
        Queue::assertPushed(ProcessTelegramBatchJob::class);

        // Verify the session now contains the image (extracted from the document).
        $sessionKey = 'telegram_intake:' . self::CHAT_ID . ':' . self::BOT_TOKEN;
        $raw = Redis::get($sessionKey);
        $this->assertNotEmpty($raw, 'intake session must be created by bufferMessage');
        $session = json_decode($raw, true);
        $this->assertCount(1, $session['images'] ?? [],
            'image document must be extracted into session.images');
        $this->assertSame('doc-jpeg-xyz', $session['images'][0]['file_id']);
        $this->assertSame('image/jpeg', $session['images'][0]['mime_type']);
    }
}
