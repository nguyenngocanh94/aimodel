<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Concerns\InteractsWithImage;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class ImageGeneratorTemplate extends NodeTemplate
{
    use InteractsWithImage;

    public string $type { get => 'imageGenerator'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Image Generator'; }
    public NodeCategory $category { get => NodeCategory::Visuals; }
    public string $description { get => 'Generates images from text prompts or scene descriptions using AI image generation.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('prompt', 'Prompt', DataType::Prompt, required: false),
                PortDefinition::input('scenes', 'Scenes', DataType::SceneList, required: false),
            ],
            outputs: [
                PortDefinition::output('image', 'Image', DataType::ImageAsset),
                PortDefinition::output('images', 'Images', DataType::ImageAssetList),
            ],
        );
    }

    public function configRules(): array
    {
        return $this->imageConfigRules() + [
            'inputMode' => ['sometimes', 'string', 'in:prompt,scene'],
            'outputMode' => ['sometimes', 'string', 'in:single,multiple'],
        ];
    }

    public function defaultConfig(): array
    {
        return $this->imageDefaultConfig() + ['inputMode' => 'prompt', 'outputMode' => 'single'];
    }

    public function activePorts(array $config): PortSchema
    {
        $inputMode = $config['inputMode'] ?? 'prompt';
        $outputMode = $config['outputMode'] ?? 'single';

        $inputs = match ($inputMode) {
            'scene' => [PortDefinition::input('scenes', 'Scenes', DataType::SceneList)],
            default => [PortDefinition::input('prompt', 'Prompt', DataType::Prompt)],
        };

        $outputs = match ($outputMode) {
            'multiple' => [PortDefinition::output('images', 'Images', DataType::ImageAssetList)],
            default => [PortDefinition::output('image', 'Image', DataType::ImageAsset)],
        };

        return new PortSchema(inputs: $inputs, outputs: $outputs);
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $inputMode = $ctx->config['inputMode'] ?? 'prompt';
        $outputMode = $ctx->config['outputMode'] ?? 'single';

        if ($outputMode === 'multiple') {
            $items = $inputMode === 'scene'
                ? ($ctx->inputValue('scenes') ?? [])
                : [['prompt' => $ctx->inputValue('prompt') ?? '']];

            $images = [];
            foreach ($items as $i => $item) {
                $promptText = is_string($item) ? $item : ($item['prompt'] ?? $item['description'] ?? json_encode($item));
                $binary = $this->callImageGeneration($ctx, (string) $promptText);

                $artifact = $ctx->storeArtifact(
                    "image-{$i}.png",
                    $binary,
                    'image/png',
                );

                $images[] = [
                    'artifactId' => $artifact->id,
                    'index' => $i,
                ];
            }

            return [
                'images' => PortPayload::success(
                    value: $images,
                    schemaType: DataType::ImageAssetList,
                    sourceNodeId: $ctx->nodeId,
                    sourcePortKey: 'images',
                    previewText: count($images) . ' image(s) generated',
                ),
            ];
        }

        // Single image mode
        $rawPrompt = $inputMode === 'scene'
            ? json_encode($ctx->inputValue('scenes') ?? [])
            : ($ctx->inputValue('prompt') ?? '');

        $promptText = is_string($rawPrompt) ? $rawPrompt : (string) json_encode($rawPrompt);

        $binary = $this->callImageGeneration($ctx, $promptText);

        $artifact = $ctx->storeArtifact('image.png', $binary, 'image/png');

        return [
            'image' => PortPayload::success(
                value: ['artifactId' => $artifact->id],
                schemaType: DataType::ImageAsset,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'image',
                previewText: 'Image generated',
            ),
        ];
    }
}
