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

class StoryWriterTemplate extends NodeTemplate
{
    public string $type { get => 'storyWriter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Story Writer'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Writes human-centered story arcs for TVC videos. Localized for Vietnamese GenZ.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('productAnalysis', 'Product Analysis', DataType::Json, required: false),
                PortDefinition::input('trendBrief', 'Trend Brief', DataType::Json, required: false),
                PortDefinition::input('modelRoster', 'Model Roster', DataType::Json, required: false),
                PortDefinition::input('seedIdea', 'Seed Idea', DataType::Text, required: false),
            ],
            outputs: [
                PortDefinition::output('storyArc', 'Story Arc', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'targetDurationSeconds' => ['required', 'integer', 'min:15', 'max:120'],
            'storyFormula' => ['required', 'string', 'in:hero_journey,problem_agitation_solution,before_after_transformation,day_in_life,social_proof_story,emotional_hook'],
            'emotionalTone' => ['required', 'string', 'in:aspirational,relatable_humor,nostalgic,empowering,fomo_urgency,warm_family'],
            'productIntegrationStyle' => ['required', 'string', 'in:subtle_background,natural_use,hero_moment,transformation_reveal,comparison_story'],
            'genZAuthenticity' => ['required', 'string', 'in:low,medium,high,ultra'],
            'vietnameseDialect' => ['required', 'string', 'in:northern,central,southern,neutral'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'targetDurationSeconds' => 30,
            'storyFormula' => 'problem_agitation_solution',
            'emotionalTone' => 'relatable_humor',
            'productIntegrationStyle' => 'natural_use',
            'genZAuthenticity' => 'high',
            'vietnameseDialect' => 'neutral',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $productAnalysis = $ctx->inputValue('productAnalysis');
        $trendBrief = $ctx->inputValue('trendBrief');
        $modelRoster = $ctx->inputValue('modelRoster');
        $seedIdea = $ctx->inputValue('seedIdea');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildSystemPrompt($config),
                'prompt' => $this->buildUserPrompt($productAnalysis, $trendBrief, $modelRoster, $seedIdea, $config),
            ],
            $config,
        );

        $storyArc = $this->parseStoryArc($result);

        return [
            'storyArc' => PortPayload::success(
                value: $storyArc,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'storyArc',
                previewText: ($storyArc['title'] ?? 'Story') . ' · '
                    . count($storyArc['shots'] ?? []) . ' shots · '
                    . ($storyArc['formula'] ?? 'unknown'),
            ),
        ];
    }

    private function buildSystemPrompt(array $config): string
    {
        $duration = $config['targetDurationSeconds'] ?? 30;
        $formula = $config['storyFormula'] ?? 'problem_agitation_solution';
        $tone = $config['emotionalTone'] ?? 'relatable_humor';
        $integration = $config['productIntegrationStyle'] ?? 'natural_use';
        $authenticity = $config['genZAuthenticity'] ?? 'high';
        $dialect = $config['vietnameseDialect'] ?? 'neutral';

        $parts = [
            'You are a Vietnamese TikTok TVC story writer that creates human stories, not product pitches.',
            "Write a {$duration}-second TVC story using the {$formula} formula.",
            "Emotional tone: {$tone}. Product integration style: {$integration}.",
            "GenZ authenticity level: {$authenticity}. Vietnamese dialect: {$dialect}.",
            'Focus on human emotions and relatable situations. The product should enhance the story, not dominate it.',
            'Return valid JSON with the following keys:',
            '"title" (string - story title),',
            '"theme" (string - central theme),',
            '"formula" (string - story formula used),',
            '"hook" (string - opening hook to grab attention in first 3 seconds),',
            '"shots" (array of objects with: shotNumber, timestamp, description, dialogue, emotion, setting, cameraDirection),',
            '"cast" (object with "lead" and "supporting" arrays describing characters),',
            '"toneDirection" (string - overall tone guidance for director),',
            '"soundDirection" (string - music/sound design guidance),',
            '"productMoment" (string - description of the key product integration moment).',
        ];

        return implode(' ', $parts);
    }

    private function buildUserPrompt(
        mixed $productAnalysis,
        mixed $trendBrief,
        mixed $modelRoster,
        mixed $seedIdea,
        array $config,
    ): string {
        $parts = [];

        if ($productAnalysis !== null) {
            $text = is_array($productAnalysis) ? json_encode($productAnalysis) : (string) $productAnalysis;
            $parts[] = "Product analysis: {$text}";
        }

        if ($trendBrief !== null) {
            $text = is_array($trendBrief) ? json_encode($trendBrief) : (string) $trendBrief;
            $parts[] = "Trend brief: {$text}";
        }

        if ($modelRoster !== null) {
            $text = is_array($modelRoster) ? json_encode($modelRoster) : (string) $modelRoster;
            $parts[] = "Available models/talent: {$text}";
        }

        if ($seedIdea !== null) {
            $seedText = is_string($seedIdea) ? $seedIdea : json_encode($seedIdea);
            if ($seedText !== '' && $seedText !== '""') {
                $parts[] = "Seed idea / creative direction: {$seedText}";
            }
        }

        $duration = $config['targetDurationSeconds'] ?? 30;

        if (empty($parts)) {
            $parts[] = "Create a {$duration}-second Vietnamese GenZ TVC story arc.";
        }

        return implode("\n", $parts);
    }

    private function parseStoryArc(mixed $result): array
    {
        if (is_string($result)) {
            // Strip markdown code fences (```json ... ``` or ``` ... ```)
            $cleaned = preg_replace('/^```(?:json)?\s*\n?/i', '', trim($result));
            $cleaned = preg_replace('/\n?```\s*$/i', '', $cleaned);

            $decoded = json_decode(trim($cleaned), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Try to find JSON object in the response
            if (preg_match('/\{[\s\S]*\}/u', $result, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            return $this->emptyStoryArc();
        }

        if (is_array($result)) {
            return $result;
        }

        return $this->emptyStoryArc();
    }

    private function emptyStoryArc(): array
    {
        return [
            'title' => '',
            'theme' => '',
            'formula' => '',
            'hook' => '',
            'shots' => [],
            'cast' => ['lead' => [], 'supporting' => []],
            'toneDirection' => '',
            'soundDirection' => '',
            'productMoment' => '',
        ];
    }
}
