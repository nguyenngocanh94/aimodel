<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\CachedStructuredAgent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Enums\Lab;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LC3 — Anthropic prompt caching for hot template system prompts
 * (StoryWriter, ScriptWriter, SceneSplitter).
 */
final class CachedStructuredAgentTest extends TestCase
{
    private function makeAgent(string $instructions = 'HOT SYSTEM PROMPT'): CachedStructuredAgent
    {
        return new CachedStructuredAgent(
            $instructions,
            [],
            [],
            static fn (JsonSchema $s) => ['foo' => $s->string()],
        );
    }

    #[Test]
    public function anthropic_provider_tags_system_with_cache_control_ephemeral(): void
    {
        $options = $this->makeAgent('cached')->providerOptions('anthropic');

        $this->assertArrayHasKey('system', $options);
        $this->assertSame('text', $options['system'][0]['type']);
        $this->assertSame('cached', $options['system'][0]['text']);
        $this->assertSame('ephemeral', $options['system'][0]['cache_control']['type']);
    }

    #[Test]
    public function anthropic_lab_enum_also_tags_cache_control(): void
    {
        $options = $this->makeAgent()->providerOptions(Lab::Anthropic);
        $this->assertSame('ephemeral', $options['system'][0]['cache_control']['type']);
    }

    #[Test]
    public function fireworks_returns_empty(): void
    {
        $this->assertSame([], $this->makeAgent()->providerOptions('fireworks'));
    }

    #[Test]
    public function openai_returns_empty(): void
    {
        $this->assertSame([], $this->makeAgent()->providerOptions('openai'));
    }

    #[Test]
    public function schema_closure_is_invoked(): void
    {
        $schema = $this->makeAgent()->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);
        $this->assertArrayHasKey('foo', $schema);
    }
}
