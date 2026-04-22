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
use App\Domain\Nodes\CachedStructuredAgent;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\VibeImpact;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class ScriptWriterTemplate extends NodeTemplate
{
    use InteractsWithLlm;

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
            // Planner-set creative knobs (canonical home for flat-script pipelines).
            'hook_intensity' => ['sometimes', 'string', 'in:low,medium,high,extreme'],
            'narrative_tension' => ['sometimes', 'string', 'in:low,medium,high'],
            'product_emphasis' => ['sometimes', 'string', 'in:subtle,balanced,hero'],
            'cta_softness' => ['sometimes', 'string', 'in:none,soft,medium,hard'],
            'native_tone' => ['sometimes', 'string', 'in:polished,conversational,genz_native,ultra_slang'],
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
            // Planner-set creative knobs.
            'hook_intensity' => 'high',
            'narrative_tension' => 'medium',
            'product_emphasis' => 'balanced',
            'cta_softness' => 'medium',
            'native_tone' => 'conversational',
        ];
    }

    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: 'Write a flat (non-story) script with hook, narration and CTA. Canonical home for narrative_tension, hook_intensity, product_emphasis, cta_softness, native_tone.',
            position: 'after intent-outcome-selector or format-library-matcher, before scene-splitter',
            vibeImpact: VibeImpact::Critical,
            humanGate: false,
            knobs: [
                new GuideKnob(
                    name: 'structure',
                    type: 'enum',
                    options: ['three_act', 'problem_solution', 'story_arc', 'listicle'],
                    default: 'three_act',
                    effect: 'Rhetorical framing of the script.',
                    vibeMapping: [
                        'funny_storytelling' => 'story_arc',
                        'clean_education' => 'problem_solution',
                        'aesthetic_mood' => 'story_arc',
                        'raw_authentic' => 'story_arc',
                    ],
                ),
                new GuideKnob(
                    name: 'hook_intensity',
                    type: 'enum',
                    options: ['low', 'medium', 'high', 'extreme'],
                    default: 'high',
                    effect: 'How hard the first 3 seconds grab the viewer. Canonical across flat-script pipelines.',
                    vibeMapping: [
                        'funny_storytelling' => 'high',
                        'clean_education' => 'medium',
                        'aesthetic_mood' => 'low',
                        'raw_authentic' => 'medium',
                    ],
                ),
                new GuideKnob(
                    name: 'narrative_tension',
                    type: 'enum',
                    options: ['low', 'medium', 'high'],
                    default: 'medium',
                    effect: 'How tense/dramatic the narrative gets. Canonical across flat-script pipelines.',
                    vibeMapping: [
                        'funny_storytelling' => 'high',
                        'clean_education' => 'medium',
                        'aesthetic_mood' => 'low',
                        'raw_authentic' => 'medium',
                    ],
                ),
                new GuideKnob(
                    name: 'humor_density',
                    type: 'enum',
                    options: ['none', 'punchline_only', 'throughout'],
                    default: 'punchline_only',
                    effect: 'Planner hint: how much humor is woven into the script. Canonical on storyWriter.',
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
                    effect: 'How prominent the product is in the script. Canonical across flat-script pipelines.',
                    vibeMapping: [
                        'funny_storytelling' => 'subtle',
                        'clean_education' => 'hero',
                        'aesthetic_mood' => 'subtle',
                        'raw_authentic' => 'balanced',
                    ],
                ),
                new GuideKnob(
                    name: 'cta_softness',
                    type: 'enum',
                    options: ['none', 'soft', 'medium', 'hard'],
                    default: 'medium',
                    effect: 'How hard the call-to-action pushes. Canonical across flat-script pipelines.',
                    vibeMapping: [
                        'funny_storytelling' => 'soft',
                        'clean_education' => 'hard',
                        'aesthetic_mood' => 'none',
                        'raw_authentic' => 'soft',
                    ],
                ),
                new GuideKnob(
                    name: 'native_tone',
                    type: 'enum',
                    options: ['polished', 'conversational', 'genz_native', 'ultra_slang'],
                    default: 'conversational',
                    effect: 'How native/casual the voice feels. Canonical across creative nodes.',
                    vibeMapping: [
                        'funny_storytelling' => 'genz_native',
                        'clean_education' => 'conversational',
                        'aesthetic_mood' => 'polished',
                        'raw_authentic' => 'ultra_slang',
                    ],
                ),
                new GuideKnob(
                    name: 'edit_pace',
                    type: 'enum',
                    options: ['slow_meditative', 'steady', 'fast_cut', 'rapid_fire'],
                    default: 'steady',
                    effect: 'Planner hint: cut rhythm. Shapes sentence density. Canonical on sceneSplitter.',
                    vibeMapping: [
                        'funny_storytelling' => 'fast_cut',
                        'clean_education' => 'steady',
                        'aesthetic_mood' => 'slow_meditative',
                        'raw_authentic' => 'steady',
                    ],
                ),
                new GuideKnob(
                    name: 'trend_usage',
                    type: 'enum',
                    options: ['ignore', 'informed', 'leaned_in', 'fully_on_trend'],
                    default: 'informed',
                    effect: 'Planner hint: how much to lean on the trend brief. Canonical on trendResearcher.',
                    vibeMapping: [
                        'funny_storytelling' => 'leaned_in',
                        'clean_education' => 'informed',
                        'aesthetic_mood' => 'informed',
                        'raw_authentic' => 'informed',
                    ],
                ),
            ],
            readsFrom: ['intentOutcomeSelector', 'formatLibraryMatcher', 'trendResearcher'],
            writesTo: ['sceneSplitter'],
            ports: [
                GuidePort::input('prompt', 'prompt', true),
                GuidePort::output('script', 'script'),
            ],
            whenToInclude: 'when vibe_mode is clean_education or when a flat (non-story) script is needed',
            whenToSkip: 'when the pipeline uses storyWriter / beatPlanner / moodSequencer',
        );
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->inputValue('prompt');
        $config = $ctx->config;

        $script = $this->callStructuredText(
            $ctx,
            $this->buildSystemPrompt($config),
            $this->buildUserPrompt($prompt, $config),
            $this->scriptSchema(),
            fn () => $this->stubScript(),
            fn (string $sys, Closure $schema) => new CachedStructuredAgent($sys, [], [], $schema),
        );

        if ($script === []) {
            $script = ['title' => 'Script', 'beats' => [], 'narration' => '', 'hook' => null, 'cta' => null];
        }

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

    private function scriptSchema(): Closure
    {
        return static fn (JsonSchema $s) => [
            'title'     => $s->string(),
            'hook'      => $s->string(),
            'beats'     => $s->array()->items($s->string()),
            'narration' => $s->string(),
            'cta'       => $s->string(),
        ];
    }

    private function stubScript(): array
    {
        return [
            'title' => 'The Journey Begins',
            'hook' => 'What if you could transform your ideas into reality?',
            'beats' => [
                'Introduce the central concept',
                'Show the transformation process',
                'Reveal the stunning result',
            ],
            'narration' => 'In a world of endless possibilities, one tool stands above the rest.',
            'cta' => 'Start creating today.',
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

        $parts[] = 'Populate all schema fields: title, hook, beats, narration, cta.';

        return implode(' ', $parts);
    }

    private function buildUserPrompt(mixed $prompt, array $config): string
    {
        $text = is_array($prompt) ? ($prompt['text'] ?? json_encode($prompt)) : (string) $prompt;
        $duration = $config['targetDurationSeconds'] ?? 90;

        return "Create a {$duration}-second video script about: {$text}";
    }

}
