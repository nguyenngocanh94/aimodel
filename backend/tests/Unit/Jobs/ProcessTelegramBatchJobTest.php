<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessTelegramBatchJob;
use App\Services\TelegramAgent\TelegramAgentFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TG-05: ProcessTelegramBatchJob — assembly, routing, stale handling, fallback.
 */
final class ProcessTelegramBatchJobTest extends TestCase
{
    private const BOT_TOKEN  = 'test-batch-job-bot';
    private const CHAT_ID    = '55001';
    private const SESSION_KEY = 'telegram_intake:55001:test-batch-job-bot';
    private const JOB_KEY     = 'telegram_batch_job:55001:test-batch-job-bot';

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del(self::SESSION_KEY);
        Redis::del(self::JOB_KEY);
        Redis::del("telegram_batch_id:" . self::SESSION_KEY);
    }

    protected function tearDown(): void
    {
        Redis::del(self::SESSION_KEY);
        Redis::del(self::JOB_KEY);
        Redis::del("telegram_batch_id:" . self::SESSION_KEY);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 1: assembleCombinedUpdate assembles 3 texts + 1 image correctly
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function assembles_three_texts_and_one_image_into_combined_update(): void
    {
        $session = [
            'chatId'    => self::CHAT_ID,
            'botToken'  => self::BOT_TOKEN,
            'texts'     => ['Hello', 'World', 'Tạo workflow'],
            'images'    => [
                ['file_id' => 'photo-abc', 'width' => 800, 'height' => 600],
            ],
            'messages'  => [
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => ['id' => 1], 'date' => 1000, 'message_id' => 1],
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => ['id' => 1], 'date' => 1001, 'message_id' => 2],
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => ['id' => 1], 'date' => 1002, 'message_id' => 3],
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => ['id' => 1], 'date' => 1003, 'message_id' => 4],
            ],
            'startedAt' => '2026-04-22T10:00:00+00:00',
        ];

        $job     = new ProcessTelegramBatchJob(self::SESSION_KEY, self::BOT_TOKEN, self::CHAT_ID);
        $combined = $job->assembleCombinedUpdate($session);

        // Text joined with double-newline.
        $this->assertSame("Hello\n\nWorld\n\nTạo workflow", $combined['message']['text']);

        // Photo array preserved.
        $this->assertCount(1, $combined['message']['photo']);
        $this->assertSame('photo-abc', $combined['message']['photo'][0]['file_id']);

        // _intake metadata present.
        $this->assertIsArray($combined['message']['_intake']);
        $this->assertSame(4, $combined['message']['_intake']['messageCount']); // 4 raw messages
        $this->assertSame(1, $combined['message']['_intake']['imageCount']);
        $this->assertSame(['Hello', 'World', 'Tạo workflow'], $combined['message']['_intake']['textParts']);
        $this->assertSame('2026-04-22T10:00:00+00:00', $combined['message']['_intake']['startedAt']);

        // update_id present.
        $this->assertArrayHasKey('update_id', $combined);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 2: Calls TelegramAgent::handle() exactly once per burst
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function calls_agent_handle_exactly_once_per_burst(): void
    {
        Http::fake();

        $session = [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
            'status'   => 'buffering',
            'texts'    => ['tạo kịch bản'],
            'images'   => [],
            'messages' => [
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 1],
            ],
            'startedAt' => now()->toIso8601String(),
        ];

        Redis::set(self::SESSION_KEY, json_encode($session), 'EX', 120);

        // Spy on TelegramAgentFactory.
        $calls = [];
        $spy   = new class($calls) {
            public function __construct(private array &$calls) {}

            public function handle(array $update, string $botToken): void
            {
                $this->calls[] = ['update' => $update, 'botToken' => $botToken];
            }
        };

        $this->app->bind(TelegramAgentFactory::class, function () use ($spy) {
            return new class($spy) {
                public function __construct(private object $spy) {}

                public function make(string $chatId, string $botToken): object
                {
                    return $this->spy;
                }
            };
        });

        $job = new ProcessTelegramBatchJob(self::SESSION_KEY, self::BOT_TOKEN, self::CHAT_ID);
        $job->handle();

        $this->assertCount(1, $calls, 'Agent must be called exactly once per burst');
        $this->assertSame(self::BOT_TOKEN, $calls[0]['botToken']);
        $this->assertStringContainsString('tạo kịch bản', $calls[0]['update']['message']['text']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 3: Superseded batch ID → no-op (FX-05 regression guard)
    //
    // Reproduces the bug the reviewer flagged: when message 2 arrives while
    // message 1's job is still delayed, bufferMessage() overwrites the
    // "latest batch" pointer. The OLD job must see the mismatch on wake-up
    // and skip — otherwise it processes prematurely.
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function superseded_batch_id_causes_no_op(): void
    {
        Http::fake();

        $session = [
            'status'   => 'buffering',
            'texts'    => ['hello'],
            'images'   => [],
            'messages' => [],
        ];
        Redis::set(self::SESSION_KEY, json_encode($session), 'EX', 120);

        // Latest pointer in Redis — set by the newer message 2's bufferMessage() call.
        Redis::set(self::JOB_KEY, 'batch_NEW_FROM_MESSAGE_2');

        $calls = [];
        $this->app->bind(TelegramAgentFactory::class, function () use (&$calls) {
            return new class($calls) {
                public function __construct(private array &$calls) {}
                public function make(string $chatId, string $botToken): object
                {
                    return new class($this->calls) {
                        public function __construct(private array &$calls) {}
                        public function handle(array $update, string $botToken): void { $this->calls[] = true; }
                    };
                }
            };
        });

        // This job was dispatched with an OLD batchId when message 1 arrived.
        $job = new ProcessTelegramBatchJob(
            self::SESSION_KEY,
            self::BOT_TOKEN,
            self::CHAT_ID,
            'batch_OLD_FROM_MESSAGE_1',
        );
        $job->handle();

        $this->assertCount(0, $calls, 'Superseded job must not invoke the agent');
    }

    #[Test]
    public function current_batch_id_is_processed(): void
    {
        Http::fake();

        $session = [
            'chatId'    => self::CHAT_ID,
            'botToken'  => self::BOT_TOKEN,
            'status'    => 'buffering',
            'texts'     => ['only message'],
            'images'    => [],
            'messages'  => [
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 1],
            ],
            'startedAt' => now()->toIso8601String(),
        ];
        Redis::set(self::SESSION_KEY, json_encode($session), 'EX', 120);

        // Latest pointer matches this job's batchId — this IS the current batch.
        Redis::set(self::JOB_KEY, 'batch_CURRENT');

        $calls = [];
        $this->app->bind(TelegramAgentFactory::class, function () use (&$calls) {
            return new class($calls) {
                public function __construct(private array &$calls) {}
                public function make(string $chatId, string $botToken): object
                {
                    return new class($this->calls) {
                        public function __construct(private array &$calls) {}
                        public function handle(array $update, string $botToken): void { $this->calls[] = true; }
                    };
                }
            };
        });

        $job = new ProcessTelegramBatchJob(
            self::SESSION_KEY,
            self::BOT_TOKEN,
            self::CHAT_ID,
            'batch_CURRENT',
        );
        $job->handle();

        $this->assertCount(1, $calls, 'Current-batch job must invoke the agent exactly once');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Case 4: Agent exception → fallback reply sent
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function agent_exception_sends_fallback_reply(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $session = [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
            'status'   => 'buffering',
            'texts'    => ['trigger error'],
            'images'   => [],
            'messages' => [
                ['chat' => ['id' => (int) self::CHAT_ID], 'from' => [], 'date' => time(), 'message_id' => 1],
            ],
            'startedAt' => now()->toIso8601String(),
        ];

        Redis::set(self::SESSION_KEY, json_encode($session), 'EX', 120);

        $this->app->bind(TelegramAgentFactory::class, function () {
            return new class {
                public function make(string $chatId, string $botToken): object
                {
                    return new class {
                        public function handle(array $update, string $botToken): void
                        {
                            throw new \RuntimeException('Simulated agent failure');
                        }
                    };
                }
            };
        });

        $job = new ProcessTelegramBatchJob(self::SESSION_KEY, self::BOT_TOKEN, self::CHAT_ID);
        $job->handle();

        // Verify a Telegram sendMessage was sent (the fallback reply).
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage');
        });

        // Session should have been cleared.
        $this->assertNull(Redis::get(self::SESSION_KEY));
    }
}
