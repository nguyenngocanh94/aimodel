<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Historical {@see \App\Domain\Planner\WorkflowPlan} outputs, used by the
 * agentic WorkflowPlanner's PriorPlanRetrievalTool to surface relevant priors.
 *
 * Named {@see PastPlan} (not WorkflowPlan) to avoid collision with the
 * {@see \App\Domain\Planner\WorkflowPlan} value object.
 */
class PastPlan extends Model
{
    use HasUuids;

    protected $table = 'workflow_plans';

    protected $fillable = [
        'brief',
        'brief_hash',
        'plan',
        'provider',
        'model',
    ];

    protected function casts(): array
    {
        return [
            'plan' => 'array',
        ];
    }

    /**
     * Normalized SHA-256 hash for a brief — used for dedup.
     */
    public static function hashBrief(string $brief): string
    {
        return hash('sha256', trim($brief));
    }
}
