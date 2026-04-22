<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\Tools\RefinePlanTool;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RefinePlanToolTest extends TestCase
{
    private const CHAT_ID   = '8888';
    private const BOT_TOKEN = 'refine-tool-test-bot';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.default', 'fireworks');
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
    }

    protected function tearDown(): void
    {
        Redis::del("ai_session:" . self::CHAT_ID . ":" . self::BOT_TOKEN);
        parent::tearDown();
    }

    private function makeTool(): RefinePlanTool
    {
        return app()->make(RefinePlanTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
    }

    private function chatCompletionBody(string $assistantText): array
    {
        return [
            'id'      => 'cmpl_test',
            'object'  => 'chat.completion',
            'created' => time(),
            'model'   => 'accounts/fireworks/models/minimax-m2p7',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $assistantText],
                'finish_reason' => 'stop',
            ]],
            'usage'   => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
        ];
    }

    /**
     * Minimal valid plan fixture: userPrompt → scriptWriter.
     * Matches the shape used in ComposeWorkflowToolTest.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function validPlanArray(array $overrides = []): array
    {
        $base = [
            'intent'   => 'original brief',
            'vibeMode' => 'funny_storytelling',
            'nodes'    => [
                [
                    'id'     => 'seed',
                    'type'   => 'userPrompt',
                    'config' => ['prompt' => 'Test brief for composition.'],
                    'reason' => 'Pass the brief through as the script prompt.',
                    'label'  => null,
                ],
                [
                    'id'     => 'script',
                    'type'   => 'scriptWriter',
                    'config' => [
                        'provider'              => 'stub',
                        'apiKey'                => '',
                        'model'                 => 'gpt-4o',
                        'style'                 => 'Soft-sell storytelling.',
                        'structure'             => 'story_arc',
                        'includeHook'           => true,
                        'includeCTA'            => false,
                        'targetDurationSeconds' => 30,
                        'hook_intensity'        => 'high',
                        'narrative_tension'     => 'high',
                        'product_emphasis'      => 'subtle',
                        'cta_softness'          => 'soft',
                        'native_tone'           => 'genz_native',
                    ],
                    'reason' => 'Write the script.',
                    'label'  => null,
                ],
            ],
            'edges' => [[
                'sourceNodeId'  => 'seed',
                'sourcePortKey' => 'prompt',
                'targetNodeId'  => 'script',
                'targetPortKey' => 'prompt',
                'reason'        => 'Script consumes the seed prompt.',
            ]],
            'assumptions' => ['Platform: TikTok 9:16'],
            'rationale'   => 'Short rationale.',
            'meta'        => ['plannerVersion' => '1.0'],
        ];

        return array_replace_recursive($base, $overrides);
    }

    private function seedSession(array $pendingPlan, int $attempts): void
    {
        $store = app(AgentSessionStore::class);
        $session = new AgentSession(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            pendingPlan: $pendingPlan,
            pendingPlanAttempts: $attempts,
        );
        $store->save($session);
    }

    #[Test]
    public function happy_path_refines_plan_and_increments_attempts(): void
    {
        // Seed session with a prior plan (attempt=1).
        $this->seedSession($this->validPlanArray(), attempts: 1);

        // Faked LLM returns an updated plan with a different vibeMode.
        $refined = $this->validPlanArray(['vibeMode' => 'aesthetic_mood']);
        Http::fake([
            'api.fireworks.ai/*' => Http::response(
                $this->chatCompletionBody(json_encode($refined, JSON_UNESCAPED_UNICODE)),
                200,
            ),
        ]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request(['feedback' => 'thêm humor nhẹ'])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($result['available'] ?? false, 'Expected available:true, got: ' . json_encode($result));
        $this->assertSame(2, $result['attempt']);
        $this->assertSame(3, $result['remaining']);
        $this->assertSame('aesthetic_mood', $result['vibeMode']);

        $session = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertSame(2, $session->pendingPlanAttempts);
        $this->assertIsArray($session->pendingPlan);
        $this->assertSame('aesthetic_mood', $session->pendingPlan['vibeMode']);
    }

    #[Test]
    public function no_pending_plan_returns_error(): void
    {
        Http::fake(['api.fireworks.ai/*' => Http::response([], 200)]);

        // No session seeded — pendingPlan is null.
        $result = json_decode(
            (string) $this->makeTool()->handle(new Request(['feedback' => 'thêm humor nhẹ'])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('no_pending_plan', $result['error']);
        $this->assertArrayHasKey('message', $result);

        // No LLM call made.
        Http::assertNotSent(function (HttpRequest $req) {
            return str_contains($req->url(), 'fireworks.ai');
        });
    }

    #[Test]
    public function refinement_cap_reached_blocks_sixth_call(): void
    {
        $this->seedSession($this->validPlanArray(), attempts: RefinePlanTool::REFINEMENT_CAP);

        Http::fake(['api.fireworks.ai/*' => Http::response([], 200)]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request(['feedback' => 'đổi vibe aesthetic'])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('refinement_cap_reached', $result['error']);
        $this->assertSame(RefinePlanTool::REFINEMENT_CAP, $result['cap']);

        Http::assertNotSent(function (HttpRequest $req) {
            return str_contains($req->url(), 'fireworks.ai');
        });
    }

    #[Test]
    public function validator_rejects_refined_plan_with_unknown_node_type(): void
    {
        $this->seedSession($this->validPlanArray(), attempts: 1);

        // LLM returns a plan with an unknown node type — validator rejects it.
        $bad = $this->validPlanArray();
        $bad['nodes'][1]['type'] = 'thisNodeDoesNotExist';
        Http::fake([
            'api.fireworks.ai/*' => Http::response(
                $this->chatCompletionBody(json_encode($bad, JSON_UNESCAPED_UNICODE)),
                200,
            ),
        ]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request(['feedback' => 'đổi node scriptWriter'])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('validation_failed', $result['error']);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);

        // Session NOT updated (attempts stays at 1).
        $session = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertSame(1, $session->pendingPlanAttempts);
    }

    #[Test]
    public function prior_plan_is_inlined_in_llm_prompt(): void
    {
        // Seed with a specific vibeMode marker.
        $plan = $this->validPlanArray(['vibeMode' => 'funny_storytelling']);
        $this->seedSession($plan, attempts: 1);

        Http::fake([
            'api.fireworks.ai/*' => Http::response(
                $this->chatCompletionBody(json_encode($this->validPlanArray(), JSON_UNESCAPED_UNICODE)),
                200,
            ),
        ]);

        $this->makeTool()->handle(new Request(['feedback' => 'thêm humor nhẹ']));

        // Assert the LLM request body contained the prior vibeMode (prior plan inlined).
        // Body is JSON — Vietnamese chars get \uXXXX escaped, so we probe for the
        // prior vibeMode literal and the feedback via its ASCII fragment.
        Http::assertSent(function (HttpRequest $req) {
            if (!str_contains($req->url(), 'fireworks.ai')) {
                return false;
            }
            $body = (string) $req->body();
            return str_contains($body, 'funny_storytelling')
                && (str_contains($body, 'humor') || str_contains($body, 'th\u00eam'));
        });
    }
}
