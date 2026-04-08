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

class TrendResearcherTemplate extends NodeTemplate
{
    public string $type { get => 'trendResearcher'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Trend Researcher'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Researches current trends, cultural context, and content angles using social-connected LLMs like Grok and Gemini.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('context', 'Context', DataType::Json, required: false),
                PortDefinition::input('topic', 'Topic', DataType::Text, required: false),
            ],
            outputs: [
                PortDefinition::output('trendBrief', 'Trend Brief', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'market' => ['required', 'string', 'in:vietnam,global,sea'],
            'platform' => ['required', 'string', 'in:tiktok,youtube,instagram,all'],
            'language' => ['required', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'grok-3',
            'market' => 'vietnam',
            'platform' => 'tiktok',
            'language' => 'vi',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $context = $ctx->inputValue('context');
        $topic = $ctx->inputValue('topic');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            [
                'systemPrompt' => $this->buildSystemPrompt($config),
                'prompt' => $this->buildUserPrompt($context, $topic),
            ],
            $config,
        );

        $trendBrief = $this->parseTrendBrief($result);

        return [
            'trendBrief' => PortPayload::success(
                value: $trendBrief,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'trendBrief',
                previewText: count($trendBrief['trendingFormats'] ?? []) . ' formats · '
                    . count($trendBrief['trendingHashtags'] ?? []) . ' hashtags · '
                    . count($trendBrief['contentAngles'] ?? []) . ' angles',
            ),
        ];
    }

    private function buildSystemPrompt(array $config): string
    {
        $market = $config['market'] ?? 'vietnam';
        $platform = $config['platform'] ?? 'tiktok';
        $language = $config['language'] ?? 'vi';

        $parts = [
            "You are a social media trend researcher specializing in {$market} market, {$platform} platform.",
            "Analyze current trends and return a structured trend brief.",
            "Respond in {$language} language where appropriate.",
            'Return valid JSON with the following keys:',
            '"trendingFormats" (array of current popular video formats, e.g. "POV videos", "before/after"),',
            '"trendingHashtags" (array of relevant hashtags for the market),',
            '"trendingSounds" (array of popular audio/music trends),',
            '"culturalMoments" (array of current cultural events, holidays, memes),',
            '"contentAngles" (array of suggested angles to approach the product/topic),',
            '"audienceInsights" (object describing what the target audience responds to right now),',
            '"avoidList" (array of topics/formats that are declining or risky).',
        ];

        return implode(' ', $parts);
    }

    private function buildUserPrompt(mixed $context, mixed $topic): string
    {
        $parts = [];

        if ($context !== null) {
            $contextText = is_array($context) ? json_encode($context) : (string) $context;
            $parts[] = "Product/brand context: {$contextText}";
        }

        if ($topic !== null) {
            $topicText = is_string($topic) ? $topic : json_encode($topic);
            $parts[] = "Research topic: {$topicText}";
        }

        if (empty($parts)) {
            $parts[] = 'Research current general trends for content creation.';
        }

        return implode("\n", $parts);
    }

    private function parseTrendBrief(mixed $result): array
    {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return $this->emptyTrendBrief();
        }

        if (is_array($result)) {
            return $result;
        }

        return $this->emptyTrendBrief();
    }

    private function emptyTrendBrief(): array
    {
        return [
            'trendingFormats' => [],
            'trendingHashtags' => [],
            'trendingSounds' => [],
            'culturalMoments' => [],
            'contentAngles' => [],
            'audienceInsights' => [],
            'avoidList' => [],
        ];
    }
}
