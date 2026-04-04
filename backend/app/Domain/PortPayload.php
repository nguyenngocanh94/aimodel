<?php

declare(strict_types=1);

namespace App\Domain;

readonly class PortPayload
{
    public function __construct(
        public mixed $value,
        public string $status,
        public DataType $schemaType,
        public ?string $producedAt = null,
        public ?string $sourceNodeId = null,
        public ?string $sourcePortKey = null,
        public ?string $previewText = null,
        public ?string $previewUrl = null,
        public ?int $sizeBytesEstimate = null,
        public ?string $errorMessage = null,
    ) {
        if (!in_array($status, ['idle', 'success', 'error'], true)) {
            throw new \InvalidArgumentException("Status must be 'idle', 'success', or 'error', got: {$status}");
        }
    }

    public static function success(
        mixed $value,
        DataType $schemaType,
        ?string $sourceNodeId = null,
        ?string $sourcePortKey = null,
        ?string $previewText = null,
        ?string $previewUrl = null,
        ?int $sizeBytesEstimate = null,
    ): self {
        return new self(
            value: $value,
            status: 'success',
            schemaType: $schemaType,
            producedAt: now()->toIso8601String(),
            sourceNodeId: $sourceNodeId,
            sourcePortKey: $sourcePortKey,
            previewText: $previewText,
            previewUrl: $previewUrl,
            sizeBytesEstimate: $sizeBytesEstimate,
        );
    }

    public static function error(
        DataType $schemaType,
        string $errorMessage,
        ?string $sourceNodeId = null,
        ?string $sourcePortKey = null,
    ): self {
        return new self(
            value: null,
            status: 'error',
            schemaType: $schemaType,
            producedAt: now()->toIso8601String(),
            sourceNodeId: $sourceNodeId,
            sourcePortKey: $sourcePortKey,
            errorMessage: $errorMessage,
        );
    }

    public static function idle(DataType $schemaType): self
    {
        return new self(
            value: null,
            status: 'idle',
            schemaType: $schemaType,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'] ?? null,
            status: $data['status'],
            schemaType: DataType::from($data['schemaType']),
            producedAt: $data['producedAt'] ?? null,
            sourceNodeId: $data['sourceNodeId'] ?? null,
            sourcePortKey: $data['sourcePortKey'] ?? null,
            previewText: $data['previewText'] ?? null,
            previewUrl: $data['previewUrl'] ?? null,
            sizeBytesEstimate: $data['sizeBytesEstimate'] ?? null,
            errorMessage: $data['errorMessage'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'status' => $this->status,
            'schemaType' => $this->schemaType->value,
            'producedAt' => $this->producedAt,
            'sourceNodeId' => $this->sourceNodeId,
            'sourcePortKey' => $this->sourcePortKey,
            'previewText' => $this->previewText,
            'previewUrl' => $this->previewUrl,
            'sizeBytesEstimate' => $this->sizeBytesEstimate,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isIdle(): bool
    {
        return $this->status === 'idle';
    }
}
