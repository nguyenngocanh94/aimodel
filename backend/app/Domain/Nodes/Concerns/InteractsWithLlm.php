<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
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
     * Provider resolution precedence (LP-H3):
     *  1. `providerChain` array on the node config (per-node failover override).
     *  2. `llm.provider` / legacy `provider` — single provider, no failover.
     *  3. Global `config('ai.failover.text')` — ordered failover chain.
     *  4. `config('ai.default')` scalar.
     *
     * When the resolved provider is "stub", returns a JSON-encoded deterministic
     * canned payload for offline tests.
     */
    protected function callTextGeneration(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
        ?int $maxTokens = null,
    ): string {
        $providerArg = $this->resolveTextProviderArg($ctx->config);

        // Stub short-circuit — canned, offline output.
        if ($providerArg === 'stub'
            || (is_array($providerArg) && in_array('stub', $providerArg, true))
        ) {
            return $this->stubTextGeneration($systemPrompt, $prompt);
        }

        // Only a scalar (single-provider) path resolves a specific model; chains
        // let each provider fall back to its own default_model.
        $model = is_string($providerArg)
            ? ($this->resolveLlmModel($ctx->config, $providerArg) ?: null)
            : null;

        $agent = new AnonymousAgent($systemPrompt, [], []);
        $startedAtMs = (int) round(microtime(true) * 1000);
        // #region agent log
        $this->debugLogLlm('initial', 'H6', 'InteractsWithLlm.php:111', 'llm_text_request_start', [
            'template' => static::class,
            'providerArgType' => is_array($providerArg) ? 'chain' : 'single',
            'provider' => is_string($providerArg) ? $providerArg : null,
            'providerChain' => is_array($providerArg) ? $providerArg : [],
            'model' => $model,
            'promptLength' => mb_strlen($prompt),
            'systemPromptLength' => mb_strlen($systemPrompt),
            'hasTokenSink' => $ctx->hasTokenDeltaSink(),
            'maxTokens' => $maxTokens,
        ]);
        // #endregion

        // LP-C3: switch to stream() when the context has an attached token-delta
        // sink (e.g., the run-page SSE subscriber). Each TextDelta is forwarded
        // into $ctx->emitTokenDelta() which the RunExecutor broadcasts. The
        // non-streaming path is kept for CLI / queued workers that have no
        // subscriber and don't need the per-token overhead.
        if ($ctx->hasTokenDeltaSink()) {
            try {
                $response = $agent->stream(
                    $prompt,
                    provider: $providerArg,
                    model: $model,
                );

                $response->each(function ($event) use ($ctx): void {
                    if ($event instanceof TextDelta) {
                        $ctx->emitTokenDelta($event->delta, $event->messageId);
                    }
                });

                $text = (string) $response->text;
                // #region agent log
                $this->debugLogLlm('initial', 'H7', 'InteractsWithLlm.php:146', 'llm_text_request_success_stream', [
                    'template' => static::class,
                    'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                    'responseLength' => mb_strlen($text),
                ]);
                // #endregion
                // After iteration, StreamableAgentResponse populates $response->text
                // by combining all TextDelta events (see vendor L142-144).
                return $text;
            } catch (\Throwable $e) {
                // #region agent log
                $this->debugLogLlm('initial', 'H8', 'InteractsWithLlm.php:155', 'llm_text_request_error_stream', [
                    'template' => static::class,
                    'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                    'error' => $e->getMessage(),
                    'exceptionClass' => $e::class,
                ]);
                // #endregion
                throw $e;
            }
        }

        try {
            $response = $agent->prompt(
                $prompt,
                provider: $providerArg,
                model: $model,
            );

            $text = (string) $response->text;
            // #region agent log
            $this->debugLogLlm('initial', 'H7', 'InteractsWithLlm.php:175', 'llm_text_request_success_prompt', [
                'template' => static::class,
                'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                'responseLength' => mb_strlen($text),
            ]);
            // #endregion
            return $text;
        } catch (\Throwable $e) {
            // #region agent log
            $this->debugLogLlm('initial', 'H8', 'InteractsWithLlm.php:184', 'llm_text_request_error_prompt', [
                'template' => static::class,
                'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                'error' => $e->getMessage(),
                'exceptionClass' => $e::class,
            ]);
            // #endregion
            throw $e;
        }
    }

    /**
     * Resolve the `provider` argument to pass to `$agent->prompt()`.
     *
     * @return string|array<int, string>
     */
    protected function resolveTextProviderArg(array $config): string|array
    {
        // 1. Per-node providerChain override — highest precedence.
        $override = $config['providerChain'] ?? null;
        if (is_array($override) && $override !== []) {
            return array_values(array_map('strval', $override));
        }

        // 2. Explicit single-provider (legacy flat or llm.provider).
        $explicit = $config['llm']['provider'] ?? $config['provider'] ?? '';
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        // 3. Global failover chain from config/ai.php.
        $chain = (array) config('ai.failover.text', []);
        if ($chain !== []) {
            return array_values(array_map('strval', $chain));
        }

        // 4. Fallback to the legacy scalar default.
        return (string) config('ai.default', 'fireworks');
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
        ?Closure $agentFactory = null,
    ): array {
        $provider = $this->resolveLlmProvider($ctx->config);

        if ($provider === 'stub') {
            return $stubFallback !== null ? (array) $stubFallback() : [];
        }

        $model = $this->resolveLlmModel($ctx->config, $provider) ?: null;

        // LC3: templates with large, stable system prompts can provide a factory
        // that returns a named agent implementing HasProviderOptions for
        // Anthropic prompt caching. Default is StructuredAnonymousAgent.
        $agent = $agentFactory !== null
            ? $agentFactory($systemPrompt, $schema)
            : new StructuredAnonymousAgent($systemPrompt, [], [], $schema);
        $startedAtMs = (int) round(microtime(true) * 1000);
        // #region agent log
        $this->debugLogLlm('initial', 'H6', 'InteractsWithLlm.php:238', 'llm_structured_request_start', [
            'template' => static::class,
            'provider' => $provider,
            'model' => $model,
            'promptLength' => mb_strlen($prompt),
            'systemPromptLength' => mb_strlen($systemPrompt),
        ]);
        // #endregion
        try {
            $response = $agent->prompt(
                $prompt,
                provider: $provider,
                model: $model,
            );
        } catch (\Throwable $e) {
            // #region agent log
            $this->debugLogLlm('initial', 'H8', 'InteractsWithLlm.php:251', 'llm_structured_request_error', [
                'template' => static::class,
                'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                'error' => $e->getMessage(),
                'exceptionClass' => $e::class,
            ]);
            // #endregion
            throw $e;
        }

        if ($response instanceof StructuredAgentResponse) {
            $structured = $response->structured ?? [];
            // #region agent log
            $this->debugLogLlm('initial', 'H7', 'InteractsWithLlm.php:264', 'llm_structured_request_success', [
                'template' => static::class,
                'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
                'hasStructuredPayload' => $structured !== [],
                'structuredType' => gettype($structured),
            ]);
            // #endregion
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
        // #region agent log
        $this->debugLogLlm('initial', 'H7', 'InteractsWithLlm.php:288', 'llm_structured_request_non_structured_response', [
            'template' => static::class,
            'durationMs' => (int) round(microtime(true) * 1000) - $startedAtMs,
            'responseClass' => $response::class,
        ]);
        // #endregion
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

    /**
     * @param array<string, mixed> $data
     */
    private function debugLogLlm(string $runId, string $hypothesisId, string $location, string $message, array $data = []): void
    {
        $payload = [
            'sessionId' => '477860',
            'runId' => $runId,
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ];

        // #region agent log
        Log::info('debug.llm', $payload);
        // #endregion

        try {
            file_put_contents(
                '/Volumes/Work/Workspace/AiModel/.cursor/debug-477860.log',
                json_encode($payload, JSON_THROW_ON_ERROR) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // no-op: fallback Log::info above already captures this event.
        }
    }
}
