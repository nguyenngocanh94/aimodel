<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * A named structured agent that tags its system prompt with Anthropic
 * `cache_control: ephemeral` so the prompt is cached across calls.
 *
 * Used by templates with large, stable system prompts (StoryWriter,
 * ScriptWriter, SceneSplitter) via
 * {@see \App\Domain\Nodes\Concerns\InteractsWithLlm::callStructuredText}'s
 * `$agentFactory` hook. On non-Anthropic providers `providerOptions()`
 * returns `[]` — plain `system` string behaviour is preserved.
 *
 * @see \App\Domain\Planner\WorkflowPlannerAgent for the planner's dedicated class.
 */
final class CachedStructuredAgent extends AnonymousAgent implements HasProviderOptions, HasStructuredOutput
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
