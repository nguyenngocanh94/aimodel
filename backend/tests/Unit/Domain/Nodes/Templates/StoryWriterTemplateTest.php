<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\Templates\StoryWriterTemplate;
use App\Domain\Nodes\VibeImpact;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use App\Services\Memory\RunMemoryStore;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StoryWriterTemplateTest extends TestCase
{
    private StoryWriterTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new StoryWriterTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('storyWriter', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Story Writer', $this->template->title);
        $this->assertSame(NodeCategory::Script, $this->template->category);
        $this->assertStringContainsString('story', strtolower($this->template->description));
        $this->assertStringContainsString('vietnamese', strtolower($this->template->description));
    }

    #[Test]
    public function ports_define_correct_inputs_and_output(): void
    {
        $ports = $this->template->ports();

        $inputKeys = array_map(fn ($p) => $p->key, $ports->inputs);
        $this->assertContains('productAnalysis', $inputKeys);
        $this->assertContains('trendBrief', $inputKeys);
        $this->assertContains('modelRoster', $inputKeys);
        $this->assertContains('seedIdea', $inputKeys);

        // All inputs are optional
        foreach ($ports->inputs as $input) {
            $this->assertFalse($input->required, "Input '{$input->key}' should be optional");
        }

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('storyArc', $outputKeys);

        $storyArcPort = $ports->outputs[0];
        $this->assertSame(DataType::Json, $storyArcPort->dataType);
    }

    #[Test]
    public function default_config_has_expected_values(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('stub', $config['provider']);
        $this->assertSame('gpt-4o', $config['model']);
        $this->assertSame(30, $config['targetDurationSeconds']);
        $this->assertSame('problem_agitation_solution', $config['storyFormula']);
        $this->assertSame('relatable_humor', $config['emotionalTone']);
        $this->assertSame('natural_use', $config['productIntegrationStyle']);
        $this->assertSame('high', $config['genZAuthenticity']);
        $this->assertSame('neutral', $config['vietnameseDialect']);
    }

    #[Test]
    public function execute_with_stub_returns_json_story_arc(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-story-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'productAnalysis' => PortPayload::success(
                    ['productName' => 'Glow Serum', 'target' => 'Gen Z'],
                    DataType::Json,
                ),
                'trendBrief' => PortPayload::success(
                    ['trendingFormats' => ['POV skincare'], 'trendingHashtags' => ['#glowup']],
                    DataType::Json,
                ),
                'seedIdea' => PortPayload::success(
                    'Morning routine transformation',
                    DataType::Text,
                ),
            ],
            runId: 'run-story-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('storyArc', $result);
        $this->assertTrue($result['storyArc']->isSuccess());
        $this->assertSame(DataType::Json, $result['storyArc']->schemaType);
        $this->assertIsArray($result['storyArc']->value);
    }

    #[Test]
    public function system_prompt_includes_key_instructions(): void
    {
        $config = $this->template->defaultConfig();

        $reflection = new \ReflectionMethod($this->template, 'buildSystemPrompt');
        $systemPrompt = $reflection->invoke($this->template, $config);

        $this->assertStringContainsString('Vietnamese', $systemPrompt);
        $this->assertStringContainsString('TikTok', $systemPrompt);
        $this->assertStringContainsString('not product pitches', $systemPrompt);
        $this->assertStringContainsString('problem_agitation_solution', $systemPrompt);
        $this->assertStringContainsString('relatable_humor', $systemPrompt);
        $this->assertStringContainsString('shots', $systemPrompt);
        $this->assertStringContainsString('cast', $systemPrompt);
        $this->assertStringContainsString('productMoment', $systemPrompt);
    }

    #[Test]
    public function planner_guide_has_correct_identity(): void
    {
        $guide = $this->template->plannerGuide();

        $this->assertInstanceOf(NodeGuide::class, $guide);
        $this->assertSame('storyWriter', $guide->nodeId);
        $this->assertSame(VibeImpact::Critical, $guide->vibeImpact);
        $this->assertTrue($guide->humanGate);
    }

    #[Test]
    public function planner_guide_has_all_seven_knobs(): void
    {
        $guide = $this->template->plannerGuide();
        $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

        $this->assertContains('story_tension_curve', $knobNames);
        $this->assertContains('product_appearance_moment', $knobNames);
        $this->assertContains('humor_density', $knobNames);
        $this->assertContains('story_versions_for_human', $knobNames);
        $this->assertContains('max_moments', $knobNames);
        $this->assertContains('target_duration_sec', $knobNames);
        $this->assertContains('ending_type_preference', $knobNames);
    }

    #[Test]
    public function planner_guide_knobs_have_vibe_mappings(): void
    {
        $guide = $this->template->plannerGuide();

        $tensionKnob = null;
        foreach ($guide->knobs as $k) {
            if ($k->name === 'story_tension_curve') {
                $tensionKnob = $k;
                break;
            }
        }

        $this->assertNotNull($tensionKnob);
        $this->assertSame('enum', $tensionKnob->type);
        $this->assertContains('slow_build', $tensionKnob->options);
        $this->assertContains('fast_hit', $tensionKnob->options);
        $this->assertArrayHasKey('funny_storytelling', $tensionKnob->vibeMapping);
        $this->assertSame('fast_hit', $tensionKnob->vibeMapping['funny_storytelling']);
    }

    #[Test]
    public function planner_guide_has_correct_connections(): void
    {
        $guide = $this->template->plannerGuide();

        $this->assertContains('humanGate', $guide->readsFrom);
        $this->assertContains('intentOutcomeSelector', $guide->readsFrom);
        $this->assertContains('truthConstraintGate', $guide->readsFrom);
        $this->assertContains('formatLibraryMatcher', $guide->readsFrom);
        $this->assertContains('casting', $guide->writesTo);
        $this->assertContains('shotCompiler', $guide->writesTo);
    }

    #[Test]
    public function planner_guide_when_to_include_specifies_vibe_modes(): void
    {
        $guide = $this->template->plannerGuide();

        $this->assertStringContainsString('funny_storytelling', $guide->whenToInclude);
        $this->assertStringContainsString('raw_authentic', $guide->whenToInclude);
    }

    #[Test]
    public function planner_guide_exposes_expected_knob_names(): void
    {
        $guide = $this->template->plannerGuide();
        $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

        // Existing knobs.
        $this->assertContains('story_tension_curve', $knobNames);
        $this->assertContains('product_appearance_moment', $knobNames);
        $this->assertContains('humor_density', $knobNames);
        $this->assertContains('ending_type_preference', $knobNames);
        // Newly added shared planner hints.
        $this->assertContains('native_tone', $knobNames);
        $this->assertContains('trend_usage', $knobNames);
    }

    // ─── LP-I4: cross-run memory recall ────────────────────────────────────

    #[Test]
    public function execute_writes_story_arc_last_into_memory(): void
    {
        $store = $this->createMock(RunMemoryStore::class);
        $store->expects($this->once())
            ->method('put')
            ->with(
                'workflow:demo',
                'storyArc:last',
                $this->callback(fn ($v) => is_array($v)),
                $this->callback(fn ($v) => is_array($v)),
                $this->isInstanceOf(\DateTimeInterface::class),
            );
        // recall is called once before execute; test path: no previous.
        $store->expects($this->once())
            ->method('get')
            ->with('workflow:demo', 'storyArc:last')
            ->willReturn(null);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-story-memory-1',
            config: $this->template->defaultConfig(),
            inputs: [],
            runId: 'run-mem-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
            memory: $store,
            workflowSlug: 'demo',
        );

        $this->template->execute($ctx);
    }

    #[Test]
    public function build_user_prompt_without_previous_story_omits_digest(): void
    {
        $reflection = new \ReflectionMethod($this->template, 'buildUserPrompt');
        $prompt = $reflection->invoke(
            $this->template,
            null,
            null,
            null,
            'Morning routine transformation',
            $this->template->defaultConfig(),
            null, // no previous story
        );

        $this->assertStringNotContainsString('Previous story for this workflow', $prompt);
    }

    #[Test]
    public function build_user_prompt_with_previous_story_includes_digest(): void
    {
        $reflection = new \ReflectionMethod($this->template, 'buildUserPrompt');
        $prompt = $reflection->invoke(
            $this->template,
            null,
            null,
            null,
            'Morning routine transformation',
            $this->template->defaultConfig(),
            ['title' => 'Glow Up', 'theme' => 'self-love', 'hook' => 'POV you glow differently'],
        );

        $this->assertStringContainsString('Previous story for this workflow', $prompt);
        $this->assertStringContainsString('Glow Up', $prompt);
        $this->assertStringContainsString('self-love', $prompt);
        $this->assertStringContainsString('POV you glow differently', $prompt);
    }

    #[Test]
    public function recall_previous_story_config_knob_false_skips_recall(): void
    {
        $store = $this->createMock(RunMemoryStore::class);
        // When recallPreviousStory is false, get() MUST NOT be called, but put() still is.
        $store->expects($this->never())->method('get');
        $store->expects($this->once())->method('put');

        $config = $this->template->defaultConfig();
        $config['recallPreviousStory'] = false;

        $ctx = new NodeExecutionContext(
            nodeId: 'node-story-mem-opt-out',
            config: $config,
            inputs: [],
            runId: 'run-mem-2',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
            memory: $store,
            workflowSlug: 'demo',
        );

        $this->template->execute($ctx);
    }

    #[Test]
    public function planner_guide_knobs_have_vibe_mappings_for_all_four_modes(): void
    {
        $guide = $this->template->plannerGuide();
        $expectVibeMapped = [
            'story_tension_curve',
            'product_appearance_moment',
            'humor_density',
            'ending_type_preference',
            'native_tone',
            'trend_usage',
        ];

        foreach ($guide->knobs as $knob) {
            if (!in_array($knob->name, $expectVibeMapped, true)) {
                continue;
            }
            $this->assertArrayHasKey('funny_storytelling', $knob->vibeMapping, "{$knob->name} missing funny_storytelling");
            $this->assertArrayHasKey('clean_education', $knob->vibeMapping, "{$knob->name} missing clean_education");
            $this->assertArrayHasKey('aesthetic_mood', $knob->vibeMapping, "{$knob->name} missing aesthetic_mood");
            $this->assertArrayHasKey('raw_authentic', $knob->vibeMapping, "{$knob->name} missing raw_authentic");
        }
    }
}
