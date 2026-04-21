<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlannerAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LC3 — Anthropic prompt caching. Verifies that WorkflowPlannerAgent emits a
 * `cache_control: ephemeral` system block only for Anthropic and `[]` elsewhere.
 */
final class WorkflowPlannerAgentTest extends TestCase
{
    private function makeAgent(string $instructions = 'SYSTEM PROMPT'): WorkflowPlannerAgent
    {
        return new WorkflowPlannerAgent(
            $instructions,
            [],
            [],
            static fn (JsonSchema $s) => ['foo' => $s->string()],
        );
    }

    #[Test]
    public function provider_options_for_anthropic_emits_cache_control_ephemeral_system_block(): void
    {
        $options = $this->makeAgent('CACHED SYSTEM PROMPT')->providerOptions('anthropic');

        $this->assertIsArray($options);
        $this->assertArrayHasKey('system', $options);
        $this->assertIsArray($options['system']);
        $this->assertArrayHasKey(0, $options['system']);
        $this->assertSame('text', $options['system'][0]['type']);
        $this->assertSame('CACHED SYSTEM PROMPT', $options['system'][0]['text']);
        $this->assertSame('ephemeral', $options['system'][0]['cache_control']['type']);
    }

    #[Test]
    public function provider_options_for_anthropic_lab_enum_emits_cache_control(): void
    {
        $options = $this->makeAgent()->providerOptions(Lab::Anthropic);

        $this->assertArrayHasKey('system', $options);
        $this->assertSame('ephemeral', $options['system'][0]['cache_control']['type']);
    }

    #[Test]
    public function provider_options_for_fireworks_returns_empty(): void
    {
        $this->assertSame([], $this->makeAgent()->providerOptions('fireworks'));
    }

    #[Test]
    public function provider_options_for_openai_returns_empty(): void
    {
        $this->assertSame([], $this->makeAgent()->providerOptions('openai'));
    }

    #[Test]
    public function schema_delegates_to_closure(): void
    {
        $agent = $this->makeAgent();
        $schema = $agent->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

        $this->assertArrayHasKey('foo', $schema);
    }

    #[Test]
    public function planner_uses_planner_agent_for_its_round_trip(): void
    {
        // Smoke: constructing the agent with WorkflowPlanner::planSchema works
        // and the agent carries the caching contract.
        $agent = new WorkflowPlannerAgent(
            'SYSTEM',
            [],
            [],
            WorkflowPlanner::planSchema(),
        );

        $this->assertInstanceOf(\Laravel\Ai\Contracts\HasProviderOptions::class, $agent);
        $this->assertInstanceOf(\Laravel\Ai\Contracts\HasStructuredOutput::class, $agent);
    }
}
