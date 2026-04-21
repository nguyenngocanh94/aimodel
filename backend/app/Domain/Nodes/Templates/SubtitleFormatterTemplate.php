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

class SubtitleFormatterTemplate extends NodeTemplate
{
    use InteractsWithLlm;

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
        return $this->llmConfigRules();
    }

    public function defaultConfig(): array
    {
        return ['llm' => ['provider' => 'stub', 'model' => '']];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $audioPlan = $ctx->inputValue('audioPlan');

        $result = $this->callStructuredTransform(
            $ctx,
            'You format subtitles from an audio plan. Return JSON with key "segments" as an array of {id, text, start, end} objects.',
            'Audio plan: ' . json_encode($audioPlan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );

        $subtitles = !empty($result) ? $result : ['segments' => []];

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
