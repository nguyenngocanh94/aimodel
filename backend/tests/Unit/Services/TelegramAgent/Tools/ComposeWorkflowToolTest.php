<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ComposeWorkflowToolTest extends TestCase
{
    private const CHAT_ID   = '7777';
    private const BOT_TOKEN = 'compose-tool-test-bot';

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

    private function makeTool(): ComposeWorkflowTool
    {
        return app()->make(ComposeWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
    }

    /** Wraps assistant text in an OpenAI-style chat completion body. */
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
     * Minimal plan JSON that passes WorkflowPlanValidator: userPrompt → scriptWriter.
     */
    private function validPlanJson(?string $rationale = null): string
    {
        return json_encode([
            'intent'   => 'test brief',
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
            'rationale'   => $rationale ?? 'Short rationale.',
            'meta'        => ['plannerVersion' => '1.0'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    #[Test]
    public function description_mentions_draft_without_persisting(): void
    {
        $desc = (string) $this->makeTool()->description();
        $this->assertStringContainsStringIgnoringCase('draft', $desc);
        $this->assertStringContainsStringIgnoringCase('without persisting', $desc);
    }

    #[Test]
    public function schema_declares_required_brief_string(): void
    {
        $fields = $this->makeTool()->schema(new JsonSchemaTypeFactory());
        $this->assertArrayHasKey('brief', $fields);
    }

    #[Test]
    public function happy_path_calls_planner_and_stores_in_session(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($this->validPlanJson()), 200),
        ]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'brief' => 'Tạo TVC 9:16 cho chocopie cho Gen Z thích meme.',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($result['available']);
        $this->assertSame('funny_storytelling', $result['vibeMode']);
        $this->assertIsArray($result['nodes']);
        $this->assertNotEmpty($result['nodes']);
        $this->assertArrayHasKey('knobCount', $result);
        $this->assertArrayHasKey('rationale', $result);

        // Session persisted.
        $session = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertIsArray($session->pendingPlan);
        $this->assertSame(1, $session->pendingPlanAttempts);
        $this->assertSame('funny_storytelling', $session->pendingPlan['vibeMode']);
    }

    #[Test]
    public function planner_failure_returns_available_false(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody('this is not JSON at all'), 200),
        ]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'brief' => 'Brief that will never parse successfully.',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertFalse($result['available']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertNotEmpty($result['reason']);

        // Session should NOT have pendingPlan set.
        $session = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertNull($session->pendingPlan);
        $this->assertSame(0, $session->pendingPlanAttempts);
    }

    #[Test]
    public function brief_too_short_returns_available_false(): void
    {
        Http::fake(['api.fireworks.ai/*' => Http::response([], 200)]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request(['brief' => 'abc'])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('ngắn', (string) $result['reason']);

        Http::assertNothingSent();
    }

    #[Test]
    public function returned_summary_has_truncated_rationale(): void
    {
        $longRationale = str_repeat('A', 1000);
        Http::fake([
            'api.fireworks.ai/*' => Http::response(
                $this->chatCompletionBody($this->validPlanJson($longRationale)),
                200,
            ),
        ]);

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'brief' => 'Brief exercising rationale truncation.',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($result['available']);
        $this->assertLessThanOrEqual(400, mb_strlen((string) $result['rationale']));
    }

    #[Test]
    public function session_is_created_fresh_if_none_exists(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($this->validPlanJson()), 200),
        ]);

        // Confirm nothing exists before.
        $before = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertNull($before->pendingPlan);

        $this->makeTool()->handle(new Request([
            'brief' => 'Brand new session composition brief goes here.',
        ]));

        $after = app(AgentSessionStore::class)->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertIsArray($after->pendingPlan);
        $this->assertSame(1, $after->pendingPlanAttempts);
    }
}
