<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\DataType;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\PortPayload;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NodeExecutionContextTest extends TestCase
{
    private function makeContext(
        array $inputs = [],
        ?ArtifactStoreContract $store = null,
    ): NodeExecutionContext {
        return new NodeExecutionContext(
            nodeId: 'node-1',
            config: ['provider' => 'stub', 'model' => 'test'],
            inputs: $inputs,
            runId: 'run-abc',
            artifactStore: $store ?? $this->createMock(ArtifactStoreContract::class),
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
}
