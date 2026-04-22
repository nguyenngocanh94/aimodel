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
        'slug',
        'triggerable',
        'description',
        'nl_description',
        'schema_version',
        'tags',
        'param_schema',
        'document',
        'catalog_embedding',
    ];

    protected function casts(): array
    {
        return [
            'tags'           => 'array',
            'document'       => 'array',
            'param_schema'   => 'array',
            'schema_version' => 'integer',
            'triggerable'    => 'boolean',
        ];
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class);
    }

    /** Scope to workflows the Telegram/MCP agent is allowed to run. */
    public function scopeTriggerable(Builder $query): Builder
    {
        return $query->where('triggerable', true);
    }

    /** Scope by slug. Chain after triggerable() for user-facing catalog lookups. */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
