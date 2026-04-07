<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\PromptRefinerTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PromptRefinerTemplateTest extends TestCase
{
    private PromptRefinerTemplate $template;

    protected function setUp(): void
    {
        $this->template = new PromptRefinerTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('promptRefiner', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Script, $this->template->category);
    }

    #[Test]
    public function ports_has_scenes_input_and_prompts_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(2, $ports->inputs);
        $this->assertSame('scenes', $ports->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->inputs[0]->dataType);
        $this->assertSame('story', $ports->inputs[1]->key);
        $this->assertSame(DataType::Text, $ports->inputs[1]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('prompts', $ports->outputs[0]->key);
        $this->assertSame(DataType::PromptList, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_prompt_list(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $scenes = [
            ['id' => 'scene-1', 'description' => 'Opening shot'],
            ['id' => 'scene-2', 'description' => 'Main action'],
        ];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-4',
            config: ['provider' => 'stub'],
            inputs: [
                'scenes' => PortPayload::success($scenes, DataType::SceneList),
            ],
            runId: 'run-1',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('prompts', $result);
        $this->assertTrue($result['prompts']->isSuccess());
        $this->assertSame(DataType::PromptList, $result['prompts']->schemaType);
        $this->assertIsArray($result['prompts']->value);
        $this->assertNotEmpty($result['prompts']->value);
    }

    // ──────────────────────────────────────────────
    //  Wan mode tests
    // ──────────────────────────────────────────────

    #[Test]
    public function execute_wan_mode_includes_aesthetic_terms_in_system_prompt(): void
    {
        $config = [
            'provider' => 'stub',
            'targetFormat' => 'wan',
            'wanFormula' => 'advanced',
            'wanAspectRatio' => '9:16',
        ];

        $systemPrompt = $this->template->buildWanSystemPrompt($config);

        // Must reference the Wan model
        $this->assertStringContainsString('Wan', $systemPrompt);

        // Must contain aesthetic control vocabulary from PromptDictionary
        $this->assertStringContainsString('Light sources:', $systemPrompt);
        $this->assertStringContainsString('daylight', $systemPrompt);
        $this->assertStringContainsString('moonlight', $systemPrompt);

        $this->assertStringContainsString('Shot sizes:', $systemPrompt);
        $this->assertStringContainsString('close-up', $systemPrompt);
        $this->assertStringContainsString('medium shot', $systemPrompt);

        $this->assertStringContainsString('Camera angles:', $systemPrompt);
        $this->assertStringContainsString('eye-level', $systemPrompt);
        $this->assertStringContainsString('low-angle shot', $systemPrompt);

        $this->assertStringContainsString('Tones:', $systemPrompt);
        $this->assertStringContainsString('warm tones', $systemPrompt);

        $this->assertStringContainsString('Stylizations:', $systemPrompt);
        $this->assertStringContainsString('cyberpunk', $systemPrompt);

        // Must reference the advanced formula
        $this->assertStringContainsString('aesthetic control', $systemPrompt);
        $this->assertStringContainsString('stylization', $systemPrompt);
    }

    #[Test]
    public function execute_wan_r2v_formula_includes_character_tags(): void
    {
        $config = [
            'provider' => 'stub',
            'targetFormat' => 'wan',
            'wanFormula' => 'r2v',
            'wanAspectRatio' => '9:16',
            'characterTags' => ['character1', 'character2', 'character3'],
        ];

        $systemPrompt = $this->template->buildWanSystemPrompt($config);

        // Must mention R2V
        $this->assertStringContainsString('R2V', $systemPrompt);

        // Must include all character tags
        $this->assertStringContainsString('character1', $systemPrompt);
        $this->assertStringContainsString('character2', $systemPrompt);
        $this->assertStringContainsString('character3', $systemPrompt);

        // Must reference the R2V formula structure
        $this->assertStringContainsString('Character + Action + Lines + Scene', $systemPrompt);
    }

    #[Test]
    public function execute_wan_multishot_formula_includes_timestamps(): void
    {
        $config = [
            'provider' => 'stub',
            'targetFormat' => 'wan',
            'wanFormula' => 'multiShot',
            'wanAspectRatio' => '16:9',
        ];

        $systemPrompt = $this->template->buildWanSystemPrompt($config);

        // Must mention multi-shot and timestamps
        $this->assertStringContainsString('multi-shot', $systemPrompt);
        $this->assertStringContainsString('shot number', $systemPrompt);
        $this->assertStringContainsString('timestamp', $systemPrompt);
        $this->assertStringContainsString('[0~3s]', $systemPrompt);

        // Must reference the multi-shot formula structure
        $this->assertStringContainsString('Overall description + Shot number + Timestamp + Shot content', $systemPrompt);
    }

    #[Test]
    public function wan_config_validation_accepts_valid_options(): void
    {
        $rules = $this->template->configRules();

        // targetFormat rule exists and allows 'generic' and 'wan'
        $this->assertArrayHasKey('targetFormat', $rules);
        $this->assertContains('in:generic,wan', $rules['targetFormat']);

        // wanFormula rule exists and allows all formula types
        $this->assertArrayHasKey('wanFormula', $rules);
        $this->assertContains('in:basic,advanced,r2v,multiShot,sound', $rules['wanFormula']);

        // wanAspectRatio rule exists
        $this->assertArrayHasKey('wanAspectRatio', $rules);
        $this->assertContains('in:16:9,9:16,1:1', $rules['wanAspectRatio']);

        // characterTags rule exists
        $this->assertArrayHasKey('characterTags', $rules);
        $this->assertContains('array', $rules['characterTags']);

        // includeSound rule exists
        $this->assertArrayHasKey('includeSound', $rules);
        $this->assertContains('boolean', $rules['includeSound']);
    }

    #[Test]
    public function active_ports_shows_story_input_when_wan_mode(): void
    {
        // Wan mode: should show story input
        $wanPorts = $this->template->activePorts(['targetFormat' => 'wan']);
        $this->assertCount(1, $wanPorts->inputs);
        $this->assertSame('story', $wanPorts->inputs[0]->key);
        $this->assertSame(DataType::Text, $wanPorts->inputs[0]->dataType);

        // Generic mode: should show scenes input
        $genericPorts = $this->template->activePorts(['targetFormat' => 'generic']);
        $this->assertCount(1, $genericPorts->inputs);
        $this->assertSame('scenes', $genericPorts->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $genericPorts->inputs[0]->dataType);

        // Default (no targetFormat): should show scenes input
        $defaultPorts = $this->template->activePorts([]);
        $this->assertCount(1, $defaultPorts->inputs);
        $this->assertSame('scenes', $defaultPorts->inputs[0]->key);
    }

    #[Test]
    public function execute_wan_mode_returns_prompt_list(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $ctx = new NodeExecutionContext(
            nodeId: 'node-5',
            config: [
                'provider' => 'stub',
                'targetFormat' => 'wan',
                'wanFormula' => 'advanced',
                'wanAspectRatio' => '9:16',
            ],
            inputs: [
                'story' => PortPayload::success(
                    'A boy discovers a magical forest and befriends a talking fox.',
                    DataType::Text,
                ),
            ],
            runId: 'run-2',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('prompts', $result);
        $this->assertTrue($result['prompts']->isSuccess());
        $this->assertSame(DataType::PromptList, $result['prompts']->schemaType);
        $this->assertIsArray($result['prompts']->value);
        $this->assertNotEmpty($result['prompts']->value);
    }

    #[Test]
    public function wan_sound_formula_includes_sound_instructions(): void
    {
        $config = [
            'provider' => 'stub',
            'targetFormat' => 'wan',
            'wanFormula' => 'sound',
            'wanAspectRatio' => '16:9',
        ];

        $systemPrompt = $this->template->buildWanSystemPrompt($config);

        $this->assertStringContainsString('sound', $systemPrompt);
        $this->assertStringContainsString('voice', $systemPrompt);
        $this->assertStringContainsString('sound effect', $systemPrompt);
        $this->assertStringContainsString('background music', $systemPrompt);
    }

    #[Test]
    public function wan_include_sound_adds_sound_instructions_to_non_sound_formula(): void
    {
        $config = [
            'provider' => 'stub',
            'targetFormat' => 'wan',
            'wanFormula' => 'advanced',
            'includeSound' => true,
        ];

        $systemPrompt = $this->template->buildWanSystemPrompt($config);

        $this->assertStringContainsString('sound descriptions', $systemPrompt);
    }

    #[Test]
    public function default_config_preserves_backward_compatibility(): void
    {
        $defaults = $this->template->defaultConfig();

        $this->assertSame('generic', $defaults['targetFormat']);
        $this->assertSame('advanced', $defaults['wanFormula']);
        $this->assertSame('9:16', $defaults['wanAspectRatio']);
        $this->assertSame([], $defaults['characterTags']);
        $this->assertFalse($defaults['includeSound']);

        // Original defaults still present
        $this->assertSame('cinematic, high quality, photorealistic', $defaults['imageStyle']);
        $this->assertSame('16:9', $defaults['aspectRatio']);
        $this->assertSame('standard', $defaults['detailLevel']);
    }
}
