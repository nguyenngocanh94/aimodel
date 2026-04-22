<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TG-04: AgentSessionStore::readPendingDraft() — core primitive for debounce selector.
 *
 * The debounce window selector (bufferMessage / direct agent call) uses this method
 * to determine whether a pending draft exists. We test the store method directly.
 */
final class TelegramWebhookDebounceWindowTest extends TestCase
{
    private const BOT_TOKEN = 'test-debounce-bot';
    private const CHAT_ID   = '77001';

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
    }

    protected function tearDown(): void
    {
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        parent::tearDown();
    }

    #[Test]
    public function read_pending_draft_returns_false_when_no_session_exists(): void
    {
        $store = new AgentSessionStore();

        $hasDraft = $store->readPendingDraft(self::CHAT_ID, self::BOT_TOKEN);

        $this->assertFalse($hasDraft,
            'readPendingDraft must return false when no ai_session key exists in Redis'
        );
    }

    #[Test]
    public function read_pending_draft_returns_false_when_session_has_no_plan(): void
    {
        $store   = new AgentSessionStore();
        $session = new AgentSession(chatId: self::CHAT_ID, botToken: self::BOT_TOKEN);
        // pendingPlan is null by default.
        $store->save($session);

        $hasDraft = $store->readPendingDraft(self::CHAT_ID, self::BOT_TOKEN);

        $this->assertFalse($hasDraft,
            'readPendingDraft must return false when session exists but pendingPlan is null'
        );
    }

    #[Test]
    public function read_pending_draft_returns_true_when_pending_plan_is_set(): void
    {
        $store   = new AgentSessionStore();
        $session = new AgentSession(chatId: self::CHAT_ID, botToken: self::BOT_TOKEN);
        $session->pendingPlan = [
            'intent'      => 'test draft',
            'vibeMode'    => 'clean_education',
            'nodes'       => [],
            'edges'       => [],
            'assumptions' => [],
            'rationale'   => 'test',
            'meta'        => [],
        ];
        $store->save($session);

        $hasDraft = $store->readPendingDraft(self::CHAT_ID, self::BOT_TOKEN);

        $this->assertTrue($hasDraft,
            'readPendingDraft must return true when session has a non-null pendingPlan'
        );
    }
}
