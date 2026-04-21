<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\StructuredAnonymousAgent;

trait InteractsWithLlm
{
    /** @return array<string, array<int, string>> */
    public function llmConfigRules(): array
    {
        return [
            'llm'          => ['sometimes', 'array'],
            'llm.provider' => ['sometimes', 'string'],
            'llm.model'    => ['sometimes', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    public function llmDefaultConfig(): array
    {
        return ['llm' => ['provider' => '', 'model' => '']];
    }

    /**
     * Resolve the provider name the node should use.
     * Precedence: llm.provider → (legacy) provider → config('ai.default').
     * Logs a deprecation warning when the legacy flat key is consulted.
     */
    protected function resolveLlmProvider(array $config): string
    {
        $nested = $config['llm']['provider'] ?? '';
        if ($nested !== '') return $nested;

        $legacy = $config['provider'] ?? '';
        if ($legacy !== '') {
            $this->warnOnceOnLegacyLlmConfig('provider');
            return $legacy;
        }

        return (string) config('ai.default', 'fireworks');
    }

    /**
     * Resolve the model for a given provider.
     * Precedence: llm.model → (legacy) model → config('ai.providers.{provider}.default_model')
     * → '' (meaning: let the provider's driver pick).
     */
    protected function resolveLlmModel(array $config, string $provider): string
    {
        $nested = $config['llm']['model'] ?? '';
        if ($nested !== '') return $nested;

        $legacy = $config['model'] ?? '';
        if ($legacy !== '') {
            $this->warnOnceOnLegacyLlmConfig('model');
            return $legacy;
        }

        return (string) config("ai.providers.{$provider}.default_model", '');
    }

    /**
     * Call laravel/ai for a text-generation round trip.
     * Templates should call this from execute() instead of a Provider adapter.
     *
     * When the resolved provider is "stub", returns a JSON-encoded deterministic
     * canned payload for offline tests. Callers that JSON-decode the response
     * (the existing parse* helpers do) get a realistic shape back.
     */
    protected function callTextGeneration(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
        ?int $maxTokens = null,
    ): string {
        $provider = $this->resolveLlmProvider($ctx->config);

        if ($provider === 'stub') {
            return $this->stubTextGeneration($systemPrompt, $prompt);
        }

        $model    = $this->resolveLlmModel($ctx->config, $provider) ?: null;

        $agent = new AnonymousAgent($systemPrompt, [], []);
        $response = $agent->prompt(
            $prompt,
            provider: $provider,
            model: $model,
        );

        return (string) $response->text;
    }

    /**
     * Call laravel/ai for a structured-data round trip via
     * {@see StructuredAnonymousAgent}. The gateway enforces the schema, so the
     * response is already-decoded — no fence-strip, no json_decode ladder.
     *
     * The $schema closure receives an `Illuminate\Contracts\JsonSchema\JsonSchema`
     * instance and returns `['fieldName' => $s->string(), ...]`.
     *
     * Stub short-circuit: when provider is "stub" and a `$stubFallback` closure
     * is supplied, its return value is used verbatim (deterministic test path).
     * Otherwise stub returns `[]` and callers must tolerate it.
     *
     * @param Closure $schema        fn (JsonSchema $s): array<string, Type>
     * @param ?Closure $stubFallback fn (): array — deterministic stub output
     * @return array<string, mixed>
     */
    protected function callStructuredText(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
        Closure $schema,
        ?Closure $stubFallback = null,
    ): array {
        $provider = $this->resolveLlmProvider($ctx->config);

        if ($provider === 'stub') {
            return $stubFallback !== null ? (array) $stubFallback() : [];
        }

        $model = $this->resolveLlmModel($ctx->config, $provider) ?: null;

        $agent = new StructuredAnonymousAgent($systemPrompt, [], [], $schema);
        $response = $agent->prompt(
            $prompt,
            provider: $provider,
            model: $model,
        );

        if ($response instanceof StructuredAgentResponse) {
            $structured = $response->structured ?? [];
            if ($structured === []) {
                Log::warning('InteractsWithLlm: structured output missing', [
                    'template' => static::class,
                    'provider' => $provider,
                    'model'    => $model,
                ]);
            }
            return is_array($structured) ? $structured : [];
        }

        Log::warning('InteractsWithLlm: structured output missing (non-structured response)', [
            'template' => static::class,
            'provider' => $provider,
            'model'    => $model,
        ]);
        return [];
    }

    /**
     * Deterministic canned text-gen output used only when `provider: stub`
     * is configured (test path). Mirrors the shape legacy StubAdapter
     * emitted for `Capability::TextGeneration` — a JSON-encoded associative
     * array with title/hook/beats/narration/cta.
     */
    private function stubTextGeneration(string $systemPrompt, string $prompt): string
    {
        $seed = hash('sha256', $systemPrompt . '|' . $prompt);
        $idx = hexdec(substr($seed, 0, 4)) % 3;

        $payloads = [
            [
                'title' => 'The Journey Begins',
                'hook' => 'What if you could transform your ideas into reality?',
                'beats' => [
                    'Introduce the central concept',
                    'Show the transformation process',
                    'Reveal the stunning result',
                ],
                'narration' => 'In a world of endless possibilities, one tool stands above the rest.',
                'cta' => 'Start creating today.',
            ],
            [
                'title' => 'Behind the Scenes',
                'hook' => 'Ever wondered how the magic happens?',
                'beats' => [
                    'Set the stage with context',
                    'Walk through each step',
                    'Celebrate the outcome',
                ],
                'narration' => 'Every great creation starts with a single spark of inspiration.',
                'cta' => 'Join the creative revolution.',
            ],
            [
                'title' => 'A New Perspective',
                'hook' => 'See the world through a different lens.',
                'beats' => [
                    'Challenge conventional thinking',
                    'Present the alternative view',
                    'Connect with the audience',
                ],
                'narration' => 'Sometimes the best stories are the ones we never expected to tell.',
                'cta' => 'Discover what is possible.',
            ],
        ];

        return json_encode($payloads[$idx], JSON_THROW_ON_ERROR);
    }

    /** Guarded once-per-run deprecation logger. */
    private function warnOnceOnLegacyLlmConfig(string $field): void
    {
        static $warned = [];
        $key = static::class . ':' . $field;
        if (isset($warned[$key])) return;
        $warned[$key] = true;

        Log::warning('InteractsWithLlm: node config uses deprecated flat key', [
            'template'   => static::class,
            'field'      => $field,
            'migrate_to' => "llm.{$field}",
        ]);
    }
}
