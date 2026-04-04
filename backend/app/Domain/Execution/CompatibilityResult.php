<?php

declare(strict_types=1);

namespace App\Domain\Execution;

readonly class CompatibilityResult
{
    public function __construct(
        public bool $compatible,
        public string $severity, // 'ok', 'warning', 'error'
        public ?string $message = null,
        public bool $requiresCoercion = false,
        public ?string $coercionType = null,
    ) {
        if (!in_array($severity, ['ok', 'warning', 'error'], true)) {
            throw new \InvalidArgumentException("Severity must be 'ok', 'warning', or 'error'");
        }
    }

    public static function compatible(): self
    {
        return new self(
            compatible: true,
            severity: 'ok',
            message: 'Types are compatible',
        );
    }

    public static function compatibleWithCoercion(string $coercionType, ?string $message = null): self
    {
        return new self(
            compatible: true,
            severity: 'warning',
            message: $message ?? "Value will be wrapped in {$coercionType}",
            requiresCoercion: true,
            coercionType: $coercionType,
        );
    }

    public static function incompatible(string $reason): self
    {
        return new self(
            compatible: false,
            severity: 'error',
            message: $reason,
        );
    }

    public function isCompatible(): bool
    {
        return $this->compatible;
    }

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    public function toArray(): array
    {
        return [
            'compatible' => $this->compatible,
            'severity' => $this->severity,
            'message' => $this->message,
            'requiresCoercion' => $this->requiresCoercion,
            'coercionType' => $this->coercionType,
        ];
    }
}
