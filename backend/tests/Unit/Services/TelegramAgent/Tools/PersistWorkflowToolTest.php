<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\Workflow;
use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use App\Services\TelegramAgent\Tools\PersistWorkflowTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PersistWorkflowToolTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID   = 'persist-tool-test-chat';
    private const BOT_TOKEN = 'persist-tool-test-bot';

    protected function setUp(): void
    {
        parent::setUp();
        Redis::del('ai_session:' . self::CHAT_ID . ':' . self::BOT_TOKEN);
    }

    protected function tearDown(): void
    {
        Redis::del('ai_session:' . self::CHAT_ID . ':' . self::BOT_TOKEN);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeTool(): PersistWorkflowTool
    {
        return app()->make(PersistWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
    }

    /**
     * Minimal valid WorkflowPlan array (matches WorkflowPlanValidator rules).
     * Based on the fixture used in ComposeWorkflowToolTest.
     */
    private function validPlanArray(string $vibeMode = 'funny_storytelling'): array
    {
        return [
            'intent'   => 'TVC 9:16 cho sản phẩm chăm sóc sức khỏe',
            'vibeMode' => $vibeMode,
            'nodes'    => [
                [
                    'id'     => 'seed',
                    'type'   => 'userPrompt',
                    'config' => ['prompt' => 'Test brief for persist tool.'],
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
                    'reason' => 'Write the TVC script.',
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
            'rationale'   => 'Sức khỏe cần tin cậy, hero_moment tăng tín nhiệm.',
            'meta'        => ['plannerVersion' => '1.0'],
        ];
    }

    /** Seed a session with a valid pending plan and return the store. */
    private function seedSessionWithPlan(string $vibeMode = 'funny_storytelling'): AgentSessionStore
    {
        $store   = app(AgentSessionStore::class);
        $session = new AgentSession(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            pendingPlan: $this->validPlanArray($vibeMode),
            pendingPlanAttempts: 1,
        );
        $store->save($session);

        return $store;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function happy_path_creates_triggerable_workflow_and_clears_session(): void
    {
        $store = $this->seedSessionWithPlan('clean_education');

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'slug' => 'test-tvc',
                'name' => 'Test TVC',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        // Response shape.
        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('test-tvc', $result['slug']);
        $this->assertSame('Test TVC', $result['name']);
        $this->assertTrue($result['triggerable']);
        $this->assertArrayHasKey('workflowId', $result);

        // Workflow persisted correctly.
        $workflow = Workflow::where('slug', 'test-tvc')->firstOrFail();
        $this->assertTrue($workflow->triggerable);
        $this->assertNotEmpty($workflow->document);

        $tags = (array) $workflow->tags;
        $this->assertContains('planner', $tags);
        $this->assertContains('v1', $tags);
        $this->assertContains('clean_education', $tags);

        // Session cleared.
        $session = $store->load(self::CHAT_ID, self::BOT_TOKEN);
        $this->assertNull($session->pendingPlan);
        $this->assertSame(0, $session->pendingPlanAttempts);
    }

    #[Test]
    public function no_pending_plan_returns_error(): void
    {
        // Do NOT seed a session — fresh / empty.
        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'slug' => 'test-tvc',
                'name' => 'Test TVC',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('no_pending_plan', $result['error']);

        // No Workflow row created.
        $this->assertSame(0, Workflow::count());
    }

    #[Test]
    public function slug_collision_with_non_planner_workflow_returns_slug_reserved(): void
    {
        // Create a non-planner workflow that owns 'test-tvc'.
        Workflow::create([
            'name'     => 'Demo Workflow',
            'slug'     => 'test-tvc',
            'document' => ['nodes' => [], 'edges' => []],
            'tags'     => ['demo'],
        ]);

        $this->seedSessionWithPlan();

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'slug' => 'test-tvc',
                'name' => 'Test TVC',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('slug_reserved', $result['error']);
        $this->assertSame('test-tvc-v2', $result['suggestion']);

        // Only the original pre-existing row — no new Workflow created.
        $this->assertSame(1, Workflow::count());
    }

    #[Test]
    public function slug_collision_with_planner_workflow_auto_appends_v2(): void
    {
        // Create an existing planner-owned workflow with the same slug.
        Workflow::create([
            'name'     => 'Existing Planner TVC',
            'slug'     => 'test-tvc',
            'document' => ['nodes' => [], 'edges' => []],
            'tags'     => ['planner', 'v1', 'funny_storytelling'],
        ]);

        $this->seedSessionWithPlan();

        $result = json_decode(
            (string) $this->makeTool()->handle(new Request([
                'slug' => 'test-tvc',
                'name' => 'Test TVC v2',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('test-tvc-v2', $result['slug']);

        // New row created with bumped slug.
        $this->assertTrue(Workflow::where('slug', 'test-tvc-v2')->exists());
    }

    #[Test]
    public function param_schema_defaults_to_productBrief_required_string(): void
    {
        $this->seedSessionWithPlan();

        $this->makeTool()->handle(new Request([
            'slug' => 'param-schema-test',
            'name' => 'Param Schema Test',
        ]));

        $workflow = Workflow::where('slug', 'param-schema-test')->firstOrFail();

        $this->assertSame(
            ['productBrief' => ['required', 'string', 'min:5']],
            $workflow->param_schema,
        );
    }

    #[Test]
    public function after_persist_list_workflows_tool_returns_new_workflow(): void
    {
        $this->seedSessionWithPlan();

        // Persist the workflow via the tool.
        $this->makeTool()->handle(new Request([
            'slug' => 'listed-tvc',
            'name' => 'Listed TVC',
        ]));

        // ListWorkflowsTool should now include the new workflow.
        $listResult = json_decode(
            (string) (new ListWorkflowsTool())->handle(new Request([])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey('workflows', $listResult);

        $slugs = array_column($listResult['workflows'], 'slug');
        $this->assertContains('listed-tvc', $slugs);
    }
}
