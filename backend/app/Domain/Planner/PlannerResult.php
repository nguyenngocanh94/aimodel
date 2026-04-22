<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * Output of {@see WorkflowPlanner::plan()}.
 *
 * Consumers:
 * - {@see \App\Services\TelegramAgent\Tools\ComposeWorkflowTool} (once swapped in)
 *   reads {@see $plan} + {@see $validation} to decide "proposal available / not available".
 * - 645.5 (creative-drift evaluator) reads {@see $plan} + {@see $steps} to score
 *   and to diagnose drift origins.
 * - 645.8 (integration tests) reads {@see $steps} to assert retry behaviour.
 */
final readonly class PlannerResult
{
    /**
     * @param WorkflowPlan|null         $plan          Populated on success; null when all attempts failed.
     * @param WorkflowPlanValidation    $validation    Result from {@see WorkflowPlanValidator::validate()} on the final attempt.
     * @param int                       $attempts      Number of LLM rounds executed (>= 1 unless LLM fully absent).
     * @param list<PlannerAttempt>      $steps         Trace of each attempt for debugging + drift-eval.
     * @param string                    $modelUsed     Model identifier actually used (audit).
     * @param string                    $providerUsed  Provider identifier actually used (audit).
     */
    public function __construct(
        public ?WorkflowPlan $plan,
        public WorkflowPlanValidation $validation,
        public int $attempts,
        public array $steps,
        public string $modelUsed,
        public string $providerUsed,
    ) {
        foreach ($steps as $i => $step) {
            if (!$step instanceof PlannerAttempt) {
                throw new \InvalidArgumentException("steps[{$i}] must be a PlannerAttempt instance");
            }
        }
    }

    public function successful(): bool
    {
        return $this->plan !== null && $this->validation->valid;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan?->toArray(),
            'validation' => $this->validation->toArray(),
            'attempts' => $this->attempts,
            'steps' => array_map(fn (PlannerAttempt $s) => $s->toArray(), $this->steps),
            'modelUsed' => $this->modelUsed,
            'providerUsed' => $this->providerUsed,
            'successful' => $this->successful(),
        ];
    }
}
