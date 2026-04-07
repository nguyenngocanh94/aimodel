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
use App\Domain\Wan\PromptDictionary;

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
                PortDefinition::input('scenes', 'Scenes', DataType::SceneList, required: false),
                PortDefinition::input('story', 'Story', DataType::Text, required: false),
            ],
            outputs: [
                PortDefinition::output('prompts', 'Prompts', DataType::PromptList),
            ],
        );
    }

    public function activePorts(array $config): PortSchema
    {
        $targetFormat = $config['targetFormat'] ?? 'generic';

        $inputs = match ($targetFormat) {
            'wan' => [PortDefinition::input('story', 'Story', DataType::Text)],
            default => [PortDefinition::input('scenes', 'Scenes', DataType::SceneList)],
        };

        return new PortSchema(
            inputs: $inputs,
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
            'targetFormat' => ['sometimes', 'string', 'in:generic,wan'],
            'wanFormula' => ['sometimes', 'string', 'in:basic,advanced,r2v,multiShot,sound'],
            'wanAspectRatio' => ['sometimes', 'string', 'in:16:9,9:16,1:1'],
            'characterTags' => ['sometimes', 'array'],
            'characterTags.*' => ['string'],
            'includeSound' => ['sometimes', 'boolean'],
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
            'targetFormat' => 'generic',
            'wanFormula' => 'advanced',
            'wanAspectRatio' => '9:16',
            'characterTags' => [],
            'includeSound' => false,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $config = $ctx->config;
        $targetFormat = $config['targetFormat'] ?? 'generic';

        if ($targetFormat === 'wan') {
            return $this->executeWan($ctx);
        }

        return $this->executeGeneric($ctx);
    }

    // ──────────────────────────────────────────────
    //  Generic mode (original behavior)
    // ──────────────────────────────────────────────

    private function executeGeneric(NodeExecutionContext $ctx): array
    {
        $scenes = $ctx->inputValue('scenes') ?? [];
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildGenericSystemPrompt($config),
                'prompt' => $this->buildGenericUserPrompt($scenes, $config),
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

    private function buildGenericSystemPrompt(array $config): string
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

    private function buildGenericUserPrompt(mixed $scenes, array $config): string
    {
        $scenesText = is_array($scenes) ? json_encode($scenes) : (string) $scenes;
        $style = $config['imageStyle'] ?? 'cinematic';

        return "Generate optimized image prompts in {$style} style for each of these scenes:\n\n{$scenesText}";
    }

    // ──────────────────────────────────────────────
    //  Wan mode
    // ──────────────────────────────────────────────

    private function executeWan(NodeExecutionContext $ctx): array
    {
        $story = $ctx->inputValue('story') ?? '';
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildWanSystemPrompt($config),
                'prompt' => $this->buildWanUserPrompt($story, $config),
            ],
            $config,
        );

        $prompts = $this->parsePrompts($result, is_array($story) ? $story : []);

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

    public function buildWanSystemPrompt(array $config): string
    {
        $formula = $config['wanFormula'] ?? 'advanced';
        $aspect = $config['wanAspectRatio'] ?? '9:16';
        $characterTags = $config['characterTags'] ?? [];
        $includeSound = $config['includeSound'] ?? false;

        $formulaTemplate = $this->resolveFormulaTemplate($formula);

        $parts = [];
        $parts[] = "You are a Wan video-generation prompt engineer.";
        $parts[] = "Generate prompts optimized for the Wan 2.6/2.7 video model.";
        $parts[] = "Target aspect ratio: {$aspect}.";
        $parts[] = "Follow this prompt formula: {$formulaTemplate}";

        // Aesthetic control vocabulary
        $parts[] = $this->buildAestheticVocabularyBlock();

        // Formula-specific instructions
        $parts[] = match ($formula) {
            'basic' => "Use the basic formula: Entity + Scene + Motion. Keep prompts concise.",
            'advanced' => "Use the advanced formula with aesthetic control terms and stylization. Include lighting, shot size, camera angle, composition, lens, and tone terms from the controlled vocabulary.",
            'r2v' => $this->buildR2VInstructions($characterTags),
            'multiShot' => "Use the multi-shot formula. Each shot must include a shot number and timestamp in [start~end s] format (e.g., [0~3s], [4~6s]). Begin with an overall description summarizing the entire video, then list each shot.",
            'sound' => "Use the sound formula. Include sound descriptions for each scene: voice (character lines + emotion + tone + speed + timbre), sound effects (source material + action + ambient sound), and/or background music (style).",
            default => "Use the advanced formula with rich detail.",
        };

        if ($includeSound && $formula !== 'sound') {
            $parts[] = "Also include sound descriptions (voice, sound effects, and/or background music) where appropriate.";
        }

        $parts[] = "Return valid JSON: {\"prompts\": [{\"sceneIndex\": number, \"prompt\": string}]}";

        return implode("\n", $parts);
    }

    private function buildWanUserPrompt(mixed $story, array $config): string
    {
        $storyText = is_array($story) ? json_encode($story) : (string) $story;
        $formula = $config['wanFormula'] ?? 'advanced';

        $instruction = match ($formula) {
            'multiShot' => "Generate a multi-shot Wan-formatted prompt with timestamps for this story",
            'r2v' => "Generate Wan R2V-formatted prompts with character tags for this story",
            'sound' => "Generate Wan-formatted prompts with sound descriptions for this story",
            default => "Generate Wan-formatted video prompts for this story",
        };

        return "{$instruction}:\n\n{$storyText}";
    }

    private function resolveFormulaTemplate(string $formula): string
    {
        return match ($formula) {
            'basic' => PromptDictionary::basicFormula(),
            'advanced' => PromptDictionary::advancedFormula(),
            'r2v' => PromptDictionary::r2vFormula(),
            'multiShot' => PromptDictionary::multiShotFormula(),
            'sound' => PromptDictionary::soundFormula(),
            default => PromptDictionary::advancedFormula(),
        };
    }

    private function buildAestheticVocabularyBlock(): string
    {
        $lines = [];
        $lines[] = "Use ONLY terms from this controlled vocabulary for aesthetic control:";
        $lines[] = "- Light sources: " . implode(', ', PromptDictionary::lightSources());
        $lines[] = "- Lighting environments: " . implode(', ', PromptDictionary::lightingEnvironments());
        $lines[] = "- Lighting times: " . implode(', ', PromptDictionary::lightingTimes());
        $lines[] = "- Shot sizes: " . implode(', ', PromptDictionary::shotSizes());
        $lines[] = "- Shot compositions: " . implode(', ', PromptDictionary::shotCompositions());
        $lines[] = "- Lenses: " . implode(', ', PromptDictionary::lenses());
        $lines[] = "- Camera angles: " . implode(', ', PromptDictionary::cameraAngles());
        $lines[] = "- Camera movements: " . implode(', ', PromptDictionary::cameraMovements());
        $lines[] = "- Stylizations: " . implode(', ', PromptDictionary::stylizations());
        $lines[] = "- Tones: " . implode(', ', PromptDictionary::tones());

        return implode("\n", $lines);
    }

    private function buildR2VInstructions(array $characterTags): string
    {
        $parts = [];
        $parts[] = "Use the R2V (Reference-to-Video) formula: Character + Action + Lines + Scene.";

        if (!empty($characterTags)) {
            $parts[] = "Use these character reference tags in the prompts:";
            foreach ($characterTags as $tag) {
                $parts[] = "- {$tag}";
            }
            $parts[] = "Each character tag corresponds to a reference video that will be provided separately.";
        }

        return implode("\n", $parts);
    }

    // ──────────────────────────────────────────────
    //  Parsing (shared)
    // ──────────────────────────────────────────────

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
