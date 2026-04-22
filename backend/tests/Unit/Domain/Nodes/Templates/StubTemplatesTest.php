<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\Templates\FinalExportTemplate;
use App\Domain\Nodes\Templates\ImageAssetMapperTemplate;
use App\Domain\Nodes\Templates\SubtitleFormatterTemplate;
use App\Domain\Nodes\Templates\TtsVoiceoverPlannerTemplate;
use App\Domain\Nodes\Templates\VideoComposerTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StubTemplatesTest extends TestCase
{
    // ================================================================
    // ImageAssetMapperTemplate
    // ================================================================

    #[Test]
    public function image_asset_mapper_has_correct_metadata(): void
    {
        $template = new ImageAssetMapperTemplate();

        $this->assertSame('imageAssetMapper', $template->type);
        $this->assertSame('1.0.0', $template->version);
        $this->assertSame('Image Asset Mapper', $template->title);
        $this->assertSame(NodeCategory::Visuals, $template->category);
    }

    #[Test]
    public function image_asset_mapper_has_correct_ports(): void
    {
        $template = new ImageAssetMapperTemplate();
        $ports = $template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('images', $ports->inputs[0]->key);
        $this->assertSame(DataType::ImageAssetList, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('frames', $ports->outputs[0]->key);
        $this->assertSame(DataType::ImageFrameList, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function image_asset_mapper_execute_returns_valid_payload(): void
    {
        $template = new ImageAssetMapperTemplate();

        $frameList = [
            'frames' => [
                [
                    'frameId' => 'frame-0',
                    'sceneIndex' => 0,
                    'prompt' => 'Mountain landscape',
                    'placeholderUrl' => 'placeholder://image/1024x1024/frame-0.png',
                    'resolution' => '1024x1024',
                    'seed' => 123,
                    'stylePreset' => 'cinematic',
                ],
                [
                    'frameId' => 'frame-1',
                    'sceneIndex' => 1,
                    'prompt' => 'Close-up of flowers',
                    'placeholderUrl' => 'placeholder://image/1024x1024/frame-1.png',
                    'resolution' => '1024x1024',
                    'seed' => 456,
                    'stylePreset' => 'cinematic',
                ],
            ],
        ];

        $ctx = $this->makeContext('node-map', $template->defaultConfig(), [
            'images' => PortPayload::success($frameList, DataType::ImageAssetList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('frames', $result);
        $this->assertInstanceOf(PortPayload::class, $result['frames']);
        $this->assertTrue($result['frames']->isSuccess());
        $this->assertSame(DataType::ImageFrameList, $result['frames']->schemaType);

        $value = $result['frames']->value;
        $this->assertIsArray($value);
    }

    // ================================================================
    // TtsVoiceoverPlannerTemplate
    // ================================================================

    #[Test]
    public function tts_voiceover_planner_has_correct_metadata(): void
    {
        $template = new TtsVoiceoverPlannerTemplate();

        $this->assertSame('ttsVoiceoverPlanner', $template->type);
        $this->assertSame('1.0.0', $template->version);
        $this->assertSame('TTS Voiceover Planner', $template->title);
        $this->assertSame(NodeCategory::Audio, $template->category);
    }

    #[Test]
    public function tts_voiceover_planner_has_correct_ports(): void
    {
        $template = new TtsVoiceoverPlannerTemplate();
        $ports = $template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('scenes', $ports->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('audioPlan', $ports->outputs[0]->key);
        $this->assertSame(DataType::AudioPlan, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function tts_voiceover_planner_execute_returns_valid_payload(): void
    {
        $template = new TtsVoiceoverPlannerTemplate();

        $scenes = [
            ['title' => 'Scene 1', 'narration' => 'Welcome to this journey through the mountains.'],
            ['title' => 'Scene 2', 'narration' => 'Nature unfolds before us in vivid detail.'],
        ];

        $ctx = $this->makeContext('node-tts', $template->defaultConfig(), [
            'scenes' => PortPayload::success($scenes, DataType::SceneList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('audioPlan', $result);
        $this->assertInstanceOf(PortPayload::class, $result['audioPlan']);
        $this->assertTrue($result['audioPlan']->isSuccess());
        $this->assertSame(DataType::AudioPlan, $result['audioPlan']->schemaType);

        $value = $result['audioPlan']->value;
        $this->assertIsArray($value);
    }

    // ================================================================
    // SubtitleFormatterTemplate
    // ================================================================

    #[Test]
    public function subtitle_formatter_has_correct_metadata(): void
    {
        $template = new SubtitleFormatterTemplate();

        $this->assertSame('subtitleFormatter', $template->type);
        $this->assertSame('1.0.0', $template->version);
        $this->assertSame('Subtitle Formatter', $template->title);
        $this->assertSame(NodeCategory::Audio, $template->category);
    }

    #[Test]
    public function subtitle_formatter_has_correct_ports(): void
    {
        $template = new SubtitleFormatterTemplate();
        $ports = $template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('audioPlan', $ports->inputs[0]->key);
        $this->assertSame(DataType::AudioPlan, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('subtitles', $ports->outputs[0]->key);
        $this->assertSame(DataType::SubtitleAsset, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function subtitle_formatter_execute_returns_valid_payload(): void
    {
        $template = new SubtitleFormatterTemplate();

        $audioPlan = [
            'segments' => [
                ['text' => 'Welcome to the show', 'duration' => 3.0],
                ['text' => 'Let us begin', 'duration' => 2.5],
            ],
        ];

        $ctx = $this->makeContext('node-sub', $template->defaultConfig(), [
            'audioPlan' => PortPayload::success($audioPlan, DataType::AudioPlan),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('subtitles', $result);
        $this->assertInstanceOf(PortPayload::class, $result['subtitles']);
        $this->assertTrue($result['subtitles']->isSuccess());
        $this->assertSame(DataType::SubtitleAsset, $result['subtitles']->schemaType);

        $value = $result['subtitles']->value;
        $this->assertIsArray($value);
    }

    // ================================================================
    // VideoComposerTemplate
    // ================================================================

    #[Test]
    public function video_composer_has_correct_metadata(): void
    {
        $template = new VideoComposerTemplate();

        $this->assertSame('videoComposer', $template->type);
        $this->assertSame('1.0.0', $template->version);
        $this->assertSame('Video Composer', $template->title);
        $this->assertSame(NodeCategory::Video, $template->category);
    }

    #[Test]
    public function video_composer_has_correct_ports(): void
    {
        $template = new VideoComposerTemplate();
        $ports = $template->ports();

        $this->assertCount(2, $ports->inputs);
        $this->assertSame('frames', $ports->inputs[0]->key);
        $this->assertSame(DataType::ImageFrameList, $ports->inputs[0]->dataType);
        $this->assertSame('audio', $ports->inputs[1]->key);
        $this->assertSame(DataType::AudioAsset, $ports->inputs[1]->dataType);
        $this->assertFalse($ports->inputs[1]->required);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('video', $ports->outputs[0]->key);
        $this->assertSame(DataType::VideoAsset, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function video_composer_execute_returns_valid_payload(): void
    {
        $template = new VideoComposerTemplate();

        $frames = [
            ['frameId' => 'f-0', 'url' => 'http://example.com/f0.png'],
        ];

        $ctx = $this->makeContext('node-vid', $template->defaultConfig(), [
            'frames' => PortPayload::success($frames, DataType::ImageFrameList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('video', $result);
        $this->assertInstanceOf(PortPayload::class, $result['video']);
        $this->assertTrue($result['video']->isSuccess());
        $this->assertSame(DataType::VideoAsset, $result['video']->schemaType);
    }

    // ================================================================
    // FinalExportTemplate
    // ================================================================

    #[Test]
    public function final_export_has_correct_metadata(): void
    {
        $template = new FinalExportTemplate();

        $this->assertSame('finalExport', $template->type);
        $this->assertSame('1.0.0', $template->version);
        $this->assertSame('Final Export', $template->title);
        $this->assertSame(NodeCategory::Output, $template->category);
    }

    #[Test]
    public function final_export_has_correct_ports(): void
    {
        $template = new FinalExportTemplate();
        $ports = $template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('video', $ports->inputs[0]->key);
        $this->assertSame(DataType::VideoAsset, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('exported', $ports->outputs[0]->key);
        $this->assertSame(DataType::Json, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function final_export_execute_returns_valid_payload(): void
    {
        $template = new FinalExportTemplate();

        $videoAsset = [
            'totalDurationSeconds' => 10,
            'resolution' => '1920x1080',
        ];

        $ctx = $this->makeContext('node-exp', $template->defaultConfig(), [
            'video' => PortPayload::success($videoAsset, DataType::VideoAsset),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('exported', $result);
        $this->assertInstanceOf(PortPayload::class, $result['exported']);
        $this->assertTrue($result['exported']->isSuccess());
        $this->assertSame(DataType::Json, $result['exported']->schemaType);

        $value = $result['exported']->value;
        $this->assertIsArray($value);
        $this->assertSame('mp4', $value['format']);
        $this->assertSame(10, $value['durationSeconds']);
        $this->assertSame('ready', $value['status']);
        $this->assertArrayHasKey('exportedAt', $value);
    }

    // ================================================================
    // Registry tests
    // ================================================================

    #[Test]
    public function all_five_templates_are_registered_in_registry(): void
    {
        $registry = new NodeTemplateRegistry();
        $registry->register(new ImageAssetMapperTemplate());
        $registry->register(new TtsVoiceoverPlannerTemplate());
        $registry->register(new SubtitleFormatterTemplate());
        $registry->register(new VideoComposerTemplate());
        $registry->register(new FinalExportTemplate());

        $this->assertNotNull($registry->get('imageAssetMapper'));
        $this->assertNotNull($registry->get('ttsVoiceoverPlanner'));
        $this->assertNotNull($registry->get('subtitleFormatter'));
        $this->assertNotNull($registry->get('videoComposer'));
        $this->assertNotNull($registry->get('finalExport'));
    }

    // ================================================================
    // Helper
    // ================================================================

    /**
     * @param array<string, mixed> $config
     * @param array<string, PortPayload> $inputs
     */
    private function makeContext(string $nodeId, array $config, array $inputs): NodeExecutionContext
    {
        $stubAdapter = new StubAdapter();

        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->willReturn($stubAdapter);

        return new NodeExecutionContext(
            nodeId: $nodeId,
            config: $config,
            inputs: $inputs,
            runId: 'run-test',
            providerRouter: $router,
            artifactStore: $this->createStub(ArtifactStoreContract::class),
        );
    }
}
