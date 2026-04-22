<?php

declare(strict_types=1);

namespace Tests\Feature\Planner;

use App\Domain\Execution\TypeCompatibility;
use App\Domain\Nodes\ConfigSchemaTranspiler;
use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\BenchmarkFixture;
use App\Domain\Planner\BenchmarkFixtureLoader;
use App\Domain\Planner\PlannerInput;
use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlanValidator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Benchmark-driven behavioural spec for the planner.
 *
 * Each fixture provides the "correct" shape the planner should produce for a
 * given brief. We don't call a live LLM — instead we fake its response with a
 * hand-crafted plan that matches the fixture's expectations. The test asserts:
 *
 *  - validator accepts the plan
 *  - every expectedNodes type is present
 *  - no forbiddenNodes type is present
 *  - expectedKnobValues are wired into each node's config
 *  - plan.vibeMode matches fixture.expectedVibeMode
 *
 * This test serves two purposes:
 * 1. Sanity-check the planner's retry + validation plumbing against realistic plans.
 * 2. When 645.5 swaps in the live LLM, diffing the canned plan vs the real
 *    plan reveals drift per fixture.
 *
 * Fixture C (milktea-aesthetic-mood) is SKIPPED — it requires a moodSequencer
 * node that does not yet exist in the registry (see 645.4 scope notes).
 */
