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

class SceneSplitterTemplate extends NodeTemplate
{
    public string $type { get => 'sceneSplitter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Scene Splitter'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Splits a script into a list of individual scenes using AI text generation.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('script', 'Script', DataType::Script),
            ],
            outputs: [
                PortDefinition::output('scenes', 'Scenes', DataType::SceneList),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'maxScenes' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'includeVisualDescriptions' => ['sometimes', 'boolean'],
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'maxScenes' => 10,
            'includeVisualDescriptions' => true,
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $script = $ctx->inputValue('script');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildSystemPrompt($config),
                'prompt' => $this->buildUserPrompt($script, $config),
            ],
            $config,
        );

        $scenes = $this->parseScenes($result);

        return [
            'scenes' => PortPayload::success(
                value: $scenes,
                schemaType: DataType::SceneList,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'scenes',
                previewText: count($scenes) . ' scene(s)',
            ),
        ];
    }

    private function buildSystemPrompt(array $config): string
    {
        $maxScenes = $config['maxScenes'] ?? 10;
        $parts = [
            "You are an expert video scene planner.",
            "Split the provided script into distinct visual scenes (max {$maxScenes}).",
            "Each scene should have a clear visual setting and action.",
        ];

        if ($config['includeVisualDescriptions'] ?? true) {
            $parts[] = "Include detailed visual descriptions for each scene suitable for image generation.";
        }

        $parts[] = "Return valid JSON: {\"scenes\": [{\"index\": number, \"title\": string, \"description\": string, \"visualDescription\": string, \"durationSeconds\": number, \"narration\": string}]}";

        return implode(' ', $parts);
    }

    private function buildUserPrompt(mixed $script, array $config): string
    {
        $maxScenes = $config['maxScenes'] ?? 10;
        $scriptText = is_array($script) ? json_encode($script) : (string) $script;

        return "Split this script into up to {$maxScenes} distinct visual scenes:\n\n{$scriptText}";
    }

    private function parseScenes(mixed $result): array
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded['scenes'] ?? $decoded['beats'] ?? [$decoded];
            }
            return [['index' => 0, 'title' => 'Scene 1', 'description' => $result, 'visualDescription' => $result]];
        }

        if (is_array($result)) {
            return $result['scenes'] ?? $result['beats'] ?? [$result];
        }

        return [];
    }
}
