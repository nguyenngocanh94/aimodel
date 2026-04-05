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

class TtsVoiceoverPlannerTemplate extends NodeTemplate
{
    public string $type { get => 'ttsVoiceoverPlanner'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'TTS Voiceover Planner'; }
    public NodeCategory $category { get => NodeCategory::Audio; }
    public string $description { get => 'Generates an audio plan from scenes using text generation.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('scenes', 'Scenes', DataType::SceneList),
            ],
            outputs: [
                PortDefinition::output('audioPlan', 'Audio Plan', DataType::AudioPlan),
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
        $scenes = $ctx->inputValue('scenes');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            ['scenes' => $scenes],
            $config,
        );

        $audioPlan = is_array($result) ? $result : ['segments' => []];

        return [
            'audioPlan' => PortPayload::success(
                value: $audioPlan,
                schemaType: DataType::AudioPlan,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'audioPlan',
                previewText: count($audioPlan['segments'] ?? []) . ' segment(s)',
            ),
        ];
    }
}
