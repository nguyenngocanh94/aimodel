<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Execution\TypeCompatibility;
use App\Domain\Nodes\ConfigSchemaTranspiler;
use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\PlannerInput;
use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlanValidator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the planner's retry loop, JSON parsing, and happy-path wiring
 * WITHOUT invoking the real Fireworks API (uses Http::fake).
 */
final class WorkflowPlannerTest extends TestCase
{
    private WorkflowPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'fireworks');

        /** @var NodeTemplateRegistry $registry */
        $registry = app(NodeTemplateRegistry::class);
        $typeCompat = new TypeCompatibility();
        $transpiler = new ConfigSchemaTranspiler();
        $validator = new WorkflowPlanValidator($registry, $typeCompat, $transpiler);
        $manifestBuilder = new NodeManifestBuilder($transpiler);

        $this->planner = new WorkflowPlanner($this->app, $registry, $manifestBuilder, $validator);
    }

    #[Test]
    public function happy_path_returns_valid_plan_on_first_attempt(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($this->validPlanJson()), 200),
        ]);

        $input = new PlannerInput(
            brief: 'A 30s English brief about a skincare serum — soft-sell storytelling.',
            maxRetries: 2,
        );

        $result = $this->planner->plan($input);

        $this->assertTrue(
            $result->successful(),
            'Expected successful plan; errors: ' . json_encode($result->validation->errors),
        );
        $this->assertSame(1, $result->attempts);
        $this->assertCount(1, $result->steps);
        $this->assertSame('funny_storytelling', $result->plan?->vibeMode);
        $this->assertSame('fireworks', $result->providerUsed);
    }

    #[Test]
    public function malformed_json_triggers_retry_then_succeeds(): void
    {
        Http::fakeSequence('api.fireworks.ai/*')
            ->push($this->chatCompletionBody('this is not { even json'), 200)
            ->push($this->chatCompletionBody($this->validPlanJson()), 200);

        $input = new PlannerInput(
            brief: 'Another English brief for retry testing.',
            maxRetries: 2,
        );

        $result = $this->planner->plan($input);

        $this->assertTrue(
            $result->successful(),
            'errors: ' . json_encode($result->validation->errors),
        );
        $this->assertSame(2, $result->attempts);
        $this->assertCount(2, $result->steps);
        $this->assertNotNull($result->steps[0]->parseError);
        $this->assertNull($result->steps[0]->parsedPlan);
        $this->assertNotNull($result->steps[1]->parsedPlan);
    }

    #[Test]
    public function all_retries_exhausted_yields_invalid_result(): void
    {
        $malformed = $this->chatCompletionBody('still not json');
        Http::fakeSequence('api.fireworks.ai/*')
            ->push($malformed, 200)
            ->push($malformed, 200)
            ->push($malformed, 200)
            ->push($malformed, 200);

        $input = new PlannerInput(
            brief: 'Brief that will never parse successfully in test.',
            maxRetries: 3, // => 4 attempts total
        );

        $result = $this->planner->plan($input);

        $this->assertFalse($result->successful());
        $this->assertSame(4, $result->attempts);
        $this->assertCount(4, $result->steps);
        foreach ($result->steps as $attempt) {
            $this->assertNull($attempt->parsedPlan);
            $this->assertNotNull($attempt->parseError);
        }
    }

    #[Test]
    public function structural_validation_failure_retries_with_error_feedback(): void
    {
        $cyclicPlan = $this->cyclicPlanJson();

        Http::fakeSequence('api.fireworks.ai/*')
            ->push($this->chatCompletionBody($cyclicPlan), 200)
            ->push($this->chatCompletionBody($this->validPlanJson()), 200);

        $input = new PlannerInput(
            brief: 'Brief that exercises the cycle-retry feedback loop.',
            maxRetries: 3,
        );

        $result = $this->planner->plan($input);

        $this->assertTrue(
            $result->successful(),
            'Expected eventual success. errors: ' . json_encode($result->validation->errors),
        );
        $this->assertSame(2, $result->attempts);

        // Second attempt's prompt must include the first attempt's errors.
        $secondPrompt = $result->steps[1]->promptUsed;
        $this->assertStringContainsString('Fix these issues:', $secondPrompt);
        $this->assertStringContainsString('cycle_detected', $secondPrompt);
    }

    #[Test]
    public function steps_trace_matches_attempt_count(): void
    {
        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($this->validPlanJson()), 200),
        ]);

        $input = new PlannerInput(
            brief: 'Short brief for trace-count verification.',
            maxRetries: 5,
        );

        $result = $this->planner->plan($input);

        $this->assertSame(count($result->steps), $result->attempts);
        $this->assertSame(1, $result->attempts);
    }

    #[Test]
    public function ambiguous_brief_still_yields_valid_plan_with_assumptions(): void
    {
        // Simulate a minimal-viable plan the model emits when the brief is vague.
        $minimalPlan = json_encode([
            'intent' => 'x',
            'vibeMode' => 'clean_education',
            'nodes' => [
                [
                    'id' => 'seed',
                    'type' => 'userPrompt',
                    'config' => ['prompt' => 'x'],
                    'reason' => 'Ambiguous brief — seed with verbatim input.',
                    'label' => null,
                ],
            ],
            'edges' => [],
            'assumptions' => ['brief was ambiguous — selected minimum viable pipeline'],
            'rationale' => 'Minimum viable plan for ambiguous brief.',
            'meta' => ['plannerVersion' => '1.0'],
        ], JSON_UNESCAPED_UNICODE);

        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($minimalPlan), 200),
        ]);

        $input = new PlannerInput(brief: 'x');
        $result = $this->planner->plan($input);

        $this->assertTrue($result->successful());
        $this->assertNotNull($result->plan);
        $this->assertNotEmpty($result->plan->assumptions);
        $this->assertStringContainsString('ambiguous', $result->plan->assumptions[0]);
    }

    #[Test]
    public function parser_strips_markdown_fences_and_surrounding_prose(): void
    {
        $wrapped = "Sure! Here is your plan:\n\n```json\n"
            . $this->validPlanJson()
            . "\n```\n\nHope that helps!";

        Http::fake([
            'api.fireworks.ai/*' => Http::response($this->chatCompletionBody($wrapped), 200),
        ]);

        $input = new PlannerInput(brief: 'Fence-stripping English brief.');
        $result = $this->planner->plan($input);

        $this->assertTrue($result->successful(), 'errors: ' . json_encode($result->validation->errors));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Build a Fireworks/Groq-compatible chat-completions response body wrapping
     * a given assistant text.
     */
    private function chatCompletionBody(string $assistantText): array
    {
        return [
            'id' => 'cmpl_test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'accounts/fireworks/models/minimax-m2p7',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $assistantText,
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 10,
                'total_tokens' => 20,
            ],
        ];
    }

    private function validPlanJson(): string
    {
        // Minimal real plan: userPrompt → scriptWriter. Avoids required-input
        // satisfaction issues that larger plans hit (productAnalyzer requires
        // images, etc.) — keeps the planner-loop test focused on loop mechanics.
        return json_encode([
            'intent' => 'Soft-sell skincare TikTok.',
            'vibeMode' => 'funny_storytelling',
            'nodes' => [
                [
                    'id' => 'seed',
                    'type' => 'userPrompt',
                    'config' => ['prompt' => 'Make a soft-sell skincare TikTok.'],
                    'reason' => 'Pass the brief through as the script prompt.',
                    'label' => null,
                ],
                [
                    'id' => 'script',
                    'type' => 'scriptWriter',
                    'config' => [
                        'provider' => 'stub',
                        'apiKey' => '',
                        'model' => 'gpt-4o',
                        'style' => 'Soft-sell storytelling with a twist.',
                        'structure' => 'story_arc',
                        'includeHook' => true,
                        'includeCTA' => false,
                        'targetDurationSeconds' => 30,
                        'hook_intensity' => 'high',
                        'narrative_tension' => 'high',
                        'product_emphasis' => 'subtle',
                        'cta_softness' => 'soft',
                        'native_tone' => 'genz_native',
                    ],
                    'reason' => 'Write the soft-sell script.',
                    'label' => null,
                ],
            ],
            'edges' => [
                [
                    'sourceNodeId' => 'seed',
                    'sourcePortKey' => 'prompt',
                    'targetNodeId' => 'script',
                    'targetPortKey' => 'prompt',
                    'reason' => 'Script consumes the seed prompt.',
                ],
            ],
            'assumptions' => ['Platform: TikTok 9:16'],
            'rationale' => 'Soft-sell briefs map to funny_storytelling.',
            'meta' => ['plannerVersion' => '1.0'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function cyclicPlanJson(): string
    {
        // Valid types but edges form seed → script → seed (cycle).
        // Note: scriptWriter.script→userPrompt would need matching port key.
        // Easier: two userPrompt nodes in a cycle.
        return json_encode([
            'intent' => 'Cycle test brief.',
            'vibeMode' => 'funny_storytelling',
            'nodes' => [
                [
                    'id' => 'a',
                    'type' => 'userPrompt',
                    'config' => ['prompt' => 'a'],
                    'reason' => 'first',
                    'label' => null,
                ],
                [
                    'id' => 'b',
                    'type' => 'userPrompt',
                    'config' => ['prompt' => 'b'],
                    'reason' => 'second',
                    'label' => null,
                ],
            ],
            'edges' => [
                [
                    'sourceNodeId' => 'a',
                    'sourcePortKey' => 'prompt',
                    'targetNodeId' => 'b',
                    'targetPortKey' => 'prompt',
                    'reason' => 'fwd',
                ],
                [
                    'sourceNodeId' => 'b',
                    'sourcePortKey' => 'prompt',
                    'targetNodeId' => 'a',
                    'targetPortKey' => 'prompt',
                    'reason' => 'back',
                ],
            ],
            'assumptions' => [],
            'rationale' => 'broken',
            'meta' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
