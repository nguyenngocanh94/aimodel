<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\Concerns\InteractsWithHuman;
use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\VibeImpact;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class StoryWriterTemplate extends NodeTemplate
{
    use InteractsWithHuman;
    use InteractsWithLlm;

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
        return array_merge([
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'targetDurationSeconds' => ['required', 'integer', 'min:15', 'max:120'],
            'storyFormula' => ['required', 'string', 'in:hero_journey,problem_agitation_solution,before_after_transformation,day_in_life,social_proof_story,emotional_hook'],
            'emotionalTone' => ['required', 'string', 'in:aspirational,relatable_humor,nostalgic,empowering,fomo_urgency,warm_family'],
            'productIntegrationStyle' => ['required', 'string', 'in:subtle_background,natural_use,hero_moment,transformation_reveal,comparison_story'],
            'genZAuthenticity' => ['required', 'string', 'in:low,medium,high,ultra'],
            'vietnameseDialect' => ['required', 'string', 'in:northern,central,southern,neutral'],
            'recallPreviousStory' => ['sometimes', 'boolean'],
        ], $this->humanGateConfigRules());
    }

    public function defaultConfig(): array
    {
        return array_merge([
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'gpt-4o',
            'targetDurationSeconds' => 30,
            'storyFormula' => 'problem_agitation_solution',
            'emotionalTone' => 'relatable_humor',
            'productIntegrationStyle' => 'natural_use',
            'genZAuthenticity' => 'high',
            'vietnameseDialect' => 'neutral',
            'recallPreviousStory' => true,
        ], $this->humanGateDefaultConfig());
    }

    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: 'Write a short story that pays off the hook promise, stays within vibe, and contains the product naturally. Outputs human-readable script + structured moments.',
            position: 'after hook selection gate, before casting',
            vibeImpact: VibeImpact::Critical,
            humanGate: true,
            knobs: [
                new GuideKnob(
                    name: 'story_tension_curve',
                    type: 'enum',
                    options: ['slow_build', 'fast_hit', 'rollercoaster'],
                    default: 'fast_hit',
                    effect: 'Controls how tension builds — gradual ramp, early peak, or multiple peaks.',
                    vibeMapping: [
                        'funny_storytelling' => 'fast_hit',
                        'clean_education' => 'slow_build',
                        'aesthetic_mood' => 'slow_build',
                        'raw_authentic' => 'slow_build',
                    ],
                ),
                new GuideKnob(
                    name: 'product_appearance_moment',
                    type: 'enum',
                    options: ['early', 'middle', 'twist', 'end'],
                    default: 'twist',
                    effect: 'When product enters the story. Later = less ad-like.',
                    vibeMapping: [
                        'funny_storytelling' => 'twist',
                        'clean_education' => 'early',
                        'aesthetic_mood' => 'middle',
                        'raw_authentic' => 'middle',
                    ],
                ),
                new GuideKnob(
                    name: 'humor_density',
                    type: 'enum',
                    options: ['none', 'punchline_only', 'throughout'],
                    default: 'throughout',
                    effect: 'How much humor is woven into the story.',
                    vibeMapping: [
                        'funny_storytelling' => 'throughout',
                        'clean_education' => 'none',
                        'aesthetic_mood' => 'none',
                        'raw_authentic' => 'none',
                    ],
                ),
                new GuideKnob(
                    name: 'story_versions_for_human',
                    type: 'int',
                    options: null,
                    default: 2,
                    effect: 'Number of story versions generated for human selection.',
                ),
                new GuideKnob(
                    name: 'max_moments',
                    type: 'int',
                    options: null,
                    default: 6,
                    effect: 'Maximum story moments. TikTok under 30s: use 4-5.',
                ),
                new GuideKnob(
                    name: 'target_duration_sec',
                    type: 'int',
                    options: null,
                    default: 35,
                    effect: 'Total video target duration distributed across moments.',
                ),
                new GuideKnob(
                    name: 'ending_type_preference',
                    type: 'enum',
                    options: ['twist_reveal', 'emotional_beat', 'soft_loop', 'call_to_action'],
                    default: 'twist_reveal',
                    effect: 'How the story ends — surprise, emotion, loop, or CTA.',
                    vibeMapping: [
                        'funny_storytelling' => 'twist_reveal',
                        'clean_education' => 'call_to_action',
                        'aesthetic_mood' => 'soft_loop',
                        'raw_authentic' => 'emotional_beat',
                    ],
                ),
                // Shared (planner-only hints) — canonical homes: scriptWriter / trendResearcher.
                new GuideKnob(
                    name: 'native_tone',
                    type: 'enum',
                    options: ['polished', 'conversational', 'genz_native', 'ultra_slang'],
                    default: 'genz_native',
                    effect: 'Planner hint: how native/casual the voice feels. Shapes dialogue phrasing. Canonical on scriptWriter.',
                    vibeMapping: [
                        'funny_storytelling' => 'genz_native',
                        'clean_education' => 'conversational',
                        'aesthetic_mood' => 'polished',
                        'raw_authentic' => 'ultra_slang',
                    ],
                ),
                new GuideKnob(
                    name: 'trend_usage',
                    type: 'enum',
                    options: ['ignore', 'informed', 'leaned_in', 'fully_on_trend'],
                    default: 'leaned_in',
                    effect: 'Planner hint: how much to lean on current trends from the trend brief. Canonical on trendResearcher.',
                    vibeMapping: [
                        'funny_storytelling' => 'leaned_in',
                        'clean_education' => 'informed',
                        'aesthetic_mood' => 'informed',
                        'raw_authentic' => 'informed',
                    ],
                ),
            ],
            readsFrom: ['humanGate', 'intentOutcomeSelector', 'truthConstraintGate', 'formatLibraryMatcher'],
            writesTo: ['casting', 'shotCompiler'],
            ports: [
                GuidePort::input('selected_hook', 'json', true),
                GuidePort::input('intent_pack', 'json', true),
                GuidePort::input('grounding', 'json', true),
                GuidePort::input('vibe_state', 'json', false),
                GuidePort::output('story_pack', 'json'),
            ],
            whenToInclude: 'when vibe_mode is funny_storytelling or raw_authentic',
            whenToSkip: 'when vibe_mode is clean_education or aesthetic_mood — use beat-planner or mood-sequencer instead',
        );
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $productAnalysis = $ctx->inputValue('productAnalysis');
        $trendBrief = $ctx->inputValue('trendBrief');
        $modelRoster = $ctx->inputValue('modelRoster');
        $seedIdea = $ctx->inputValue('seedIdea');
        $config = $ctx->config;

        // LP-I4: recall the last story arc from this workflow for style continuity.
        $previousStory = null;
        if (($config['recallPreviousStory'] ?? true) === true) {
            $previousStory = $ctx->recall('storyArc:last');
        }

        $storyArc = $this->callStructuredText(
            $ctx,
            $this->buildSystemPrompt($config),
            $this->buildUserPrompt($productAnalysis, $trendBrief, $modelRoster, $seedIdea, $config, $previousStory),
            $this->storySchema(),
            fn () => $this->stubStoryArc(),
        );

        if ($storyArc === []) {
            $storyArc = $this->emptyStoryArc();
        }

        // LP-I4: write-through on every successful execute. 7-day TTL.
        $ctx->remember('storyArc:last', $storyArc, expires: now()->addDays(7));

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

    /**
     * Extract a compact digest of a prior story arc — title, theme, hook only —
     * so we keep style continuity without blowing the token budget.
     */
    private function digestPreviousStory(array $previous): string
    {
        $title = trim((string) ($previous['title'] ?? ''));
        $theme = trim((string) ($previous['theme'] ?? ''));
        $hook  = trim((string) ($previous['hook'] ?? ''));

        $parts = [];
        if ($title !== '') $parts[] = "title: {$title}";
        if ($theme !== '') $parts[] = "theme: {$theme}";
        if ($hook !== '')  $parts[] = "hook: {$hook}";

        return implode(' · ', $parts);
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
            'Populate all schema fields: shots cover the full duration; cast names the lead and supporting characters;',
            'toneDirection and soundDirection guide the director; productMoment describes the key product integration.',
        ];

        return implode(' ', $parts);
    }

    private function storySchema(): Closure
    {
        return static fn (JsonSchema $s) => [
            'title'   => $s->string(),
            'theme'   => $s->string(),
            'formula' => $s->string(),
            'hook'    => $s->string(),
            'shots'   => $s->array()->items($s->object([
                'shotNumber'      => $s->integer(),
                'timestamp'       => $s->string(),
                'description'     => $s->string(),
                'dialogue'        => $s->string(),
                'emotion'         => $s->string(),
                'setting'         => $s->string(),
                'cameraDirection' => $s->string(),
            ])),
            'cast' => $s->object([
                'lead'       => $s->array()->items($s->string()),
                'supporting' => $s->array()->items($s->string()),
            ]),
            'toneDirection'  => $s->string(),
            'soundDirection' => $s->string(),
            'productMoment'  => $s->string(),
        ];
    }

    private function stubStoryArc(): array
    {
        return [
            'title' => 'The Journey Begins',
            'theme' => 'transformation',
            'formula' => 'problem_agitation_solution',
            'hook' => 'What if you could transform your ideas into reality?',
            'shots' => [
                [
                    'shotNumber' => 1,
                    'timestamp' => '0:00-0:05',
                    'description' => 'Introduce the central concept',
                    'dialogue' => '',
                    'emotion' => 'curiosity',
                    'setting' => 'home',
                    'cameraDirection' => 'medium shot',
                ],
                [
                    'shotNumber' => 2,
                    'timestamp' => '0:05-0:20',
                    'description' => 'Show the transformation process',
                    'dialogue' => '',
                    'emotion' => 'excitement',
                    'setting' => 'home',
                    'cameraDirection' => 'close-up',
                ],
                [
                    'shotNumber' => 3,
                    'timestamp' => '0:20-0:30',
                    'description' => 'Reveal the stunning result',
                    'dialogue' => '',
                    'emotion' => 'delight',
                    'setting' => 'home',
                    'cameraDirection' => 'wide shot',
                ],
            ],
            'cast' => ['lead' => ['protagonist'], 'supporting' => []],
            'toneDirection' => 'warm and relatable',
            'soundDirection' => 'upbeat acoustic',
            'productMoment' => 'product appears as a natural solution in the reveal',
        ];
    }

    private function buildUserPrompt(
        mixed $productAnalysis,
        mixed $trendBrief,
        mixed $modelRoster,
        mixed $seedIdea,
        array $config,
        ?array $previousStory = null,
    ): string {
        $parts = [];

        if ($previousStory !== null) {
            $digest = $this->digestPreviousStory($previousStory);
            if ($digest !== '') {
                $parts[] = "Previous story for this workflow (for style consistency, do not copy): {$digest}";
            }
        }

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

        $history = $config['_humanFeedbackHistory'] ?? [];
        if (!empty($history)) {
            $parts[] = "---\nPrevious drafts were rejected. Human feedback across rounds (latest last):";
            foreach ($history as $i => $note) {
                $parts[] = sprintf('  %d. %s', $i + 1, $note);
            }
            $parts[] = 'Revise the story to honour the latest feedback while preserving anything that has worked so far.';
        }

        return implode("\n", $parts);
    }

    protected function humanGateFormatMessage(array $outputs, array $config): string
    {
        $arc = $outputs['storyArc'] ?? null;
        $value = $arc?->value;

        if (!is_array($value)) {
            return (string) ($arc?->previewText ?? 'Story draft ready — please review.');
        }

        $attempt = count($config['_humanFeedbackHistory'] ?? []) + 1;
        $title = $value['title'] ?? '(untitled)';
        $hook = $value['hook'] ?? '';
        $beats = array_map(
            fn ($shot) => is_array($shot)
                ? ($shot['description'] ?? $shot['beat'] ?? json_encode($shot, JSON_UNESCAPED_UNICODE))
                : (string) $shot,
            $value['shots'] ?? $value['beats'] ?? [],
        );

        $lines = [
            "📝 *Story draft — round {$attempt}*",
            "",
            "*{$title}*",
        ];

        if ($hook !== '') {
            $lines[] = "";
            $lines[] = "Hook: _{$hook}_";
        }

        if (!empty($beats)) {
            $lines[] = "";
            $lines[] = "Beats:";
            foreach ($beats as $i => $beat) {
                $lines[] = sprintf('  %d. %s', $i + 1, $beat);
            }
        }

        $lines[] = "";
        $lines[] = "Reply *1* to approve, *2* to revise, or send feedback to re-draft.";

        return implode("\n", $lines);
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
