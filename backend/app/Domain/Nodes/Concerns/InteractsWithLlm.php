<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

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
     * Call laravel/ai for a structured-data round trip.
     *
     * Interim helper for nodes that used to go through
     * `Capability::StructuredTransform`. Returns an array for direct
     * consumption. Until LC2 lands full `HasStructuredOutput` support,
     * this short-circuits stubs deterministically and otherwise asks the
     * text LLM to emit JSON and decodes it.
     *
     * @return array<string, mixed>
     */
    protected function callStructuredTransform(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
    ): array {
        $provider = $this->resolveLlmProvider($ctx->config);

        if ($provider === 'stub') {
            return $this->stubStructuredTransform($systemPrompt, $prompt);
        }

        $raw = $this->callTextGeneration($ctx, $systemPrompt, $prompt);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
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

    /**
     * Deterministic canned structured-transform output for `provider: stub`.
     * Mirrors legacy StubAdapter shape for StructuredTransform.
     *
     * @return array<string, mixed>
     */
    private function stubStructuredTransform(string $systemPrompt, string $prompt): array
    {
        $seed = hash('sha256', $systemPrompt . '|' . $prompt);
        $idx = hexdec(substr($seed, 0, 4)) % 2;

        return match ($idx) {
            0 => [
                'scenes' => [
                    ['id' => 'scene-1', 'description' => 'Opening shot establishing context', 'duration' => 3.0],
                    ['id' => 'scene-2', 'description' => 'Main action sequence', 'duration' => 5.0],
                    ['id' => 'scene-3', 'description' => 'Closing with call to action', 'duration' => 2.0],
                ],
            ],
            1 => [
                'scenes' => [
                    ['id' => 'scene-1', 'description' => 'Wide angle landscape view', 'duration' => 4.0],
                    ['id' => 'scene-2', 'description' => 'Close-up detail shot', 'duration' => 3.0],
                    ['id' => 'scene-3', 'description' => 'Dynamic transition sequence', 'duration' => 3.0],
                    ['id' => 'scene-4', 'description' => 'Final reveal and branding', 'duration' => 2.0],
                ],
            ],
        };
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
