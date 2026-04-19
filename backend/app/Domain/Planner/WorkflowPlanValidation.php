<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * Result of WorkflowPlanValidator::validate().
 *
 * Error shape (stable, machine-consumable):
 *   {
 *     path: string,      // dot-notation into the plan, e.g. "nodes[2].config.channel"
 *     code: string,      // stable error code enum (see WorkflowPlanValidator::CODE_*)
 *     message: string,   // human-readable, actionable message
 *     context?: array,   // optional structured context (e.g. allowed values)
 *   }
 */
final readonly class WorkflowPlanValidation
{
    /**
     * @param list<array{path: string, code: string, message: string, context?: array<string, mixed>}> $errors
     * @param list<array{path: string, code: string, message: string, context?: array<string, mixed>}> $warnings
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public static function ok(array $warnings = []): self
    {
        return new self(valid: true, errors: [], warnings: $warnings);
    }

    /**
     * @param list<array{path: string, code: string, message: string, context?: array<string, mixed>}> $errors
     * @param list<array{path: string, code: string, message: string, context?: array<string, mixed>}> $warnings
     */
    public static function withErrors(array $errors, array $warnings = []): self
    {
        return new self(valid: false, errors: $errors, warnings: $warnings);
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Codes present in $errors (unique, for quick assertions in tests/UI).
     *
     * @return list<string>
     */
    public function errorCodes(): array
    {
        return array_values(array_unique(array_map(fn (array $e) => $e['code'], $this->errors)));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
