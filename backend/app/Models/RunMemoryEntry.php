<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cross-run, cross-node key-value memory store.
 *
 * Scope convention:
 *  - "workflow:{slug}"                      — per-workflow memory
 *  - "workflow:{slug}:user:{tgChatId}"      — per-workflow, per-user
 *  - "node:{nodeType}"                      — per-node-type, workflow-agnostic
 *
 * Uniqueness is (scope, key). `expires_at` null means never expires.
 */
class RunMemoryEntry extends Model
{
    protected $table = 'run_memory';

    protected $fillable = [
        'workflow_id',
        'scope',
        'key',
        'value',
        'meta',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'meta' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Filter entries that are not expired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
