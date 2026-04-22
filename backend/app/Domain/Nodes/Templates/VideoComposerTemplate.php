<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Concerns\InteractsWithVideo;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class VideoComposerTemplate extends NodeTemplate
{
    use InteractsWithVideo;

    public string $type { get => 'videoComposer'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Video Composer'; }
    public NodeCategory $category { get => NodeCategory::Video; }
    public string $description { get => 'Composes video from frames and optional audio using media composition.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('frames', 'Frames', DataType::ImageFrameList),
                PortDefinition::input('audio', 'Audio', DataType::AudioAsset, required: false),
            ],
            outputs: [
                PortDefinition::output('video', 'Video', DataType::VideoAsset),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'video' => ['sometimes', 'array'],
            'video.provider' => ['sometimes', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return ['video' => ['provider' => 'stub']];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $frames = $ctx->inputValue('frames');
        $audio = $ctx->inputValue('audio');
        $video = $this->callMediaComposition($ctx, is_array($frames) ? $frames : [], $audio);

        return [
            'video' => PortPayload::success(
                value: $video,
                schemaType: DataType::VideoAsset,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'video',
                previewText: 'Video composed',
            ),
        ];
    }
}
