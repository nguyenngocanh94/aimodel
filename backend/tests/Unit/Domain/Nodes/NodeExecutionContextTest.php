<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\DataType;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\PortPayload;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use App\Services\Memory\RunMemoryStore;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NodeExecutionContextTest extends TestCase
{
    private function makeContext(
        array $inputs = [],
        ?ArtifactStoreContract $store = null,
        ?RunMemoryStore $memory = null,
        ?string $workflowSlug = null,
        ?Closure $onTokenDelta = null,
    ): NodeExecutionContext {
        return new NodeExecutionContext(
            nodeId: 'node-1',
            config: ['provider' => 'stub', 'model' => 'test'],
            inputs: $inputs,
            runId: 'run-abc',
            artifactStore: $store ?? $this->createMock(ArtifactStoreContract::class),
            memory: $memory,
            workflowSlug: $workflowSlug,
            onTokenDelta: $onTokenDelta,
        );
    }

    #[Test]
    public function input_returns_port_payload_by_key(): void
    {
        $payload = new PortPayload(
            value: 'hello world',
            status: 'success',
            schemaType: DataType::Text,
        );

        $ctx = $this->makeContext(['prompt' => $payload]);

        $this->assertSame($payload, $ctx->input('prompt'));
    }

    #[Test]
    public function input_returns_null_for_missing_key(): void
    {
        $ctx = $this->makeContext();

        $this->assertNull($ctx->input('nonexistent'));
    }

    #[Test]
    public function input_value_returns_the_value_property(): void
    {
        $payload = new PortPayload(
            value: ['scene1', 'scene2'],
            status: 'success',
            schemaType: DataType::SceneList,
        );

        $ctx = $this->makeContext(['scenes' => $payload]);

        $this->assertSame(['scene1', 'scene2'], $ctx->inputValue('scenes'));
    }

    #[Test]
    public function input_value_returns_null_for_missing_key(): void
    {
        $ctx = $this->makeContext();

        $this->assertNull($ctx->inputValue('missing'));
    }

    #[Test]
    public function store_artifact_delegates_to_artifact_store(): void
    {
        $artifact = $this->createMock(Artifact::class);
        $store = $this->createMock(ArtifactStoreContract::class);

        $store->expects($this->once())
            ->method('put')
            ->with('run-abc', 'node-1', 'output.png', 'binary-data', 'image/png')
            ->willReturn($artifact);

        $ctx = $this->makeContext(store: $store);

        $result = $ctx->storeArtifact('output.png', 'binary-data', 'image/png');

        $this->assertSame($artifact, $result);
    }

    // ─── Memory (LP-I3) ────────────────────────────────────────────────────

    #[Test]
    public function has_memory_reflects_injection(): void
    {
        $this->assertFalse($this->makeContext()->hasMemory());

        $store = $this->createMock(RunMemoryStore::class);
        $this->assertTrue($this->makeContext(memory: $store, workflowSlug: 'demo')->hasMemory());
    }

    #[Test]
    public function recall_returns_null_when_no_memory_attached(): void
    {
        $this->assertNull($this->makeContext()->recall('any-key'));
    }

    #[Test]
    public function recall_delegates_to_store_using_workflow_scope(): void
    {
        $store = $this->createMock(RunMemoryStore::class);
        $store->expects($this->once())
            ->method('get')
            ->with('workflow:demo', 'storyArc:last')
            ->willReturn(['title' => 'Hi']);

        $ctx = $this->makeContext(memory: $store, workflowSlug: 'demo');

        $this->assertSame(['title' => 'Hi'], $ctx->recall('storyArc:last'));
    }

    #[Test]
    public function remember_writes_through_store_with_provenance_meta(): void
    {
        $store = $this->createMock(RunMemoryStore::class);
        $expires = new \DateTimeImmutable('2030-01-01T00:00:00Z');
        $store->expects($this->once())
            ->method('put')
            ->with(
                'workflow:demo',
                'storyArc:last',
                ['title' => 'Hi'],
                ['source_run_id' => 'run-abc', 'source_node_id' => 'node-1'],
                $expires,
            );

        $ctx = $this->makeContext(memory: $store, workflowSlug: 'demo');

        $ctx->remember('storyArc:last', ['title' => 'Hi'], expires: $expires);
    }

    #[Test]
    public function remember_is_noop_without_memory(): void
    {
        // Should not throw.
        $this->makeContext()->remember('k', ['v' => 1]);
        $this->assertTrue(true);
    }

    #[Test]
    public function recall_accepts_custom_scope_override(): void
    {
        $store = $this->createMock(RunMemoryStore::class);
        $store->expects($this->once())
            ->method('get')
            ->with('node:StoryWriter', 'warmup')
            ->willReturn(null);

        $ctx = $this->makeContext(memory: $store, workflowSlug: 'demo');

        $this->assertNull($ctx->recall('warmup', scopeOverride: 'node:StoryWriter'));
    }

    // ─── Token delta sink (LP-C1) ──────────────────────────────────────────

    #[Test]
    public function has_token_delta_sink_reflects_closure(): void
    {
        $this->assertFalse($this->makeContext()->hasTokenDeltaSink());
        $this->assertTrue(
            $this->makeContext(onTokenDelta: fn () => null)->hasTokenDeltaSink(),
        );
    }

    #[Test]
    public function emit_token_delta_invokes_closure_with_context_fields(): void
    {
        $seen = [];
        $ctx = $this->makeContext(onTokenDelta: function (
            string $delta,
            string $messageId,
            string $nodeId,
            string $runId,
        ) use (&$seen): void {
            $seen[] = compact('delta', 'messageId', 'nodeId', 'runId');
        });

        $ctx->emitTokenDelta('Hel', 'msg-1');
        $ctx->emitTokenDelta('lo', 'msg-1');

        $this->assertSame([
            ['delta' => 'Hel', 'messageId' => 'msg-1', 'nodeId' => 'node-1', 'runId' => 'run-abc'],
            ['delta' => 'lo',  'messageId' => 'msg-1', 'nodeId' => 'node-1', 'runId' => 'run-abc'],
        ], $seen);
    }

    #[Test]
    public function emit_token_delta_is_noop_without_sink(): void
    {
        $this->makeContext()->emitTokenDelta('x', 'm');
        $this->assertTrue(true);
    }
}
