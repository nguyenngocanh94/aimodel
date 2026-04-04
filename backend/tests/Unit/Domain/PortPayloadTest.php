<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DataType;
use App\Domain\PortPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PortPayloadTest extends TestCase
{
    #[Test]
    public function success_factory_sets_status_and_produced_at(): void
    {
        $payload = PortPayload::success(
            value: ['text' => 'Hello world'],
            schemaType: DataType::Text,
            sourceNodeId: 'node-1',
            sourcePortKey: 'output',
        );

        $this->assertSame('success', $payload->status);
        $this->assertSame(['text' => 'Hello world'], $payload->value);
        $this->assertSame(DataType::Text, $payload->schemaType);
        $this->assertNotNull($payload->producedAt);
        $this->assertSame('node-1', $payload->sourceNodeId);
        $this->assertSame('output', $payload->sourcePortKey);
        $this->assertTrue($payload->isSuccess());
        $this->assertFalse($payload->isError());
        $this->assertFalse($payload->isIdle());
    }

    #[Test]
    public function error_factory_sets_error_message(): void
    {
        $payload = PortPayload::error(
            schemaType: DataType::Script,
            errorMessage: 'Something went wrong',
            sourceNodeId: 'node-2',
            sourcePortKey: 'result',
        );

        $this->assertSame('error', $payload->status);
        $this->assertNull($payload->value);
        $this->assertSame('Something went wrong', $payload->errorMessage);
        $this->assertNotNull($payload->producedAt);
        $this->assertSame('node-2', $payload->sourceNodeId);
        $this->assertSame('result', $payload->sourcePortKey);
        $this->assertTrue($payload->isError());
        $this->assertFalse($payload->isSuccess());
    }

    #[Test]
    public function idle_factory_creates_idle_payload(): void
    {
        $payload = PortPayload::idle(DataType::ImageAsset);

        $this->assertSame('idle', $payload->status);
        $this->assertNull($payload->value);
        $this->assertSame(DataType::ImageAsset, $payload->schemaType);
        $this->assertNull($payload->producedAt);
        $this->assertNull($payload->sourceNodeId);
        $this->assertTrue($payload->isIdle());
        $this->assertFalse($payload->isSuccess());
        $this->assertFalse($payload->isError());
    }

    #[Test]
    public function from_array_round_trips_correctly(): void
    {
        $original = PortPayload::success(
            value: ['data' => 'test'],
            schemaType: DataType::Json,
            sourceNodeId: 'node-3',
            sourcePortKey: 'output-1',
            previewText: 'Preview text',
            previewUrl: 'http://example.com/preview.png',
            sizeBytesEstimate: 1024,
        );

        $array = $original->toArray();
        $restored = PortPayload::fromArray($array);

        $this->assertSame($original->status, $restored->status);
        $this->assertSame($original->value, $restored->value);
        $this->assertSame($original->schemaType, $restored->schemaType);
        $this->assertSame($original->producedAt, $restored->producedAt);
        $this->assertSame($original->sourceNodeId, $restored->sourceNodeId);
        $this->assertSame($original->sourcePortKey, $restored->sourcePortKey);
        $this->assertSame($original->previewText, $restored->previewText);
        $this->assertSame($original->previewUrl, $restored->previewUrl);
        $this->assertSame($original->sizeBytesEstimate, $restored->sizeBytesEstimate);
    }

    #[Test]
    public function throws_on_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Status must be 'idle', 'success', or 'error'");

        new PortPayload(
            value: null,
            status: 'invalid',
            schemaType: DataType::Text,
        );
    }
}
