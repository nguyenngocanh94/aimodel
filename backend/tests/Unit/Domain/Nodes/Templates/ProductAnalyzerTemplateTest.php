<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ProductAnalyzerTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductAnalyzerTemplateTest extends TestCase
{
    private ProductAnalyzerTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new ProductAnalyzerTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('productAnalyzer', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Product Analyzer', $this->template->title);
        $this->assertSame(NodeCategory::Input, $this->template->category);
    }

    #[Test]
    public function ports_define_images_input_and_analysis_output(): void
    {
        $ports = $this->template->ports();

        $inputKeys = array_map(fn ($p) => $p->key, $ports->inputs);
        $this->assertContains('images', $inputKeys);
        $this->assertContains('description', $inputKeys);

        // images is required, description is optional
        $imagesPort = $ports->getInput('images');
        $this->assertTrue($imagesPort->required);
        $this->assertSame(DataType::ImageAssetList, $imagesPort->dataType);

        $descPort = $ports->getInput('description');
        $this->assertFalse($descPort->required);
        $this->assertSame(DataType::Text, $descPort->dataType);

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('analysis', $outputKeys);
        $this->assertSame(DataType::Json, $ports->getOutput('analysis')->dataType);
    }

    #[Test]
    public function default_config_uses_stub(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('stub', $config['provider']);
        $this->assertSame('gpt-4o', $config['model']);
        $this->assertSame('detailed', $config['analysisDepth']);
    }

    #[Test]
    public function execute_with_stub_returns_json_analysis(): void
    {
        $store = $this->createMock(ArtifactStoreContract::class);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-pa-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'images' => PortPayload::success(
                    [
                        ['url' => 'https://example.com/product-front.jpg'],
                        ['url' => 'https://example.com/product-side.jpg'],
                    ],
                    DataType::ImageAssetList,
                ),
            ],
            runId: 'run-pa-1',
            artifactStore: $store,
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('analysis', $result);
        $this->assertTrue($result['analysis']->isSuccess());
        $this->assertSame(DataType::Json, $result['analysis']->schemaType);
        $this->assertIsArray($result['analysis']->value);

        // The StubAdapter returns a script-like array for TextGeneration,
        // so the parser should fall back to the default structure.
        $analysis = $result['analysis']->value;
        $this->assertArrayHasKey('productType', $analysis);
        $this->assertArrayHasKey('productName', $analysis);
        $this->assertArrayHasKey('colors', $analysis);
        $this->assertArrayHasKey('materials', $analysis);
        $this->assertArrayHasKey('sellingPoints', $analysis);
        $this->assertArrayHasKey('targetAudience', $analysis);
        $this->assertArrayHasKey('pricePositioning', $analysis);
        $this->assertArrayHasKey('suggestedMood', $analysis);
    }

    #[Test]
    public function planner_guide_exposes_expected_knob_names(): void
    {
        $guide = $this->template->plannerGuide();
        $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

        $this->assertContains('analysis_angle', $knobNames);
        $this->assertContains('product_emphasis', $knobNames);
    }

    #[Test]
    public function planner_guide_knobs_have_vibe_mappings_for_all_four_modes(): void
    {
        $guide = $this->template->plannerGuide();
        foreach ($guide->knobs as $knob) {
            $this->assertArrayHasKey('funny_storytelling', $knob->vibeMapping, "{$knob->name} missing funny_storytelling");
            $this->assertArrayHasKey('clean_education', $knob->vibeMapping, "{$knob->name} missing clean_education");
            $this->assertArrayHasKey('aesthetic_mood', $knob->vibeMapping, "{$knob->name} missing aesthetic_mood");
            $this->assertArrayHasKey('raw_authentic', $knob->vibeMapping, "{$knob->name} missing raw_authentic");
        }
    }

    #[Test]
    public function config_rules_include_new_planner_knobs(): void
    {
        $rules = $this->template->configRules();
        $this->assertArrayHasKey('analysis_angle', $rules);

        $defaults = $this->template->defaultConfig();
        $this->assertSame('neutral', $defaults['analysis_angle']);
    }
}
