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

class WanR2VTemplate extends NodeTemplate
{
    use InteractsWithVideo;

    public string $type { get => 'wanR2V'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Wan R2V'; }
    public NodeCategory $category { get => NodeCategory::Video; }
    public string $description { get => 'Generates video from reference videos/images using Wan 2.7 R2V. Preserves character identity across scenes. Supports multi-shot and sound.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('prompt', 'Prompt', DataType::Text, description: 'Multi-shot prompt with character tags and scene directions'),
                PortDefinition::input('referenceVideos', 'Reference Videos', DataType::VideoUrlList, required: false, description: 'Up to 3 character reference video URLs'),
                PortDefinition::input('referenceImages', 'Reference Images', DataType::ImageAssetList, required: false, description: 'Character reference image URLs'),
            ],
            outputs: [
                PortDefinition::output('video', 'Video', DataType::VideoAsset, description: 'Generated video with preserved character identity'),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'aspectRatio' => ['sometimes', 'string', 'in:16:9,9:16,1:1,4:3,3:4'],
            'resolution' => ['sometimes', 'string', 'in:720p,1080p'],
            'duration' => ['sometimes', 'string', 'in:2,3,4,5,6,7,8,9,10'],
            'multiShots' => ['sometimes', 'boolean'],
            'seed' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
            'model' => 'fal-ai/wan/v2.7/reference-to-video',
            'aspectRatio' => '9:16',
            'resolution' => '1080p',
            'duration' => '5',
            'multiShots' => false,
            'seed' => null,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->inputValue('prompt') ?? '';
        if (is_array($prompt)) {
            $prompt = $prompt['text'] ?? json_encode($prompt);
        }

        $referenceVideos = $ctx->inputValue('referenceVideos') ?? [];
        $referenceImages = $ctx->inputValue('referenceImages') ?? [];

        // Collect all reference URLs into a single array for the adapter
        $referenceUrls = [];

        if (!empty($referenceVideos)) {
            foreach ((array) $referenceVideos as $vid) {
                if (is_string($vid)) {
                    $referenceUrls[] = $vid;
                } elseif (is_array($vid) && isset($vid['url'])) {
                    $referenceUrls[] = $vid['url'];
                }
            }
        }

        if (!empty($referenceImages)) {
            foreach ((array) $referenceImages as $img) {
                if (is_string($img)) {
                    $referenceUrls[] = $img;
                } elseif (is_array($img) && isset($img['url'])) {
                    $referenceUrls[] = $img['url'];
                }
            }
        }

        $result = $this->callReferenceToVideo($ctx, (string) $prompt, $referenceUrls);

        $videoData = $result['video'] ?? $result;
        $url = is_array($videoData) ? ($videoData['url'] ?? '') : (string) $videoData;
        $duration = is_array($videoData) ? ($videoData['duration'] ?? 5.0) : 5.0;

        return [
            'video' => PortPayload::success(
                value: [
                    'url' => $url,
                    'duration' => $duration,
                    'resolution' => $ctx->config['resolution'] ?? '1080p',
                    'aspectRatio' => $ctx->config['aspectRatio'] ?? '9:16',
                    'seed' => $result['seed'] ?? null,
                ],
                schemaType: DataType::VideoAsset,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'video',
                previewText: "R2V video · {$duration}s",
            ),
        ];
    }
}
