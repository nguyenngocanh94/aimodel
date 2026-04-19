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

class ProductAnalyzerTemplate extends NodeTemplate
{
    public string $type { get => 'productAnalyzer'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Product Analyzer'; }
    public NodeCategory $category { get => NodeCategory::Input; }
    public string $description { get => 'Analyzes product images using vision AI to extract features, selling points, and target audience.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('images', 'Images', DataType::ImageAssetList, description: 'Product photos to analyze'),
                PortDefinition::input('description', 'Description', DataType::Text, required: false, description: 'Optional text description from seller'),
            ],
            outputs: [
                PortDefinition::output('analysis', 'Analysis', DataType::Json, description: 'Structured product analysis report'),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'analysisDepth' => ['sometimes', 'string', 'in:basic,detailed'],
            // Planner-set creative knobs.
            'analysis_angle' => ['sometimes', 'string', 'in:neutral,entertainment_ready,education_ready,aesthetic_ready'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'analysisDepth' => 'detailed',
            // Planner-set creative knobs.
            'analysis_angle' => 'neutral',
        ];
    }

    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: 'Analyze product images and extract features, selling points, target audience, and suggested mood. Tilts the analysis wording toward the downstream vibe.',
            position: 'early in the pipeline; feeds creative nodes',
            vibeImpact: VibeImpact::Neutral,
            humanGate: false,
            knobs: [
                new GuideKnob(
                    name: 'analysis_angle',
                    type: 'enum',
                    options: ['neutral', 'entertainment_ready', 'education_ready', 'aesthetic_ready'],
                    default: 'neutral',
                    effect: 'Tilts selling-point wording and suggestedMood toward the downstream vibe.',
                    vibeMapping: [
                        'funny_storytelling' => 'entertainment_ready',
                        'clean_education' => 'education_ready',
                        'aesthetic_mood' => 'aesthetic_ready',
                        'raw_authentic' => 'neutral',
                    ],
                ),
                new GuideKnob(
                    name: 'product_emphasis',
                    type: 'enum',
                    options: ['subtle', 'balanced', 'hero'],
                    default: 'balanced',
                    effect: 'Planner hint: which traits to foreground in the analysis report. Canonical on scriptWriter.',
                    vibeMapping: [
                        'funny_storytelling' => 'subtle',
                        'clean_education' => 'hero',
                        'aesthetic_mood' => 'subtle',
                        'raw_authentic' => 'balanced',
                    ],
                ),
            ],
            readsFrom: [],
            writesTo: ['storyWriter', 'scriptWriter', 'intentOutcomeSelector'],
            ports: [
                GuidePort::input('images', 'imageAssetList', true),
                GuidePort::input('description', 'text', false),
                GuidePort::output('analysis', 'json'),
            ],
            whenToInclude: 'always when a product is present in the brief',
            whenToSkip: 'when the workflow is product-agnostic (e.g. seasonal brand-mood content)',
        );
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $images = $ctx->inputValue('images') ?? [];
        $description = $ctx->inputValue('description') ?? '';

        if (is_array($description)) {
            $description = $description['text'] ?? json_encode($description);
        }

        $config = $ctx->config;
        $analysisDepth = $config['analysisDepth'] ?? 'detailed';

        // Extract image URLs for vision models
        $imageUrls = [];
        if (is_array($images)) {
            foreach ($images as $img) {
                if (is_string($img)) {
                    $imageUrls[] = $img;
                } elseif (is_array($img) && isset($img['url'])) {
                    $imageUrls[] = $img['url'];
                }
            }
        }

        // Also extract URLs from description text (user might paste URLs)
        $descText = (string) $description;
        if (preg_match_all('/https?:\/\/[^\s<>"]+\.(?:jpg|jpeg|png|webp|gif)/i', $descText, $urlMatches)) {
            $imageUrls = array_merge($imageUrls, $urlMatches[0]);
        }

        $input = [
            'systemPrompt' => $this->buildSystemPrompt($analysisDepth),
            'prompt' => $this->buildUserPrompt($images, $descText),
        ];

        if (!empty($imageUrls)) {
            $input['imageUrls'] = array_values(array_unique($imageUrls));
        }

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            $input,
            $config,
        );

        $analysis = $this->parseAnalysis($result);

        return [
            'analysis' => PortPayload::success(
                value: $analysis,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'analysis',
                previewText: ($analysis['productName'] ?? 'Product') . ' · ' . ($analysis['productType'] ?? 'unknown type'),
            ),
        ];
    }

    private function buildSystemPrompt(string $analysisDepth): string
    {
        $parts = [
            'You are a product analysis AI with vision capabilities.',
            'Analyze the provided product images and return a structured JSON report.',
            "Analysis depth: {$analysisDepth}.",
            'Return valid JSON with these fields:',
            '{"productType": string, "productName": string, "colors": string[], "materials": string[],',
            '"style": string, "sellingPoints": string[], "targetAudience": {"age": string, "gender": string, "occasion": string, "lifestyle": string},',
            '"pricePositioning": "budget"|"mid-range"|"premium"|"luxury", "suggestedMood": string}',
        ];

        return implode(' ', $parts);
    }

    private function buildUserPrompt(mixed $images, string $description): string
    {
        $imageCount = is_array($images) ? count($images) : 0;
        $prompt = "Analyze these {$imageCount} product image(s).";

        if ($description !== '') {
            $prompt .= " Seller description: {$description}";
        }

        return $prompt;
    }

    private function parseAnalysis(mixed $result): array
    {
        if (is_string($result)) {
            // Strip markdown code fences
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

            return $this->fallbackAnalysis();
        }

        if (is_array($result)) {
            // The StubAdapter returns script-like arrays for TextGeneration.
            // Check if the result looks like a product analysis (has productType key).
            if (isset($result['productType'])) {
                return $result;
            }

            // Fallback: the stub returned a script-shaped response, not product analysis.
            return $this->fallbackAnalysis();
        }

        return $this->fallbackAnalysis();
    }

    private function fallbackAnalysis(): array
    {
        return [
            'productType' => 'unknown',
            'productName' => 'Unidentified Product',
            'colors' => [],
            'materials' => [],
            'style' => 'unknown',
            'sellingPoints' => [],
            'targetAudience' => [
                'age' => 'unknown',
                'gender' => 'unisex',
                'occasion' => 'general',
                'lifestyle' => 'general',
            ],
            'pricePositioning' => 'mid-range',
            'suggestedMood' => 'neutral',
        ];
    }
}
