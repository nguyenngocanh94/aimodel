<?php

declare(strict_types=1);

namespace Tests\Feature\Streaming;

use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\NodeExecutionContext;
use App\Services\ArtifactStoreContract;
use Laravel\Ai\AnonymousAgent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LP-C3: when the context has a token-delta sink, `callTextGeneration()` must
 * route through `$agent->stream()` and forward TextDelta events to the sink.
 */
final class StreamingTextGenerationTest extends TestCase
{
    #[Test]
    public function streams_deltas_when_sink_is_present(): void
    {
        // FakeTextGateway chunks the response by spaces into TextDelta events.
        AnonymousAgent::fake(fn () => 'Hello streaming world');

        $helper = new class {
            use InteractsWithLlm;

            public function run(NodeExecutionContext $ctx): string
            {
                return $this->callTextGeneration($ctx, 'system', 'prompt');
            }
        };

        $seen = [];
        $ctx = new NodeExecutionContext(
            nodeId: 'node-s',
            config: ['llm' => ['provider' => 'openai']],
            inputs: [],
            runId: 'run-s',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
            onTokenDelta: function (string $delta, string $mid) use (&$seen): void {
                $seen[] = $delta;
            },
        );

        $text = $helper->run($ctx);

        $this->assertSame('Hello streaming world', $text);
        // Fake gateway yields a TextDelta per space-separated word.
        $this->assertSame(['Hello', ' streaming', ' world'], $seen);
    }

    #[Test]
    public function falls_back_to_prompt_when_no_sink(): void
    {
        AnonymousAgent::fake(fn () => 'Plain response');

        $helper = new class {
            use InteractsWithLlm;

            public function run(NodeExecutionContext $ctx): string
            {
                return $this->callTextGeneration($ctx, 'system', 'prompt');
            }
        };

        $ctx = new NodeExecutionContext(
            nodeId: 'node-s',
            config: ['llm' => ['provider' => 'openai']],
            inputs: [],
            runId: 'run-s',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
            // no onTokenDelta sink
        );

        $this->assertSame('Plain response', $helper->run($ctx));
    }
}
