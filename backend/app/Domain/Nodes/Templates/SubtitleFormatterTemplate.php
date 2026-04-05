<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class SubtitleFormatterTemplate extends NodeTemplate
{
    public string $type { get => 'subtitleFormatter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Subtitle Formatter'; }
    public NodeCategory $category { get => NodeCategory::Audio; }
    public string $description { get => 'Formats subtitles from an audio plan using structured transform.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('audioPlan', 'Audio Plan', DataType::AudioPlan),
            ],
            outputs: [
                PortDefinition::output('subtitles', 'Subtitles', DataType::SubtitleAsset),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['sometimes', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $audioPlan = $ctx->inputValue('audioPlan');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::StructuredTransform)->execute(
            Capability::StructuredTransform,
            ['audioPlan' => $audioPlan],
            $config,
        );

        $subtitles = is_array($result) ? $result : ['segments' => []];

        return [
            'subtitles' => PortPayload::success(
                value: $subtitles,
                schemaType: DataType::SubtitleAsset,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'subtitles',
                previewText: count($subtitles['segments'] ?? []) . ' subtitle(s)',
            ),
        ];
    }
}
