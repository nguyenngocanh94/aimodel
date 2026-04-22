<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ScriptWriterTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScriptWriterTemplateTest extends TestCase
{
    private ScriptWriterTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new ScriptWriterTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('scriptWriter', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Script, $this->template->category);
    }

    #[Test]
    public function ports_has_prompt_input_and_script_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('prompt', $ports->inputs[0]->key);
        $this->assertSame(DataType::Prompt, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('script', $ports->outputs[0]->key);
        $this->assertSame(DataType::Script, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_script_via_provider(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-2',
            config: ['provider' => 'stub'],
            inputs: [
                'prompt' => PortPayload::success('Write a script about nature', DataType::Prompt),
            ],
            runId: 'run-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('script', $result);
        $this->assertTrue($result['script']->isSuccess());
        $this->assertSame(DataType::Script, $result['script']->schemaType);
        $this->assertIsArray($result['script']->value);
        $this->assertArrayHasKey('title', $result['script']->value);
    }

    #[Test]
    public function planner_guide_exposes_expected_knob_names(): void
    {
        $guide = $this->template->plannerGuide();
        $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

        $this->assertContains('structure', $knobNames);
        $this->assertContains('hook_intensity', $knobNames);
        $this->assertContains('narrative_tension', $knobNames);
        $this->assertContains('humor_density', $knobNames);
        $this->assertContains('product_emphasis', $knobNames);
        $this->assertContains('cta_softness', $knobNames);
        $this->assertContains('native_tone', $knobNames);
        $this->assertContains('edit_pace', $knobNames);
        $this->assertContains('trend_usage', $knobNames);
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

        $this->assertArrayHasKey('hook_intensity', $rules);
        $this->assertArrayHasKey('narrative_tension', $rules);
        $this->assertArrayHasKey('product_emphasis', $rules);
        $this->assertArrayHasKey('cta_softness', $rules);
        $this->assertArrayHasKey('native_tone', $rules);

        $defaults = $this->template->defaultConfig();
        $this->assertSame('high', $defaults['hook_intensity']);
        $this->assertSame('medium', $defaults['narrative_tension']);
        $this->assertSame('balanced', $defaults['product_emphasis']);
        $this->assertSame('medium', $defaults['cta_softness']);
        $this->assertSame('conversational', $defaults['native_tone']);
    }
}
