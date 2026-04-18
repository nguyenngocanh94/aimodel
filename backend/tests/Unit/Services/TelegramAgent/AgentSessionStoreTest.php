<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Tests for AgentSessionStore — Redis-backed session persistence.
 *
 * Uses a real Redis connection (redis-app-1 linked to backend-app-1).
 * Keys are namespaced under "test:telegram_agent:" via a sub-prefix on
 * chatId so they can be isolated and cleaned up.
 */
class AgentSessionStoreTest extends TestCase
{
    private AgentSessionStore $store;

    private string $chatId;

    private string $botToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new AgentSessionStore();

        // Unique per-test chatId to prevent cross-test pollution.
        $this->chatId   = 'test_' . uniqid('', true);
        $this->botToken = 'test_token_abc';
    }

    protected function tearDown(): void
    {
        // Clean up the key created by this test.
        Redis::del("telegram_agent:{$this->chatId}:{$this->botToken}");

        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Fresh load
    // ──────────────────────────────────────────────────────────────────────────

    public function test_fresh_load_returns_empty_session(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);

        $this->assertInstanceOf(AgentSession::class, $session);
        $this->assertSame($this->chatId, $session->chatId);
        $this->assertSame($this->botToken, $session->botToken);
        $this->assertEmpty($session->messages);
        $this->assertNull($session->pendingWorkflowSlug);
        $this->assertEmpty($session->collectedParams);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Round-trip save / load
    // ──────────────────────────────────────────────────────────────────────────

    public function test_save_then_load_round_trips_scalar_content(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);
        $session->appendMessage('user', 'Hello agent');
        $session->appendMessage('assistant', 'How can I help?');
        $session->pendingWorkflowSlug = 'story-writer-gated';
        $session->collectedParams     = ['productBrief' => 'Chocopie'];

        $this->store->save($session);

        $loaded = $this->store->load($this->chatId, $this->botToken);

        $this->assertCount(2, $loaded->messages);
        $this->assertSame('user',         $loaded->messages[0]['role']);
        $this->assertSame('Hello agent',  $loaded->messages[0]['content']);
        $this->assertSame('assistant',    $loaded->messages[1]['role']);
        $this->assertSame('How can I help?', $loaded->messages[1]['content']);
        $this->assertSame('story-writer-gated', $loaded->pendingWorkflowSlug);
        $this->assertSame(['productBrief' => 'Chocopie'], $loaded->collectedParams);
    }

    public function test_save_then_load_round_trips_structured_array_content(): void
    {
        $structuredContent = [
            ['type' => 'tool_use', 'id' => 'tu_123', 'name' => 'list_workflows', 'input' => []],
        ];

        $session = $this->store->load($this->chatId, $this->botToken);
        $session->appendMessage('assistant', $structuredContent);

        $this->store->save($session);

        $loaded = $this->store->load($this->chatId, $this->botToken);

        $this->assertCount(1, $loaded->messages);
        $this->assertSame('assistant', $loaded->messages[0]['role']);
        $this->assertIsArray($loaded->messages[0]['content']);
        $this->assertSame('tool_use', $loaded->messages[0]['content'][0]['type']);
        $this->assertSame('tu_123',   $loaded->messages[0]['content'][0]['id']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Trimming
    // ──────────────────────────────────────────────────────────────────────────

    public function test_save_trims_to_20_messages_oldest_dropped(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);

        // Append 25 messages numbered 1..25.
        for ($i = 1; $i <= 25; $i++) {
            $session->appendMessage('user', "message {$i}");
        }

        $this->store->save($session);

        $loaded = $this->store->load($this->chatId, $this->botToken);

        $this->assertCount(20, $loaded->messages);

        // The oldest 5 (messages 1–5) should have been dropped; first remaining is "message 6".
        $this->assertSame('message 6',  $loaded->messages[0]['content']);
        $this->assertSame('message 25', $loaded->messages[19]['content']);
    }

    public function test_exactly_20_messages_are_kept_intact(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);

        for ($i = 1; $i <= 20; $i++) {
            $session->appendMessage('user', "msg {$i}");
        }

        $this->store->save($session);

        $loaded = $this->store->load($this->chatId, $this->botToken);

        $this->assertCount(20, $loaded->messages);
        $this->assertSame('msg 1',  $loaded->messages[0]['content']);
        $this->assertSame('msg 20', $loaded->messages[19]['content']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // forget()
    // ──────────────────────────────────────────────────────────────────────────

    public function test_forget_removes_key_and_subsequent_load_returns_fresh(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);
        $session->appendMessage('user', 'do not persist');
        $this->store->save($session);

        // Confirm it was saved.
        $this->assertCount(1, $this->store->load($this->chatId, $this->botToken)->messages);

        // Forget it.
        $this->store->forget($this->chatId, $this->botToken);

        // Key should no longer exist in Redis.
        $raw = Redis::get("telegram_agent:{$this->chatId}:{$this->botToken}");
        $this->assertNull($raw);

        // Next load returns a fresh empty session.
        $fresh = $this->store->load($this->chatId, $this->botToken);
        $this->assertEmpty($fresh->messages);
        $this->assertNull($fresh->pendingWorkflowSlug);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TTL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_save_sets_ttl_of_approximately_3600_seconds(): void
    {
        $session = $this->store->load($this->chatId, $this->botToken);
        $this->store->save($session);

        $ttl = Redis::ttl("telegram_agent:{$this->chatId}:{$this->botToken}");

        // TTL should be within a 5-second window of 3600.
        $this->assertGreaterThanOrEqual(3595, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }
}
