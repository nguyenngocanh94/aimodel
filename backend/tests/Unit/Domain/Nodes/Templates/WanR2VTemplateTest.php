<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\WanR2VTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WanR2VTemplateTest extends TestCase
{
    private WanR2VTemplate $template;

    protected function setUp(): void
    {
        $this->template = new WanR2VTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('wanR2V', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Wan R2V', $this->template->title);
        $this->assertSame(NodeCategory::Video, $this->template->category);
    }

    #[Test]
    public function ports_define_prompt_and_references_as_input_video_as_output(): void
    {
        $ports = $this->template->ports();

        $inputKeys = array_map(fn ($p) => $p->key, $ports->inputs);
        $this->assertContains('prompt', $inputKeys);
        $this->assertContains('referenceVideos', $inputKeys);

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('video', $outputKeys);
    }

    #[Test]
    public function default_config_uses_stub_provider(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('stub', $config['provider']);
        $this->assertSame('9:16', $config['aspectRatio']);
        $this->assertSame('1080p', $config['resolution']);
    }

    #[Test]
    public function execute_with_stub_returns_video_asset(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::ReferenceToVideo, $this->anything())
            ->willReturn(new StubAdapter());

        $store = $this->createMock(ArtifactStoreContract::class);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-r2v',
            config: $this->template->defaultConfig(),
            inputs: [
                'prompt' => PortPayload::success(
                    'A young woman walks through a Saigon street market, golden hour',
                    DataType::Text,
                ),
                'referenceVideos' => PortPayload::success(
                    ['https://example.com/linh-ref.mp4'],
                    DataType::VideoUrlList,
                ),
            ],
            runId: 'run-r2v-1',
            providerRouter: $router,
            artifactStore: $store,
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('video', $result);
        $this->assertTrue($result['video']->isSuccess());
        $this->assertSame(DataType::VideoAsset, $result['video']->schemaType);
        $this->assertArrayHasKey('url', $result['video']->value);
        $this->assertArrayHasKey('duration', $result['video']->value);
    }
}
