<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Named agent for the workflow planner. Mirrors {@see StructuredAnonymousAgent}
 * but also implements {@see HasProviderOptions} so the planner's large
 * system prompt (≈2-3k tokens of catalog + rules) is cached on Anthropic via
 * ephemeral `cache_control` blocks. Non-Anthropic providers get `[]` — the
 * plain `system` string stays in the request body and behaviour is unchanged.
 *
 * Used by {@see WorkflowPlanner::invokeLlm} and {@see \App\Services\TelegramAgent\Tools\RefinePlanTool}
 * so cache reads hit across planner retries and refinement round-trips.
 */
class WorkflowPlannerAgent extends AnonymousAgent implements HasProviderOptions, HasStructuredOutput
{
    public $schema;

    public function __construct(string $instructions, iterable $messages, iterable $tools, Closure $schema)
    {
        parent::__construct($instructions, $messages, $tools);
        $this->schema = new SerializableClosure($schema);
    }

    public function schema(JsonSchema $schema): array
    {
        return call_user_func($this->schema, $schema);
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $name = $provider instanceof Lab ? $provider->value : $provider;
        if ($name !== 'anthropic') {
            // Fireworks/Groq/OpenAI — caching not available on this hot path.
            return [];
        }

        return [
            'system' => [[
                'type' => 'text',
                'text' => $this->instructions,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
        ];
    }
}
