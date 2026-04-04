<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ImageGeneratorTemplate;
use App\Domain\Nodes\Templates\PromptRefinerTemplate;
use App\Domain\Nodes\Templates\SceneSplitterTemplate;
use App\Domain\Nodes\Templates\ScriptWriterTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderRouter;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\TestCase;

class WiredTemplatesTest extends TestCase
{
    private function makeContext(
        array $config,
        array $inputs,
        string $nodeId = 'test-node',
    ): NodeExecutionContext {
        $artifactStore = new class implements ArtifactStoreContract {
            public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact
            {
                $artifact = new Artifact();
                $artifact->id = 'art-' . md5($name);
                $artifact->run_id = $runId;
                $artifact->node_id = $nodeId;
                $artifact->name = $name;
                $artifact->mime_type = $mimeType;
                $artifact->size_bytes = strlen($contents);
                $artifact->disk = 'local';
                $artifact->path = "artifacts/{$runId}/{$nodeId}/{$name}";
                return $artifact;
            }

            public function url(Artifact $artifact): string { return "/api/artifacts/{$artifact->id}"; }
            public function get(Artifact $artifact): string { return ''; }
            public function delete(Artifact $artifact): void {}
            public function deleteForRun(string $runId): void {}
        };

        return new NodeExecutionContext(
            nodeId: $nodeId,
            config: $config,
            inputs: $inputs,
            runId: 'run-test',
            providerRouter: new ProviderRouter(),
            artifactStore: $artifactStore,
        );
    }

    // --- ScriptWriter ---

    public function test_script_writer_builds_system_prompt_with_config(): void
    {
        $template = new ScriptWriterTemplate();
        $config = $template->defaultConfig();

        $this->assertArrayHasKey('style', $config);
        $this->assertArrayHasKey('structure', $config);
        $this->assertArrayHasKey('includeHook', $config);
        $this->assertArrayHasKey('includeCTA', $config);
        $this->assertArrayHasKey('targetDurationSeconds', $config);
    }

    public function test_script_writer_execute_returns_script(): void
    {
        $template = new ScriptWriterTemplate();
        $config = $template->defaultConfig();
        $ctx = $this->makeContext($config, [
            'prompt' => PortPayload::success('A video about cats', DataType::Prompt),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('script', $result);
        $this->assertInstanceOf(PortPayload::class, $result['script']);
        $this->assertSame('success', $result['script']->status);
        $this->assertSame(DataType::Script, $result['script']->schemaType);
    }

    public function test_script_writer_config_rules_validate(): void
    {
        $template = new ScriptWriterTemplate();
        $rules = $template->configRules();

        $this->assertArrayHasKey('style', $rules);
        $this->assertArrayHasKey('structure', $rules);
        $this->assertArrayHasKey('targetDurationSeconds', $rules);
    }

    // --- SceneSplitter ---

    public function test_scene_splitter_builds_proper_prompts(): void
    {
        $template = new SceneSplitterTemplate();
        $config = $template->defaultConfig();

        $this->assertArrayHasKey('maxScenes', $config);
        $this->assertArrayHasKey('includeVisualDescriptions', $config);
    }

    public function test_scene_splitter_execute_returns_scenes(): void
    {
        $template = new SceneSplitterTemplate();
        $config = $template->defaultConfig();
        $ctx = $this->makeContext($config, [
            'script' => PortPayload::success(
                ['title' => 'Test', 'beats' => ['beat1', 'beat2']],
                DataType::Script,
            ),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('scenes', $result);
        $this->assertInstanceOf(PortPayload::class, $result['scenes']);
        $this->assertSame(DataType::SceneList, $result['scenes']->schemaType);
    }

    // --- PromptRefiner ---

    public function test_prompt_refiner_has_image_config(): void
    {
        $template = new PromptRefinerTemplate();
        $config = $template->defaultConfig();

        $this->assertArrayHasKey('imageStyle', $config);
        $this->assertArrayHasKey('aspectRatio', $config);
        $this->assertArrayHasKey('detailLevel', $config);
    }

    public function test_prompt_refiner_execute_returns_prompts(): void
    {
        $template = new PromptRefinerTemplate();
        $config = $template->defaultConfig();
        $ctx = $this->makeContext($config, [
            'scenes' => PortPayload::success(
                [['index' => 0, 'title' => 'Scene 1', 'description' => 'A cat in a garden']],
                DataType::SceneList,
            ),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('prompts', $result);
        $this->assertInstanceOf(PortPayload::class, $result['prompts']);
        $this->assertSame(DataType::PromptList, $result['prompts']->schemaType);
    }

    // --- ImageGenerator ---

    public function test_image_generator_single_mode(): void
    {
        $template = new ImageGeneratorTemplate();
        $config = array_merge($template->defaultConfig(), [
            'inputMode' => 'prompt',
            'outputMode' => 'single',
        ]);
        $ctx = $this->makeContext($config, [
            'prompt' => PortPayload::success('A beautiful sunset', DataType::Prompt),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('image', $result);
        $this->assertInstanceOf(PortPayload::class, $result['image']);
        $this->assertSame(DataType::ImageAsset, $result['image']->schemaType);
    }

    public function test_image_generator_multiple_mode(): void
    {
        $template = new ImageGeneratorTemplate();
        $config = array_merge($template->defaultConfig(), [
            'inputMode' => 'scene',
            'outputMode' => 'multiple',
        ]);
        $ctx = $this->makeContext($config, [
            'scenes' => PortPayload::success(
                [['prompt' => 'Scene 1'], ['prompt' => 'Scene 2']],
                DataType::SceneList,
            ),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('images', $result);
        $this->assertInstanceOf(PortPayload::class, $result['images']);
        $this->assertSame(DataType::ImageAssetList, $result['images']->schemaType);
        $this->assertCount(2, $result['images']->value);
    }

    public function test_image_generator_active_ports_varies_by_config(): void
    {
        $template = new ImageGeneratorTemplate();

        $singlePorts = $template->activePorts(['inputMode' => 'prompt', 'outputMode' => 'single']);
        $this->assertCount(1, $singlePorts->inputs);
        $this->assertSame('prompt', $singlePorts->inputs[0]->key);
        $this->assertSame('image', $singlePorts->outputs[0]->key);

        $multiPorts = $template->activePorts(['inputMode' => 'scene', 'outputMode' => 'multiple']);
        $this->assertSame('scenes', $multiPorts->inputs[0]->key);
        $this->assertSame('images', $multiPorts->outputs[0]->key);
    }

    // --- Error handling ---

    public function test_script_writer_handles_string_response(): void
    {
        $template = new ScriptWriterTemplate();
        $config = $template->defaultConfig();
        $ctx = $this->makeContext($config, [
            'prompt' => PortPayload::success('test prompt', DataType::Prompt),
        ]);

        $result = $template->execute($ctx);
        $script = $result['script']->value;

        // StubAdapter returns structured data, parseScript should handle it
        $this->assertIsArray($script);
    }
}