final class WorkflowPlannerBenchmarkTest extends TestCase
{
    private WorkflowPlanner $planner;
    private BenchmarkFixtureLoader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'fireworks');

        /** @var NodeTemplateRegistry $registry */
        $registry = app(NodeTemplateRegistry::class);
        $transpiler = new ConfigSchemaTranspiler();
        $validator = new WorkflowPlanValidator($registry, new TypeCompatibility(), $transpiler);
        $manifestBuilder = new NodeManifestBuilder($transpiler);
        $this->planner = new WorkflowPlanner($this->app, $registry, $manifestBuilder, $validator);

        $this->loader = new BenchmarkFixtureLoader();
    }

    #[Test]
    public function fixture_cocoon_soft_sell_plans_to_funny_storytelling(): void
    {
        $this->runBenchmark('cocoon-soft-sell', $this->cocoonSoftSellPlan());
    }

    #[Test]
    public function fixture_cocoon_direct_intro_plans_to_clean_education(): void
    {
        $this->runBenchmark('cocoon-direct-intro', $this->cocoonDirectIntroPlan());
    }

    #[Test]
    public function fixture_chocopie_raw_authentic_plans_to_raw_authentic(): void
    {
        $this->runBenchmark('chocopie-raw-authentic', $this->chocopieRawAuthenticPlan());
    }

    #[Test]
    public function fixture_milktea_aesthetic_mood_is_deferred_to_mood_sequencer_work(): void
    {
        $this->markTestSkipped(
            'Fixture C (milktea-aesthetic-mood) requires moodSequencer node '
            . 'which is not yet in the registry. Will be revisited once that '
            . 'node lands (see 645.4 scope notes).',
        );
    }

    // ── Benchmark runner ──────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $cannedPlan
     */
    private function runBenchmark(string $fixtureId, array $cannedPlan): void
    {
        $fixture = $this->loader->byId($fixtureId);
        $this->assertNotNull($fixture, "Fixture '{$fixtureId}' not found");

        // Fake the LLM with our canned plan.
        Http::fake([
            'api.fireworks.ai/*' => Http::response(
                $this->chatCompletionBody(json_encode($cannedPlan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                200,
            ),
        ]);

        $input = new PlannerInput(
            brief: $fixture->brief,
            product: $fixture->product,
            maxRetries: 2,
        );

        $result = $this->planner->plan($input);

        $this->assertTrue(
            $result->successful(),
            "Fixture {$fixtureId}: plan invalid. errors: " . json_encode($result->validation->errors),
        );

        $plan = $result->plan;
        $this->assertNotNull($plan);
        $this->assertSame($fixture->expectedVibeMode, $plan->vibeMode, "{$fixtureId}: vibeMode mismatch");

        $typesPresent = array_map(fn ($n) => $n->type, $plan->nodes);

        foreach ($fixture->expectedNodes as $required) {
            // Fixture C (moodSequencer) is skipped above; for remaining fixtures
            // all expectedNodes should be in the registry.
            $this->assertContains(
                $required,
                $typesPresent,
                "{$fixtureId}: expected node type '{$required}' missing from plan. Got: "
                    . implode(', ', $typesPresent),
            );
        }

        foreach ($fixture->forbiddenNodes as $forbiddenDescription) {
            // forbiddenNodes entries look like "storyWriter (reason)". Take the type slug.
            $forbiddenType = (string) strtok($forbiddenDescription, ' ');
            $this->assertNotContains(
                $forbiddenType,
                $typesPresent,
                "{$fixtureId}: forbidden node type '{$forbiddenType}' present",
            );
        }

        $this->assertKnobsMatch($fixture, $plan, $typesPresent);
    }

    /** @param list<string> $typesPresent */
    private function assertKnobsMatch(BenchmarkFixture $fixture, \App\Domain\Planner\WorkflowPlan $plan, array $typesPresent): void
    {
        foreach ($fixture->expectedKnobValues as $dotted => $expected) {
            [$nodeType, $knobName] = array_pad(explode('.', (string) $dotted, 2), 2, '');

            if (!in_array($nodeType, $typesPresent, true)) {
                // Knob belongs to a node type not in the plan — acceptable when
                // that node is an optional hint. Skip.
                continue;
            }

            $matchingNodes = array_filter($plan->nodes, fn ($n) => $n->type === $nodeType);
            $this->assertNotEmpty($matchingNodes, "{$fixture->id}: no node of type '{$nodeType}'");

            $firstMatching = array_values($matchingNodes)[0];
            $this->assertArrayHasKey(
                $knobName,
                $firstMatching->config,
                "{$fixture->id}: knob {$nodeType}.{$knobName} missing from config",
            );
            $this->assertSame(
                $expected,
                $firstMatching->config[$knobName],
                "{$fixture->id}: knob {$nodeType}.{$knobName} mismatch",
            );
        }
    }

    // ── Canned plans per fixture ──────────────────────────────────────────

    /**
     * Fixture A — funny_storytelling via storyWriter.
     *
     * NOTE: cross-stack port compatibility between the creative nodes
     * (storyWriter, sceneSplitter, imageGenerator, wanR2V, videoComposer) is
     * still evolving — types don't line up end-to-end yet (e.g.
     * storyWriter.storyArc[json] vs sceneSplitter.script[script]). To keep
     * this benchmark hermetic we use config.inputs.<key> to satisfy required
     * inputs instead of drawing real edges everywhere. The wiring story is a
     * follow-up once 645.5 audits cross-stack types.
     *
     * @return array<string, mixed>
     */
    private function cocoonSoftSellPlan(): array
    {
        $fixture = $this->loader->byId('cocoon-soft-sell');
        $nodes = [
            $this->productAnalyzerNode('analyze', angle: 'entertainment_ready'),
            [
                'id' => 'story',
                'type' => 'storyWriter',
                'config' => $this->storyWriterConfigForSoftSell(),
                'reason' => 'Soft-sell narrative with twist ending.',
                'label' => null,
            ],
            $this->sceneSplitterNode('scenes', editPace: 'fast_cut'),
            $this->promptRefinerNode('prompts'),
            $this->imageGeneratorNode('imgs'),
            $this->wanR2vNode('r2v'),
            $this->videoComposerNode('compose'),
        ];

        // Connect where type-compatible; orphans are allowed as warnings.
        return $this->envelope($fixture, $nodes, [
            ['analyze', 'analysis', 'story', 'productAnalysis', 'Story uses product facts.'],
        ]);
    }

    /**
     * Fixture B — clean_education via scriptWriter.
     * @return array<string, mixed>
     */
    private function cocoonDirectIntroPlan(): array
    {
        $fixture = $this->loader->byId('cocoon-direct-intro');
        $nodes = [
            [
                'id' => 'seed',
                'type' => 'userPrompt',
                'config' => ['prompt' => $fixture->brief],
                'reason' => 'Seed the scriptWriter with the user brief.',
                'label' => null,
            ],
            $this->productAnalyzerNode('analyze', angle: 'education_ready'),
            [
                'id' => 'script',
                'type' => 'scriptWriter',
                'config' => $this->scriptWriterConfigForDirectIntro($fixture->expectedKnobValues),
                'reason' => 'Educational script with hero product moment and CTA.',
                'label' => null,
            ],
            $this->sceneSplitterNode('scenes', editPace: 'steady', extra: ['style' => 'ingredient_breakdown']),
            $this->promptRefinerNode('prompts'),
            $this->imageGeneratorNode('imgs'),
            $this->videoComposerNode('compose'),
        ];

        return $this->envelope($fixture, $nodes, [
            ['seed', 'prompt', 'script', 'prompt', 'Seed feeds script.'],
            ['script', 'script', 'scenes', 'script', 'Script → scenes.'],
        ]);
    }

    /**
     * Fixture D — raw_authentic via storyWriter with humor_density=none.
     * @return array<string, mixed>
     */
    private function chocopieRawAuthenticPlan(): array
    {
        $fixture = $this->loader->byId('chocopie-raw-authentic');
        $nodes = [
            $this->productAnalyzerNode('analyze', angle: 'neutral'),
            [
                'id' => 'story',
                'type' => 'storyWriter',
                'config' => $this->storyWriterConfigForRawAuthentic(),
                'reason' => 'Nostalgic anecdote with no humor; product enters middle.',
                'label' => null,
            ],
            $this->sceneSplitterNode('scenes', editPace: 'steady'),
            $this->promptRefinerNode('prompts'),
            $this->imageGeneratorNode('imgs', extra: ['stylePreset' => 'raw_phone_camera']),
            $this->wanR2vNode('r2v'),
            $this->videoComposerNode('compose', extra: ['polishLevel' => 'minimal']),
        ];

        return $this->envelope($fixture, $nodes, [
            ['analyze', 'analysis', 'story', 'productAnalysis', 'Facts → story.'],
        ]);
    }

    /**
     * Wrap a node list + edge tuples into a full plan envelope.
     *
     * @param list<array<string, mixed>> $nodes
     * @param list<array{0:string, 1:string, 2:string, 3:string, 4:string}> $edgeTuples
     * @return array<string, mixed>
     */
    private function envelope(BenchmarkFixture $fixture, array $nodes, array $edgeTuples): array
    {
        $edges = array_map(fn ($e) => [
            'sourceNodeId' => $e[0],
            'sourcePortKey' => $e[1],
            'targetNodeId' => $e[2],
            'targetPortKey' => $e[3],
            'reason' => $e[4],
        ], $edgeTuples);

        return [
            'intent' => $fixture->brief,
            'vibeMode' => $fixture->expectedVibeMode,
            'nodes' => $nodes,
            'edges' => $edges,
            'assumptions' => ["Canned plan for {$fixture->id} benchmark."],
            'rationale' => "Benchmark-matched plan for {$fixture->id}.",
            'meta' => ['plannerVersion' => '1.0', 'fixtureId' => $fixture->id],
        ];
    }

    // ── Node builders (satisfy required inputs via config.inputs) ─────────

    /** @return array<string, mixed> */
    private function productAnalyzerNode(string $id, string $angle = 'neutral'): array
    {
        return [
            'id' => $id,
            'type' => 'productAnalyzer',
            'config' => $this->productAnalyzerConfig() + [
                'analysis_angle' => $angle,
                'inputs' => ['images' => 'seed-image.jpg'],
            ],
            'reason' => 'Gather product facts with angle ' . $angle . '.',
            'label' => null,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function sceneSplitterNode(string $id, string $editPace = 'steady', array $extra = []): array
    {
        return [
            'id' => $id,
            'type' => 'sceneSplitter',
            'config' => $this->sceneSplitterConfig() + [
                'edit_pace' => $editPace,
                'inputs' => ['script' => 'placeholder script text'],
            ] + $extra,
            'reason' => 'Split into visual scenes with pace ' . $editPace . '.',
            'label' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function promptRefinerNode(string $id): array
    {
        return [
            'id' => $id,
            'type' => 'promptRefiner',
            'config' => $this->promptRefinerConfig() + [
                'inputs' => ['scenes' => ['scene-1']],
            ],
            'reason' => 'Refine per-scene image prompts.',
            'label' => null,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function imageGeneratorNode(string $id, array $extra = []): array
    {
        return [
            'id' => $id,
            'type' => 'imageGenerator',
            'config' => $this->imageGeneratorConfig() + [
                'inputs' => ['prompt' => 'placeholder image prompt'],
            ] + $extra,
            'reason' => 'Render key-frame images.',
            'label' => null,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function wanR2vNode(string $id, array $extra = []): array
    {
        return [
            'id' => $id,
            'type' => 'wanR2V',
            'config' => $this->wanR2vConfig() + [
                'inputs' => ['prompt' => 'placeholder motion prompt'],
            ] + $extra,
            'reason' => 'Bring key-frames to motion.',
            'label' => null,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function videoComposerNode(string $id, array $extra = []): array
    {
        return [
            'id' => $id,
            'type' => 'videoComposer',
            'config' => $this->videoComposerConfig() + [
                'inputs' => ['frames' => ['frame-1.jpg']],
            ] + $extra,
            'reason' => 'Assemble final video.',
            'label' => null,
        ];
    }

    // ── Config helpers (trimmed to what validator requires) ───────────────

    /** @return array<string, mixed> */
    private function productAnalyzerConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'analysisDepth' => 'detailed',
        ];
    }

    /** @return array<string, mixed> */
    private function storyWriterConfigForSoftSell(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'targetDurationSeconds' => 30,
            'storyFormula' => 'problem_agitation_solution',
            'emotionalTone' => 'relatable_humor',
            'productIntegrationStyle' => 'subtle_background',
            'genZAuthenticity' => 'ultra',
            'vietnameseDialect' => 'neutral',
            'story_tension_curve' => 'fast_hit',
            'product_appearance_moment' => 'twist',
            'humor_density' => 'throughout',
            'ending_type_preference' => 'twist_reveal',
            'humanGate' => [
                'enabled' => false,
                'channel' => 'telegram',
                'messageTemplate' => '',
                'options' => ['Approve', 'Revise'],
                'botToken' => '',
                'chatId' => '',
                'timeoutSeconds' => 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function storyWriterConfigForRawAuthentic(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'targetDurationSeconds' => 25,
            'storyFormula' => 'day_in_life',
            'emotionalTone' => 'nostalgic',
            'productIntegrationStyle' => 'natural_use',
            'genZAuthenticity' => 'ultra',
            'vietnameseDialect' => 'neutral',
            'story_tension_curve' => 'slow_build',
            'product_appearance_moment' => 'middle',
            'humor_density' => 'none',
            'ending_type_preference' => 'emotional_beat',
            'humanGate' => [
                'enabled' => false,
                'channel' => 'telegram',
                'messageTemplate' => '',
                'options' => ['Approve', 'Revise'],
                'botToken' => '',
                'chatId' => '',
                'timeoutSeconds' => 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expectedKnobs
     * @return array<string, mixed>
     */
    private function scriptWriterConfigForDirectIntro(array $expectedKnobs): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'style' => 'Clean educational ingredient breakdown.',
            'structure' => 'problem_solution',
            'includeHook' => true,
            'includeCTA' => true,
            'targetDurationSeconds' => (int) ($expectedKnobs['scriptWriter.targetDurationSeconds'] ?? 15),
            'hook_intensity' => 'medium',
            'narrative_tension' => 'medium',
            'product_emphasis' => 'hero',
            'cta_softness' => 'hard',
            'native_tone' => 'conversational',
            // Planner-assigned knobs per fixture (may not be in rules; Laravel ignores unknown keys).
            'tone' => 'educational',
            'productIntegrationStyle' => 'hero_moment',
            'includeCallToAction' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function sceneSplitterConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'maxScenes' => 8,
            'includeVisualDescriptions' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function promptRefinerConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
        ];
    }

    /** @return array<string, mixed> */
    private function imageGeneratorConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'flux',
            'width' => 1080,
            'height' => 1920,
        ];
    }

    /** @return array<string, mixed> */
    private function wanR2vConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'wan-r2v-v1',
            'motionStrength' => 'medium',
        ];
    }

    /** @return array<string, mixed> */
    private function videoComposerConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'ffmpeg',
        ];
    }

    /** @return array<string, mixed> */
    private function chatCompletionBody(string $assistantText): array
    {
        return [
            'id' => 'cmpl_test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'accounts/fireworks/models/minimax-m2p7',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $assistantText],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10, 'total_tokens' => 20],
        ];
    }
}
