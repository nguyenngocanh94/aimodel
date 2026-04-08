<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\StoryWriterTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoryWriterTemplateTest extends TestCase
{
    private StoryWriterTemplate $template;

    protected function setUp(): void
    {
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
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

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
            providerRouter: $router,
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
}
