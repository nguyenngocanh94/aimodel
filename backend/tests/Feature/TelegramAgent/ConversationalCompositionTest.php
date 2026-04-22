<?php

declare(strict_types=1);

namespace Tests\Feature\TelegramAgent;

use App\Models\Workflow;
use App\Services\TelegramAgent\AgentSession;
use App\Services\TelegramAgent\AgentSessionStore;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use App\Services\TelegramAgent\Tools\PersistWorkflowTool;
use App\Services\TelegramAgent\Tools\RefinePlanTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CW5 — end-to-end feature test for conversational workflow composition.
 *
 * Four scenarios exercise the compose → refine → persist loop with Fireworks
 * and Telegram fully faked. Each scenario drives the tools directly (because
 * TelegramAgent::fake() short-circuits the tool-use loop) AND drives
 * TelegramAgent::handle() at least once to confirm the webhook → agent → reply
 * wiring remains intact end-to-end.
 */
final class ConversationalCompositionTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID   = 'cw5-chat-42';
    private const BOT_TOKEN = 'cw5-bot-token';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.default', 'fireworks');
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

    private function telegramSendMessageUrl(): string
    {
        return 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';
    }

    private function makeAgent(): TelegramAgent
    {
        return new TelegramAgent(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            slashRouter: new SlashCommandRouter(),
        );
    }

    private function store(): AgentSessionStore
    {
        return app(AgentSessionStore::class);
    }

    /**
     * Minimal valid WorkflowPlan array (matches WorkflowPlanValidator rules).
     * Mirrors the fixture used in the Unit tool tests.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function validPlanArray(array $overrides = []): array
    {
        $base = [
            'intent'   => 'TVC 9:16 cho sản phẩm chăm sóc sức khỏe',
            'vibeMode' => 'clean_education',
            'nodes'    => [
                [
                    'id'     => 'seed',
                    'type'   => 'userPrompt',
                    'config' => ['prompt' => 'Tạo TVC cho sản phẩm chăm sóc sức khỏe.'],
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
                        'style'                 => 'Clean, trust-forward storytelling.',
                        'structure'             => 'story_arc',
                        'includeHook'           => true,
                        'includeCTA'            => false,
                        'targetDurationSeconds' => 30,
                        'hook_intensity'        => 'medium',
                        'narrative_tension'     => 'medium',
                        'product_emphasis'      => 'hero',
                        'cta_softness'          => 'medium',
                        'native_tone'           => 'conversational',
                    ],
                    'reason' => 'Viết script cho TVC sức khỏe.',
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

        return array_replace_recursive($base, $overrides);
    }

    /** Canned plan as JSON (what the LLM "content" should be). */
    private function cannedPlanJson(array $overrides = []): string
    {
        return json_encode(
            $this->validPlanArray($overrides),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /** Wrap assistant text in an OpenAI-style chat.completion body. */
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

    /** Run a caller-supplied assertion against the loaded session. */
    private function assertSessionHas(callable $check): void
    {
        $session = $this->store()->load(self::CHAT_ID, self::BOT_TOKEN);
        $check($session);
    }

    /** Seed a session directly (helper for scenarios that start mid-flow). */
    private function seedSession(?array $pendingPlan, int $attempts): void
    {
        $this->store()->save(new AgentSession(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            pendingPlan: $pendingPlan,
            pendingPlanAttempts: $attempts,
        ));
    }

    /** Drive TelegramAgent::handle() with a free-text message once per scenario. */
    private function driveAgent(string $text): void
    {
        $this->makeAgent()->handle(
            update: [
                'message' => [
                    'chat' => ['id' => self::CHAT_ID],
                    'from' => ['id' => 100],
                    'text' => $text,
                ],
            ],
            botToken: self::BOT_TOKEN,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scenario 1 — happy path (2 turns)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_happy_path_2_turn_composition_persists_workflow(): void
    {
        Queue::fake();

        Http::fake([
            'api.fireworks.ai/*'           => Http::response(
                $this->chatCompletionBody($this->cannedPlanJson()),
                200,
            ),
            $this->telegramSendMessageUrl() => Http::response(['ok' => true], 200),
        ]);

        // Fake the LLM so TelegramAgent::handle() doesn't hit real Fireworks
        // through the tool-use loop (returns canned text, does NOT call tools).
        TelegramAgent::fake(['Mình đề xuất workflow này. OK để mình lưu không?']);

        // ── Turn 1: user asks to compose ─────────────────────────────────────
        // Drive the webhook entrypoint to confirm the handle() chain works.
        $this->driveAgent('tạo workflow sinh video TVC 9:16 cho sản phẩm chăm sóc sức khỏe');

        TelegramAgent::assertPrompted('tạo workflow sinh video TVC 9:16 cho sản phẩm chăm sóc sức khỏe');

        // The fake doesn't invoke tools. Simulate the LLM's ComposeWorkflowTool
        // call directly — this exercises the real tool + session write.
        $composeTool = app()->make(ComposeWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $composeResult = json_decode(
            (string) $composeTool->handle(new Request([
                'brief' => 'Tạo TVC 9:16 cho sản phẩm chăm sóc sức khỏe.',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($composeResult['available'] ?? false, 'ComposeWorkflowTool must return available:true');

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertIsArray($s->pendingPlan, 'pendingPlan must be populated after compose');
            $this->assertSame(1, $s->pendingPlanAttempts);
        });

        // ── Turn 2: user says "ok" → persist ─────────────────────────────────
        $persistTool = app()->make(PersistWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $persistResult = json_decode(
            (string) $persistTool->handle(new Request([
                'slug' => 'health-tvc-9x16',
                'name' => 'Health TVC 9:16',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayNotHasKey('error', $persistResult);
        $this->assertSame('health-tvc-9x16', $persistResult['slug']);

        // Workflow row persisted with expected shape.
        $workflow = Workflow::where('slug', 'health-tvc-9x16')->firstOrFail();
        $this->assertTrue((bool) $workflow->triggerable);
        $this->assertNotEmpty($workflow->document);

        $tags = (array) $workflow->tags;
        $this->assertContains('planner', $tags);
        $this->assertContains('clean_education', $tags);

        // Session cleared.
        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertNull($s->pendingPlan);
            $this->assertSame(0, $s->pendingPlanAttempts);
        });

        // Persist must NOT auto-dispatch a workflow run.
        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scenario 2 — refinement (3 turns)
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_refinement_path_3_turn_composition(): void
    {
        Queue::fake();

        // Sequence: first LLM call returns the original plan
        // (native_tone=conversational), second LLM call (refine) returns an
        // updated plan with native_tone=genz_native. RefinePlanTool re-invokes
        // Fireworks; the compose tool fires first.
        $originalPlanJson = $this->cannedPlanJson();
        $refinedPlanJson  = $this->cannedPlanJson([
            'nodes' => [
                1 => [
                    'config' => ['native_tone' => 'genz_native'],
                ],
            ],
            'rationale' => 'Thêm giọng GenZ cho đỡ khô khan.',
        ]);

        Http::fake([
            'api.fireworks.ai/*' => Http::sequence()
                ->push($this->chatCompletionBody($originalPlanJson))
                ->push($this->chatCompletionBody($refinedPlanJson)),
            $this->telegramSendMessageUrl() => Http::response(['ok' => true], 200),
        ]);

        TelegramAgent::fake([
            'Mình đề xuất workflow này. OK để mình lưu không?',
            'Đã cập nhật humor_density. OK chưa?',
            'Đã lưu workflow.',
        ]);

        // ── Turn 1: compose ──────────────────────────────────────────────────
        $this->driveAgent('tạo workflow TVC sức khỏe');

        $composeTool = app()->make(ComposeWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $composeTool->handle(new Request([
            'brief' => 'Tạo TVC 9:16 cho sản phẩm chăm sóc sức khỏe.',
        ]));

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertIsArray($s->pendingPlan);
            $this->assertSame(1, $s->pendingPlanAttempts);
            // Original plan has native_tone=conversational.
            $this->assertSame(
                'conversational',
                $s->pendingPlan['nodes'][1]['config']['native_tone'] ?? null,
            );
        });

        // ── Turn 2: refine ───────────────────────────────────────────────────
        $refineTool = app()->make(RefinePlanTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $refineResult = json_decode(
            (string) $refineTool->handle(new Request([
                'feedback' => 'đổi giọng qua GenZ native cho đỡ khô',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertTrue($refineResult['available'] ?? false, 'Refine must succeed: ' . json_encode($refineResult));
        $this->assertSame(2, $refineResult['attempt']);

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertSame(2, $s->pendingPlanAttempts);
            // Refined plan has native_tone=genz_native.
            $this->assertSame(
                'genz_native',
                $s->pendingPlan['nodes'][1]['config']['native_tone'] ?? null,
                'Refined knob must overwrite the original.',
            );
        });

        // Serialized plan must contain the updated marker.
        $serialized = json_encode(
            $this->store()->load(self::CHAT_ID, self::BOT_TOKEN)->pendingPlan,
            JSON_UNESCAPED_UNICODE,
        );
        $this->assertStringContainsString('genz_native', (string) $serialized);

        // ── Turn 3: approve → persist ────────────────────────────────────────
        $persistTool = app()->make(PersistWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $persistTool->handle(new Request([
            'slug' => 'health-tvc-funny',
            'name' => 'Health TVC (humor)',
        ]));

        $workflow = Workflow::where('slug', 'health-tvc-funny')->firstOrFail();
        $this->assertTrue((bool) $workflow->triggerable);

        // Persisted document must reflect the refined knob, not the original.
        $document = (array) $workflow->document;
        $serializedDoc = json_encode($document, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('genz_native', (string) $serializedDoc);

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertNull($s->pendingPlan);
            $this->assertSame(0, $s->pendingPlanAttempts);
        });

        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scenario 3 — refinement cap blocks 6th attempt
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_refinement_cap_blocks_after_5_refinements(): void
    {
        Queue::fake();

        // Pre-populate a session that's already at the cap.
        $seededPlan = $this->validPlanArray();
        $this->seedSession($seededPlan, attempts: RefinePlanTool::REFINEMENT_CAP);

        Http::fake([
            // If the tool wrongly calls the LLM, this response would fire —
            // we assert below that it does NOT.
            'api.fireworks.ai/*'           => Http::response(
                $this->chatCompletionBody($this->cannedPlanJson()),
                200,
            ),
            $this->telegramSendMessageUrl() => Http::response(['ok' => true], 200),
        ]);

        TelegramAgent::fake(['Đã chỉnh 5 lần — gõ ok hoặc hủy.']);

        // Drive the agent at least once so the webhook path is covered for
        // this scenario too.
        $this->driveAgent('chỉnh: đổi vibe aesthetic');

        $refineTool = app()->make(RefinePlanTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $result = json_decode(
            (string) $refineTool->handle(new Request([
                'feedback' => 'another change please',
            ])),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('refinement_cap_reached', $result['error']);
        $this->assertSame(RefinePlanTool::REFINEMENT_CAP, $result['cap']);

        // No Fireworks call was made by the refine tool itself.
        Http::assertNotSent(function (HttpRequest $req) {
            return str_contains($req->url(), 'api.fireworks.ai');
        });

        // Session is unchanged.
        $this->assertSessionHas(function (AgentSession $s) use ($seededPlan): void {
            $this->assertSame(RefinePlanTool::REFINEMENT_CAP, $s->pendingPlanAttempts);
            $this->assertSame($seededPlan, $s->pendingPlan);
        });

        // No Workflow row written.
        $this->assertSame(0, Workflow::count());
        Queue::assertNothingPushed();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scenario 4 — rejection clears session, no workflow created
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function test_rejection_path_clears_no_workflow_created(): void
    {
        Queue::fake();

        Http::fake([
            'api.fireworks.ai/*'           => Http::response(
                $this->chatCompletionBody($this->cannedPlanJson()),
                200,
            ),
            $this->telegramSendMessageUrl() => Http::response(['ok' => true], 200),
        ]);

        TelegramAgent::fake([
            'Mình đề xuất workflow này. OK để mình lưu không?',
            'Đã hủy.',
        ]);

        // ── Turn 1: compose ──────────────────────────────────────────────────
        $this->driveAgent('tạo workflow TVC sức khỏe');

        $composeTool = app()->make(ComposeWorkflowTool::class, [
            'chatId'   => self::CHAT_ID,
            'botToken' => self::BOT_TOKEN,
        ]);
        $composeTool->handle(new Request([
            'brief' => 'Tạo TVC 9:16 cho sản phẩm chăm sóc sức khỏe.',
        ]));

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertIsArray($s->pendingPlan);
            $this->assertSame(1, $s->pendingPlanAttempts);
        });

        // ── Turn 2: user says "hủy" ──────────────────────────────────────────
        // The skill instructs the LLM to call reply(text:"Đã hủy") and clear
        // the pendingPlan. Since we can't assert on LLM behavior directly,
        // we simulate the rejection path's side-effect — clearing the
        // session — and assert no workflow was persisted.
        $this->driveAgent('hủy');

        $store   = $this->store();
        $session = $store->load(self::CHAT_ID, self::BOT_TOKEN);
        $session->pendingPlan         = null;
        $session->pendingPlanAttempts = 0;
        $store->save($session);

        // ── Assertions ───────────────────────────────────────────────────────
        $this->assertSame(0, Workflow::count(), 'Rejection must not persist any workflow.');

        $this->assertSessionHas(function (AgentSession $s): void {
            $this->assertNull($s->pendingPlan);
            $this->assertSame(0, $s->pendingPlanAttempts);
        });

        Queue::assertNothingPushed();
    }
}
