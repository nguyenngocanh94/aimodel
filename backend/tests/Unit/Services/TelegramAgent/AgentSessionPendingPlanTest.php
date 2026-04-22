<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AgentSessionPendingPlanTest extends TestCase
{
    private const CHAT_ID   = '9001';
    private const BOT_TOKEN = 'unit-test-bot';

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
    public function session_round_trips_pendingPlan_through_store(): void
    {
        $store = new AgentSessionStore();

        $session = new AgentSession(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
        );
        $session->pendingPlan = [
            'intent'   => 'chocopie tvc 9:16',
            'vibeMode' => 'funny_storytelling',
            'nodes'    => [
                ['id' => 'n1', 'type' => 'productAnalyzer', 'config' => ['emphasis' => 'hero_moment'], 'reason' => 'anchor USP', 'label' => null],
            ],
            'edges'       => [],
            'assumptions' => ['gen_z_audience'],
            'rationale'   => 'test rationale',
            'meta'        => ['plannerVersion' => 'v1'],
        ];
        $session->pendingPlanAttempts = 1;

        $store->save($session);

        $fresh = $store->load(self::CHAT_ID, self::BOT_TOKEN);

        $this->assertSame($session->pendingPlan, $fresh->pendingPlan);
        $this->assertSame(1, $fresh->pendingPlanAttempts);
    }

    #[Test]
    public function fresh_session_has_null_pendingPlan_and_zero_attempts(): void
    {
        $store = new AgentSessionStore();

        $fresh = $store->load(self::CHAT_ID, self::BOT_TOKEN);

        $this->assertNull($fresh->pendingPlan);
        $this->assertSame(0, $fresh->pendingPlanAttempts);
        $this->assertSame(self::CHAT_ID, $fresh->chatId);
        $this->assertSame(self::BOT_TOKEN, $fresh->botToken);
    }

    #[Test]
    public function saved_session_preserves_pendingPlan_serialization_shape(): void
    {
        $store = new AgentSessionStore();

        $plan = [
            'intent'   => 'nested test',
            'vibeMode' => 'clean_education',
            'nodes'    => [
                [
                    'id'     => 'n1',
                    'type'   => 'storyWriter',
                    'config' => [
                        'toneKnobs' => ['humor_density' => 'none', 'edit_pace' => 'steady'],
                        'threshold' => 0.75,
                        'tags'      => ['a', 'b', 'c'],
                    ],
                    'reason' => 'why',
                    'label'  => null,
                ],
            ],
            'edges'       => [['source' => 'n1', 'target' => 'n2', 'reason' => 'seq']],
            'assumptions' => [],
            'rationale'   => 'r',
            'meta'        => [],
        ];

        $session = new AgentSession(chatId: self::CHAT_ID, botToken: self::BOT_TOKEN);
        $session->pendingPlan = $plan;
        $session->pendingPlanAttempts = 3;
        $store->save($session);

        $fresh = $store->load(self::CHAT_ID, self::BOT_TOKEN);

        // Verify nested structure round-trips as arrays, not stringified JSON.
        $this->assertIsArray($fresh->pendingPlan);
        $this->assertIsArray($fresh->pendingPlan['nodes'][0]['config']['toneKnobs']);
        $this->assertSame('none', $fresh->pendingPlan['nodes'][0]['config']['toneKnobs']['humor_density']);
        $this->assertSame(['a', 'b', 'c'], $fresh->pendingPlan['nodes'][0]['config']['tags']);
        $this->assertSame(3, $fresh->pendingPlanAttempts);
    }
}
