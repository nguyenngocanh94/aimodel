<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class ImageAssetMapperTemplate extends NodeTemplate
{
    use InteractsWithLlm;

    public string $type { get => 'imageAssetMapper'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Image Asset Mapper'; }
    public NodeCategory $category { get => NodeCategory::Visuals; }
    public string $description { get => 'Wraps image assets into frame format using structured transform.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('images', 'Images', DataType::ImageAssetList),
            ],
            outputs: [
                PortDefinition::output('frames', 'Frames', DataType::ImageFrameList),
            ],
        );
    }

    public function configRules(): array
    {
        return $this->llmConfigRules();
    }

    public function defaultConfig(): array
    {
        return ['llm' => ['provider' => 'stub', 'model' => '']];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $images = $ctx->inputValue('images');

        $result = $this->callStructuredTransform(
            $ctx,
            'You map a list of image assets into frame objects suitable for video composition. Return JSON with key "frames" as an array of {id, imageUrl, duration, order} objects.',
            'Images: ' . json_encode($images, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $frames = !empty($result) ? $result : [];

        return [
            'frames' => PortPayload::success(
                value: $frames,
                schemaType: DataType::ImageFrameList,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'frames',
                previewText: count(is_array($frames) ? ($frames['frames'] ?? $frames) : []) . ' frame(s) mapped',
            ),
        ];
    }
}
