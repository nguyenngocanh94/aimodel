<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ImageGeneratorTemplate;
use App\Domain\PortPayload;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageGeneratorTemplateTest extends TestCase
{
    private ImageGeneratorTemplate $template;

    protected function setUp(): void
    {
        $this->template = new ImageGeneratorTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('imageGenerator', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Visuals, $this->template->category);
    }

    #[Test]
    public function full_ports_include_all_inputs_and_outputs(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(2, $ports->inputs);
        $this->assertCount(2, $ports->outputs);
    }

    #[Test]
    public function active_ports_with_prompt_single_mode(): void
    {
        $active = $this->template->activePorts(['inputMode' => 'prompt', 'outputMode' => 'single']);

        $this->assertCount(1, $active->inputs);
        $this->assertSame('prompt', $active->inputs[0]->key);
        $this->assertSame(DataType::Prompt, $active->inputs[0]->dataType);

        $this->assertCount(1, $active->outputs);
        $this->assertSame('image', $active->outputs[0]->key);
        $this->assertSame(DataType::ImageAsset, $active->outputs[0]->dataType);
    }

    #[Test]
    public function active_ports_with_scene_multiple_mode(): void
    {
        $active = $this->template->activePorts(['inputMode' => 'scene', 'outputMode' => 'multiple']);

        $this->assertCount(1, $active->inputs);
        $this->assertSame('scenes', $active->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $active->inputs[0]->dataType);

        $this->assertCount(1, $active->outputs);
        $this->assertSame('images', $active->outputs[0]->key);
        $this->assertSame(DataType::ImageAssetList, $active->outputs[0]->dataType);
    }

    #[Test]
    public function active_ports_defaults_to_prompt_single(): void
    {
        $active = $this->template->activePorts([]);

        $this->assertCount(1, $active->inputs);
        $this->assertSame('prompt', $active->inputs[0]->key);

        $this->assertCount(1, $active->outputs);
        $this->assertSame('image', $active->outputs[0]->key);
    }

    #[Test]
    public function execute_single_image_from_prompt(): void
    {
        $artifact = $this->createMock(Artifact::class);
        $artifact->id = 'art-123';

        $store = $this->createMock(ArtifactStoreContract::class);
        $store->method('put')->willReturn($artifact);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-5',
            config: ['image' => ['provider' => 'stub'], 'inputMode' => 'prompt', 'outputMode' => 'single'],
            inputs: [
                'prompt' => PortPayload::success('A beautiful sunset', DataType::Prompt),
            ],
            runId: 'run-1',
            artifactStore: $store,
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('image', $result);
        $this->assertTrue($result['image']->isSuccess());
        $this->assertSame(DataType::ImageAsset, $result['image']->schemaType);
        $this->assertArrayHasKey('artifactId', $result['image']->value);
    }

    #[Test]
    public function execute_multiple_images_from_scenes(): void
    {
        $artifact = $this->createMock(Artifact::class);
        $artifact->id = 'art-456';

        $store = $this->createMock(ArtifactStoreContract::class);
        $store->method('put')->willReturn($artifact);

        $scenes = [
            ['description' => 'Scene one'],
            ['description' => 'Scene two'],
        ];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-6',
            config: ['image' => ['provider' => 'stub'], 'inputMode' => 'scene', 'outputMode' => 'multiple'],
            inputs: [
                'scenes' => PortPayload::success($scenes, DataType::SceneList),
            ],
            runId: 'run-1',
            artifactStore: $store,
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('images', $result);
        $this->assertTrue($result['images']->isSuccess());
        $this->assertSame(DataType::ImageAssetList, $result['images']->schemaType);
        $this->assertCount(2, $result['images']->value);
    }
}
