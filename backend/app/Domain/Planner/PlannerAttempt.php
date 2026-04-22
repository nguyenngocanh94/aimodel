<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * One round in the planner retry loop.
 *
 * Stored as an element of {@see PlannerResult::$steps}. Useful for debugging
 * which attempt succeeded, what the LLM returned, and which validation errors
 * drove the retry.
 */
final readonly class PlannerAttempt
{
    /**
     * @param int                       $round            1-indexed attempt number.
     * @param string                    $promptUsed       The exact prompt body sent to the LLM this round.
     * @param string                    $rawLlmOutput     Verbatim LLM text response.
     * @param WorkflowPlan|null         $parsedPlan       Hydrated plan (null if JSON parse failed).
     * @param WorkflowPlanValidation|null $validation     Validator result (null if parse failed).
     * @param string|null               $parseError       Parser error message when parsedPlan is null.
     */
    public function __construct(
        public int $round,
        public string $promptUsed,
        public string $rawLlmOutput,
        public ?WorkflowPlan $parsedPlan,
        public ?WorkflowPlanValidation $validation,
        public ?string $parseError = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'round' => $this->round,
            'promptUsed' => $this->promptUsed,
            'rawLlmOutput' => $this->rawLlmOutput,
            'parsedPlan' => $this->parsedPlan?->toArray(),
            'validationErrors' => $this->validation?->errors ?? [],
            'validationWarnings' => $this->validation?->warnings ?? [],
            'parseError' => $this->parseError,
        ];
    }
}
