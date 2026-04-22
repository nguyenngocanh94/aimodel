<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * Input to {@see WorkflowPlanner::plan()}.
 *
 * Carries the user's verbatim brief plus optional hints. Everything except
 * `brief` is optional — the planner should still produce a valid plan when
 * only a brief is supplied.
 */
final readonly class PlannerInput
{
    /**
     * @param string      $brief      The user's raw brief, verbatim. Never summarized.
     * @param string|null $vibeMode   Optional hint; null = planner infers from brief.
     * @param string|null $product    Optional product name for tagging.
     * @param string|null $provider   laravel/ai provider override; null = config('ai.default').
     * @param string|null $model      Model override; null = provider default.
     * @param int         $maxRetries Retry budget after a failed attempt. Total attempts = maxRetries + 1.
     */
    public function __construct(
        public string $brief,
        public ?string $vibeMode = null,
        public ?string $product = null,
        public ?string $provider = null,
        public ?string $model = null,
        public int $maxRetries = 3,
    ) {
        if (trim($brief) === '') {
            throw new \InvalidArgumentException('PlannerInput.brief must be non-empty');
        }
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('PlannerInput.maxRetries must be >= 0');
        }
    }
}
