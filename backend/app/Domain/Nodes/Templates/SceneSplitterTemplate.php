<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\VibeImpact;

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
            // Planner-set creative knobs.
            'edit_pace' => ['sometimes', 'string', 'in:slow_meditative,steady,fast_cut,rapid_fire'],
            'scene_granularity' => ['sometimes', 'string', 'in:broad,normal,fine'],
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
            // Planner-set creative knobs.
            'edit_pace' => 'steady',
            'scene_granularity' => 'normal',
        ];
    }

    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: 'Split a script into a list of individual visual scenes. Canonical home for edit_pace.',
            position: 'after scriptWriter (or storyWriter fallback), before promptRefiner',
            vibeImpact: VibeImpact::Critical,
            humanGate: false,
            knobs: [
                new GuideKnob(
                    name: 'edit_pace',
                    type: 'enum',
                    options: ['slow_meditative', 'steady', 'fast_cut', 'rapid_fire'],
                    default: 'steady',
                    effect: 'Canonical. Drives scene count and cut rhythm. Downstream promptRefiner reads this as a hint.',
                    vibeMapping: [
                        'funny_storytelling' => 'fast_cut',
                        'clean_education' => 'steady',
                        'aesthetic_mood' => 'slow_meditative',
                        'raw_authentic' => 'steady',
                    ],
                ),
                new GuideKnob(
                    name: 'scene_granularity',
                    type: 'enum',
                    options: ['broad', 'normal', 'fine'],
                    default: 'normal',
                    effect: 'Inverse of min-scene-duration. Higher granularity = more cuts.',
                ),
                new GuideKnob(
                    name: 'humor_density',
                    type: 'enum',
                    options: ['none', 'punchline_only', 'throughout'],
                    default: 'punchline_only',
                    effect: 'Planner hint: permits comedic beat-splits. Canonical on storyWriter.',
                    vibeMapping: [
                        'funny_storytelling' => 'throughout',
                        'clean_education' => 'none',
                        'aesthetic_mood' => 'none',
                        'raw_authentic' => 'none',
                    ],
                ),
                new GuideKnob(
                    name: 'product_emphasis',
                    type: 'enum',
                    options: ['subtle', 'balanced', 'hero'],
                    default: 'balanced',
                    effect: 'Planner hint: whether the product gets its own scene. Canonical on scriptWriter.',
                    vibeMapping: [
                        'funny_storytelling' => 'subtle',
                        'clean_education' => 'hero',
                        'aesthetic_mood' => 'subtle',
                        'raw_authentic' => 'balanced',
                    ],
                ),
            ],
            readsFrom: ['scriptWriter', 'storyWriter'],
            writesTo: ['promptRefiner'],
            ports: [
                GuidePort::input('script', 'script', true),
                GuidePort::output('scenes', 'sceneList'),
            ],
            whenToInclude: 'always when a scene-level breakdown is needed before prompt generation',
            whenToSkip: 'when downstream promptRefiner is configured in Wan mode and consumes the story directly',
        );
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
