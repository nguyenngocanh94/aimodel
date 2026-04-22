<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ImageAssetMapperTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImageAssetMapperTemplateTest extends TestCase
{
    private ImageAssetMapperTemplate $template;

    protected function setUp(): void
    {
        $this->template = new ImageAssetMapperTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('imageAssetMapper', $this->template->type);
        $this->assertSame(NodeCategory::Visuals, $this->template->category);
    }

    #[Test]
    public function ports_define_images_input_and_frames_output(): void
    {
        $ports = $this->template->ports();
        $this->assertSame('images', $ports->inputs[0]->key);
        $this->assertSame(DataType::ImageAssetList, $ports->inputs[0]->dataType);
        $this->assertSame('frames', $ports->outputs[0]->key);
        $this->assertSame(DataType::ImageFrameList, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_with_stub_returns_frames(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-im-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'images' => PortPayload::success(
                    [['url' => 'https://example.com/a.png']],
                    DataType::ImageAssetList,
                ),
            ],
            runId: 'run-im-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('frames', $result);
        $this->assertTrue($result['frames']->isSuccess());
    }

    #[Test]
    public function config_rules_expose_llm_keys(): void
    {
        $rules = $this->template->configRules();
        $this->assertArrayHasKey('llm.provider', $rules);
    }
}
