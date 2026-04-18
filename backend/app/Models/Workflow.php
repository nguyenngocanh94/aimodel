<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'schema_version',
        'tags',
        'document',
        'slug',
        'triggerable',
        'nl_description',
        'param_schema',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'document' => 'array',
            'schema_version' => 'integer',
            'param_schema' => 'array',
            'triggerable' => 'bool',
        ];
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class);
    }

    /**
     * Scope to only workflows that the agent is allowed to trigger.
     *
     * @param  Builder<Workflow>  $q
     * @return Builder<Workflow>
     */
    public function scopeTriggerable(Builder $q): Builder
    {
        return $q->where('triggerable', true);
    }

    /**
     * Scope to find a workflow by its stable slug identifier.
     *
     * @param  Builder<Workflow>  $q
     * @return Builder<Workflow>
     */
    public function scopeBySlug(Builder $q, string $slug): Builder
    {
        return $q->where('slug', $slug);
    }
}
