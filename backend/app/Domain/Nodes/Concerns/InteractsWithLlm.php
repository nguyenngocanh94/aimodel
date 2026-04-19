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
     * Templates should call this from execute() instead of ProviderRouter.
     */
    protected function callTextGeneration(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
        ?int $maxTokens = null,
    ): string {
        $provider = $this->resolveLlmProvider($ctx->config);
        $model    = $this->resolveLlmModel($ctx->config, $provider) ?: null;

        $agent = new AnonymousAgent($systemPrompt, [], []);
        $response = $agent->prompt(
            $prompt,
            provider: $provider,
            model: $model,
        );

        return (string) $response->text;
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
