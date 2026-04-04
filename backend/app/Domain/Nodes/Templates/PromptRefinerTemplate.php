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

class PromptRefinerTemplate extends NodeTemplate
{
    public string $type { get => 'promptRefiner'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Prompt Refiner'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Generates detailed image prompts from a scene list using AI text generation.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('scenes', 'Scenes', DataType::SceneList),
            ],
            outputs: [
                PortDefinition::output('prompts', 'Prompts', DataType::PromptList),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'imageStyle' => ['sometimes', 'string', 'max:200'],
            'aspectRatio' => ['sometimes', 'string', 'in:1:1,16:9,9:16,4:3'],
            'detailLevel' => ['sometimes', 'string', 'in:minimal,standard,detailed'],
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'imageStyle' => 'cinematic, high quality, photorealistic',
            'aspectRatio' => '16:9',
            'detailLevel' => 'standard',
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $scenes = $ctx->inputValue('scenes') ?? [];
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildSystemPrompt($config),
                'prompt' => $this->buildUserPrompt($scenes, $config),
            ],
            $config,
        );

        $prompts = $this->parsePrompts($result, $scenes);

        return [
            'prompts' => PortPayload::success(
                value: $prompts,
                schemaType: DataType::PromptList,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'prompts',
                previewText: count($prompts) . ' prompt(s)',
            ),
        ];
    }

    private function buildSystemPrompt(array $config): string
    {
        $style = $config['imageStyle'] ?? 'cinematic';
        $aspect = $config['aspectRatio'] ?? '16:9';
        $detail = $config['detailLevel'] ?? 'standard';

        return implode(' ', [
            "You are an expert image prompt engineer for AI image generators.",
            "Create detailed, optimized image generation prompts for each scene.",
            "Style: {$style}. Aspect ratio: {$aspect}. Detail level: {$detail}.",
            "Each prompt should describe the scene visually in rich detail suitable for text-to-image models.",
            "Include lighting, composition, mood, and camera angle when appropriate.",
            "Return valid JSON: {\"prompts\": [{\"sceneIndex\": number, \"prompt\": string, \"negativePrompt\": string}]}",
        ]);
    }

    private function buildUserPrompt(mixed $scenes, array $config): string
    {
        $scenesText = is_array($scenes) ? json_encode($scenes) : (string) $scenes;
        $style = $config['imageStyle'] ?? 'cinematic';

        return "Generate optimized image prompts in {$style} style for each of these scenes:\n\n{$scenesText}";
    }

    private function parsePrompts(mixed $result, array $scenes): array
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded['prompts'] ?? [$decoded];
            }
            return [['sceneIndex' => 0, 'prompt' => $result, 'negativePrompt' => '']];
        }

        if (is_array($result)) {
            if (isset($result['prompts'])) {
                return $result['prompts'];
            }
            if (isset($result['beats'])) {
                return array_map(
                    fn (int $i, string $beat) => ['sceneIndex' => $i, 'prompt' => $beat, 'negativePrompt' => ''],
                    array_keys($result['beats']),
                    $result['beats'],
                );
            }
            return [$result];
        }

        return [];
    }
}
