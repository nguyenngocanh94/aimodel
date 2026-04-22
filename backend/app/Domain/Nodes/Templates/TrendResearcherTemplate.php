<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\VibeImpact;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class TrendResearcherTemplate extends NodeTemplate
{
    use InteractsWithLlm;

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
            // Planner-set creative knobs.
            'trend_usage' => ['sometimes', 'string', 'in:ignore,informed,leaned_in,fully_on_trend'],
            'content_angle_focus' => ['sometimes', 'string', 'in:broad,vibe_matched,entertainment_first,info_first'],
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
            // Planner-set creative knobs.
            'trend_usage' => 'informed',
            'content_angle_focus' => 'vibe_matched',
        ];
    }

    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: 'Research current trends, cultural context and content angles for a market/platform. Canonical home for trend_usage.',
            position: 'early in the pipeline, alongside productAnalyzer; feeds creative nodes',
            vibeImpact: VibeImpact::Critical,
            humanGate: false,
            knobs: [
                new GuideKnob(
                    name: 'trend_usage',
                    type: 'enum',
                    options: ['ignore', 'informed', 'leaned_in', 'fully_on_trend'],
                    default: 'informed',
                    effect: 'Canonical. How aggressively to mine and surface current trends. Downstream creative nodes read it as a hint.',
                    vibeMapping: [
                        'funny_storytelling' => 'leaned_in',
                        'clean_education' => 'informed',
                        'aesthetic_mood' => 'informed',
                        'raw_authentic' => 'informed',
                    ],
                ),
                new GuideKnob(
                    name: 'content_angle_focus',
                    type: 'enum',
                    options: ['broad', 'vibe_matched', 'entertainment_first', 'info_first'],
                    default: 'vibe_matched',
                    effect: 'Constrains the content angles the researcher returns.',
                    vibeMapping: [
                        'funny_storytelling' => 'entertainment_first',
                        'clean_education' => 'info_first',
                        'aesthetic_mood' => 'vibe_matched',
                        'raw_authentic' => 'vibe_matched',
                    ],
                ),
                new GuideKnob(
                    name: 'native_tone',
                    type: 'enum',
                    options: ['polished', 'conversational', 'genz_native', 'ultra_slang'],
                    default: 'conversational',
                    effect: 'Planner hint: tone the trend brief should match. Canonical on scriptWriter.',
                    vibeMapping: [
                        'funny_storytelling' => 'genz_native',
                        'clean_education' => 'conversational',
                        'aesthetic_mood' => 'polished',
                        'raw_authentic' => 'ultra_slang',
                    ],
                ),
            ],
            readsFrom: [],
            writesTo: ['storyWriter', 'scriptWriter', 'intentOutcomeSelector'],
            ports: [
                GuidePort::input('context', 'json', false),
                GuidePort::input('topic', 'text', false),
                GuidePort::output('trendBrief', 'json'),
            ],
            whenToInclude: 'when current-trend grounding is needed (most TikTok/short-video pipelines)',
            whenToSkip: 'when the brief already specifies a fixed format and no trend awareness is required',
        );
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $context = $ctx->inputValue('context');
        $topic = $ctx->inputValue('topic');
        $config = $ctx->config;

        $trendBrief = $this->callStructuredText(
            $ctx,
            $this->buildSystemPrompt($config),
            $this->buildUserPrompt($context, $topic),
            $this->trendBriefSchema(),
            fn () => $this->stubTrendBrief(),
        );

        if ($trendBrief === []) {
            $trendBrief = $this->emptyTrendBrief();
        }

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
            "Analyze current trends and populate the structured trend brief schema.",
            "Respond in {$language} language where appropriate.",
            'Fields cover: trendingFormats, trendingHashtags, trendingSounds, culturalMoments, contentAngles,',
            'audienceInsights (tone/age/interests/behaviors), and avoidList (declining or risky topics/formats).',
        ];

        return implode(' ', $parts);
    }

    private function trendBriefSchema(): Closure
    {
        return static fn (JsonSchema $s) => [
            'trendingFormats'  => $s->array()->items($s->string()),
            'trendingHashtags' => $s->array()->items($s->string()),
            'trendingSounds'   => $s->array()->items($s->string()),
            'culturalMoments'  => $s->array()->items($s->string()),
            'contentAngles'    => $s->array()->items($s->string()),
            'audienceInsights' => $s->object([
                'tone'      => $s->string(),
                'age'       => $s->string(),
                'interests' => $s->array()->items($s->string()),
                'behaviors' => $s->array()->items($s->string()),
            ]),
            'avoidList' => $s->array()->items($s->string()),
        ];
    }

    private function stubTrendBrief(): array
    {
        return [
            'trendingFormats' => ['POV storytelling', 'before/after transformation'],
            'trendingHashtags' => ['#genz', '#vietnam', '#tiktok'],
            'trendingSounds' => ['upbeat indie pop', 'lo-fi'],
            'culturalMoments' => ['back-to-school season'],
            'contentAngles' => ['relatable humor', 'authentic testimonial'],
            'audienceInsights' => [
                'tone' => 'casual, authentic',
                'age' => '16-24',
                'interests' => ['beauty', 'lifestyle'],
                'behaviors' => ['scrolls quickly', 'values authenticity'],
            ],
            'avoidList' => ['overt hard-sell', 'cliché taglines'],
        ];
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
