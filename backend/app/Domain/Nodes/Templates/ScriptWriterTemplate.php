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

class ScriptWriterTemplate extends NodeTemplate
{
    public string $type { get => 'scriptWriter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Script Writer'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Generates a structured video script from a text prompt using AI text generation.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('prompt', 'Prompt', DataType::Prompt),
            ],
            outputs: [
                PortDefinition::output('script', 'Script', DataType::Script),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'style' => ['required', 'string', 'min:1', 'max:200'],
            'structure' => ['required', 'string', 'in:three_act,problem_solution,story_arc,listicle'],
            'includeHook' => ['required', 'boolean'],
            'includeCTA' => ['required', 'boolean'],
            'targetDurationSeconds' => ['required', 'integer', 'min:5', 'max:600'],
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'style' => 'Clear, conversational narration with concrete examples',
            'structure' => 'three_act',
            'includeHook' => true,
            'includeCTA' => true,
            'targetDurationSeconds' => 90,
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->inputValue('prompt');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildSystemPrompt($config),
                'prompt' => $this->buildUserPrompt($prompt, $config),
            ],
            $config,
        );

        $script = $this->parseScript($result);

        return [
            'script' => PortPayload::success(
                value: $script,
                schemaType: DataType::Script,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'script',
                previewText: ($script['title'] ?? 'Script') . ' · ' . count($script['beats'] ?? []) . ' beats',
            ),
        ];
    }

    private function buildSystemPrompt(array $config): string
    {
        $structure = $config['structure'] ?? 'three_act';
        $style = $config['style'] ?? 'conversational';
        $duration = $config['targetDurationSeconds'] ?? 90;

        $parts = [
            "You are a professional video scriptwriter.",
            "Write in this style: {$style}.",
            "Use a {$structure} narrative structure.",
            "Target duration: {$duration} seconds.",
        ];

        if ($config['includeHook'] ?? true) {
            $parts[] = "Start with an attention-grabbing hook in the first 5 seconds.";
        }
        if ($config['includeCTA'] ?? true) {
            $parts[] = "End with a clear call-to-action.";
        }

        $parts[] = "Return valid JSON: {\"title\": string, \"hook\": string|null, \"beats\": string[], \"narration\": string, \"cta\": string|null}";

        return implode(' ', $parts);
    }

    private function buildUserPrompt(mixed $prompt, array $config): string
    {
        $text = is_array($prompt) ? ($prompt['text'] ?? json_encode($prompt)) : (string) $prompt;
        $duration = $config['targetDurationSeconds'] ?? 90;

        return "Create a {$duration}-second video script about: {$text}";
    }

    private function parseScript(mixed $result): array
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return ['title' => 'Generated Script', 'beats' => [$result], 'narration' => $result, 'hook' => null, 'cta' => null];
        }

        if (is_array($result)) {
            return $result;
        }

        return ['title' => 'Script', 'beats' => [], 'narration' => '', 'hook' => null, 'cta' => null];
    }
}
