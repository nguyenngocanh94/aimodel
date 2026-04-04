<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\FinalExportTemplate;
use App\Domain\Nodes\Templates\ImageAssetMapperTemplate;
use App\Domain\Nodes\Templates\SubtitleFormatterTemplate;
use App\Domain\Nodes\Templates\TtsVoiceoverPlannerTemplate;
use App\Domain\Nodes\Templates\VideoComposerTemplate;
use App\Domain\PortPayload;
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
        $this->assertSame('imageFrameList', $ports->inputs[0]->key);
        $this->assertSame(DataType::ImageFrameList, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('imageAssetList', $ports->outputs[0]->key);
        $this->assertSame(DataType::ImageAssetList, $ports->outputs[0]->dataType);
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
            'imageFrameList' => PortPayload::success($frameList, DataType::ImageFrameList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('imageAssetList', $result);
        $this->assertInstanceOf(PortPayload::class, $result['imageAssetList']);
        $this->assertTrue($result['imageAssetList']->isSuccess());
        $this->assertSame(DataType::ImageAssetList, $result['imageAssetList']->schemaType);

        $value = $result['imageAssetList']->value;
        $this->assertIsArray($value);
        $this->assertSame(2, $value['count']);
        $this->assertCount(2, $value['assets']);
        $this->assertSame('background', $value['assets'][0]['role']);
        $this->assertSame('overlay', $value['assets'][1]['role']);
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
        $this->assertSame('sceneList', $ports->inputs[0]->key);
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
            'sceneList' => PortPayload::success($scenes, DataType::SceneList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('audioPlan', $result);
        $this->assertInstanceOf(PortPayload::class, $result['audioPlan']);
        $this->assertTrue($result['audioPlan']->isSuccess());
        $this->assertSame(DataType::AudioPlan, $result['audioPlan']->schemaType);

        $value = $result['audioPlan']->value;
        $this->assertIsArray($value);
        $this->assertCount(2, $value['segments']);
        $this->assertSame('warm', $value['voiceStyle']);
        $this->assertSame('normal', $value['pace']);
        $this->assertSame('neutral', $value['genderStyle']);
        $this->assertGreaterThan(0, $value['totalDurationSeconds']);
        $this->assertStringStartsWith('placeholder://audio/', $value['placeholderAudioUrl']);
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
        $this->assertSame('sceneList', $ports->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('subtitleAsset', $ports->outputs[0]->key);
        $this->assertSame(DataType::SubtitleAsset, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function subtitle_formatter_execute_returns_valid_payload(): void
    {
        $template = new SubtitleFormatterTemplate();

        $scenes = [
            ['title' => 'Scene 1', 'description' => 'A sunrise over the valley with golden light.'],
            ['title' => 'Scene 2', 'description' => 'Close-up of wildflowers swaying in the breeze.'],
        ];

        $ctx = $this->makeContext('node-sub', $template->defaultConfig(), [
            'sceneList' => PortPayload::success($scenes, DataType::SceneList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('subtitleAsset', $result);
        $this->assertInstanceOf(PortPayload::class, $result['subtitleAsset']);
        $this->assertTrue($result['subtitleAsset']->isSuccess());
        $this->assertSame(DataType::SubtitleAsset, $result['subtitleAsset']->schemaType);

        $value = $result['subtitleAsset']->value;
        $this->assertIsArray($value);
        $this->assertSame(2, $value['totalSegments']);
        $this->assertCount(2, $value['segments']);
        $this->assertSame('default', $value['style']['preset']);
        $this->assertSame('soft', $value['style']['burnMode']);
        $this->assertGreaterThan(0, $value['totalDurationSeconds']);
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

        $this->assertCount(3, $ports->inputs);
        $this->assertSame('imageAssetList', $ports->inputs[0]->key);
        $this->assertSame(DataType::ImageAssetList, $ports->inputs[0]->dataType);
        $this->assertSame('audioAsset', $ports->inputs[1]->key);
        $this->assertSame(DataType::AudioAsset, $ports->inputs[1]->dataType);
        $this->assertSame('subtitleAsset', $ports->inputs[2]->key);
        $this->assertSame(DataType::SubtitleAsset, $ports->inputs[2]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('videoAsset', $ports->outputs[0]->key);
        $this->assertSame(DataType::VideoAsset, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function video_composer_execute_returns_valid_payload(): void
    {
        $template = new VideoComposerTemplate();

        $assetList = [
            'assets' => [
                ['assetId' => 'asset-0-0', 'sceneIndex' => 0, 'role' => 'background'],
                ['assetId' => 'asset-1-1', 'sceneIndex' => 1, 'role' => 'foreground'],
                ['assetId' => 'asset-2-2', 'sceneIndex' => 2, 'role' => 'background'],
            ],
            'count' => 3,
            'resolution' => '1024x1024',
        ];

        $ctx = $this->makeContext('node-vid', $template->defaultConfig(), [
            'imageAssetList' => PortPayload::success($assetList, DataType::ImageAssetList),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('videoAsset', $result);
        $this->assertInstanceOf(PortPayload::class, $result['videoAsset']);
        $this->assertTrue($result['videoAsset']->isSuccess());
        $this->assertSame(DataType::VideoAsset, $result['videoAsset']->schemaType);

        $value = $result['videoAsset']->value;
        $this->assertIsArray($value);
        $this->assertSame('16:9', $value['aspectRatio']);
        $this->assertSame(30, $value['fps']);
        $this->assertGreaterThan(0, $value['totalDurationSeconds']);
        $this->assertNotEmpty($value['timeline']);
        $this->assertFalse($value['hasAudio']);
        $this->assertFalse($value['hasSubtitles']);
        $this->assertStringStartsWith('placeholder://video/poster/', $value['posterFrameUrl']);
    }

    #[Test]
    public function video_composer_detects_optional_inputs(): void
    {
        $template = new VideoComposerTemplate();

        $assetList = [
            'assets' => [
                ['assetId' => 'asset-0-0'],
            ],
            'count' => 1,
            'resolution' => '1024x1024',
        ];

        $ctx = $this->makeContext('node-vid2', $template->defaultConfig(), [
            'imageAssetList' => PortPayload::success($assetList, DataType::ImageAssetList),
            'audioAsset' => PortPayload::success(['url' => 'audio.mp3'], DataType::AudioAsset),
            'subtitleAsset' => PortPayload::success(['segments' => []], DataType::SubtitleAsset),
        ]);

        $result = $template->execute($ctx);
        $value = $result['videoAsset']->value;

        $this->assertTrue($value['hasAudio']);
        $this->assertTrue($value['hasSubtitles']);
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
        $this->assertSame('videoAsset', $ports->inputs[0]->key);
        $this->assertSame(DataType::VideoAsset, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('json', $ports->outputs[0]->key);
        $this->assertSame(DataType::Json, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function final_export_execute_returns_valid_payload(): void
    {
        $template = new FinalExportTemplate();

        $videoAsset = [
            'timeline' => [
                ['index' => 0, 'type' => 'titleCard', 'durationSeconds' => 3],
                ['index' => 1, 'type' => 'image', 'assetRef' => 'asset-0-0', 'durationSeconds' => 4],
            ],
            'totalDurationSeconds' => 7,
            'aspectRatio' => '16:9',
            'fps' => 30,
            'posterFrameUrl' => 'placeholder://video/poster/abc.jpg',
            'hasAudio' => false,
            'hasSubtitles' => false,
            'musicBed' => 'none',
        ];

        $ctx = $this->makeContext('node-exp', $template->defaultConfig(), [
            'videoAsset' => PortPayload::success($videoAsset, DataType::VideoAsset),
        ]);

        $result = $template->execute($ctx);

        $this->assertArrayHasKey('json', $result);
        $this->assertInstanceOf(PortPayload::class, $result['json']);
        $this->assertTrue($result['json']->isSuccess());
        $this->assertSame(DataType::Json, $result['json']->schemaType);

        $value = $result['json']->value;
        $this->assertIsArray($value);
        $this->assertSame('mp4', $value['format']);
        $this->assertSame(7, $value['durationSeconds']);
        $this->assertSame('1920x1080', $value['resolution']);
        $this->assertStringEndsWith('.mp4', $value['fileName']);
        $this->assertGreaterThan(0, $value['fileSizeBytesEstimate']);
        $this->assertArrayHasKey('exportedAt', $value);
        $this->assertArrayHasKey('metadata', $value);
    }

    #[Test]
    public function final_export_respects_include_metadata_false(): void
    {
        $template = new FinalExportTemplate();

        $videoAsset = [
            'totalDurationSeconds' => 5,
            'aspectRatio' => '9:16',
            'fps' => 60,
            'timeline' => [],
        ];

        $config = [
            'fileNamePattern' => '{name}-{resolution}',
            'includeMetadata' => false,
            'includeWorkflowSpecReference' => false,
        ];

        $ctx = $this->makeContext('node-exp2', $config, [
            'videoAsset' => PortPayload::success($videoAsset, DataType::VideoAsset),
        ]);

        $result = $template->execute($ctx);
        $value = $result['json']->value;

        $this->assertArrayNotHasKey('metadata', $value);
        $this->assertArrayNotHasKey('workflowSpecRef', $value);
    }

    #[Test]
    public function final_export_includes_workflow_spec_reference(): void
    {
        $template = new FinalExportTemplate();

        $videoAsset = [
            'totalDurationSeconds' => 10,
            'aspectRatio' => '16:9',
            'fps' => 30,
            'timeline' => [],
        ];

        $config = [
            'fileNamePattern' => '{name}-{date}',
            'includeMetadata' => true,
            'includeWorkflowSpecReference' => true,
        ];

        $ctx = $this->makeContext('node-exp3', $config, [
            'videoAsset' => PortPayload::success($videoAsset, DataType::VideoAsset),
        ]);

        $result = $template->execute($ctx);
        $value = $result['json']->value;

        $this->assertArrayHasKey('workflowSpecRef', $value);
        $this->assertStringStartsWith('spec://workflow/', $value['workflowSpecRef']);
    }

    // ================================================================
    // Config rules & defaults
    // ================================================================

    #[Test]
    public function all_templates_have_non_empty_config_rules(): void
    {
        $templates = [
            new ImageAssetMapperTemplate(),
            new TtsVoiceoverPlannerTemplate(),
            new SubtitleFormatterTemplate(),
            new VideoComposerTemplate(),
            new FinalExportTemplate(),
        ];

        foreach ($templates as $template) {
            $this->assertNotEmpty(
                $template->configRules(),
                "{$template->type} should have config rules",
            );
        }
    }

    #[Test]
    public function all_templates_have_non_empty_default_config(): void
    {
        $templates = [
            new ImageAssetMapperTemplate(),
            new TtsVoiceoverPlannerTemplate(),
            new SubtitleFormatterTemplate(),
            new VideoComposerTemplate(),
            new FinalExportTemplate(),
        ];

        foreach ($templates as $template) {
            $this->assertNotEmpty(
                $template->defaultConfig(),
                "{$template->type} should have default config",
            );
        }
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
        return new NodeExecutionContext(
            nodeId: $nodeId,
            config: $config,
            inputs: $inputs,
            runId: 'run-test',
            providerRouter: $this->createStub(ProviderRouter::class),
            artifactStore: $this->createStub(ArtifactStoreContract::class),
        );
    }
}
