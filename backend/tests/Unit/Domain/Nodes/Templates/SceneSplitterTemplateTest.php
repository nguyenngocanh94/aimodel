<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\SceneSplitterTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SceneSplitterTemplateTest extends TestCase
{
    private SceneSplitterTemplate $template;

    protected function setUp(): void
    {
        $this->template = new SceneSplitterTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('sceneSplitter', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Script, $this->template->category);
    }

    #[Test]
    public function ports_has_script_input_and_scenes_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('script', $ports->inputs[0]->key);
        $this->assertSame(DataType::Script, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('scenes', $ports->outputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_scene_list(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $scriptData = [
            'title' => 'Test Script',
            'beats' => ['Scene one', 'Scene two', 'Scene three'],
        ];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-3',
            config: ['provider' => 'stub'],
            inputs: [
                'script' => PortPayload::success($scriptData, DataType::Script),
            ],
            runId: 'run-1',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('scenes', $result);
        $this->assertTrue($result['scenes']->isSuccess());
        $this->assertSame(DataType::SceneList, $result['scenes']->schemaType);
        $this->assertIsArray($result['scenes']->value);
    }

    #[Test]
    public function planner_guide_exposes_expected_knob_names(): void
    {
        $guide = $this->template->plannerGuide();
        $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

        $this->assertContains('edit_pace', $knobNames);
        $this->assertContains('scene_granularity', $knobNames);
        $this->assertContains('humor_density', $knobNames);
        $this->assertContains('product_emphasis', $knobNames);
    }

    #[Test]
    public function planner_guide_knobs_have_vibe_mappings_for_all_four_modes(): void
    {
        $guide = $this->template->plannerGuide();
        // scene_granularity is intentionally not vibe-mapped (see design doc §8).
        $expectVibeMapped = ['edit_pace', 'humor_density', 'product_emphasis'];

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

    #[Test]
    public function config_rules_include_new_planner_knobs(): void
    {
        $rules = $this->template->configRules();
        $this->assertArrayHasKey('edit_pace', $rules);
        $this->assertArrayHasKey('scene_granularity', $rules);

        $defaults = $this->template->defaultConfig();
        $this->assertSame('steady', $defaults['edit_pace']);
        $this->assertSame('normal', $defaults['scene_granularity']);
    }
}
