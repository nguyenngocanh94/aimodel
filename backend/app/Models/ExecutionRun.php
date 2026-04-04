<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExecutionRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'execution_runs';

    protected $fillable = [
        'workflow_id',
        'mode',
        'trigger',
        'target_node_id',
        'planned_node_ids',
        'status',
        'document_snapshot',
        'document_hash',
        'node_config_hashes',
        'started_at',
        'completed_at',
        'termination_reason',
    ];

    protected function casts(): array
    {
        return [
            'planned_node_ids' => 'array',
            'document_snapshot' => 'array',
            'node_config_hashes' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function nodeRunRecords(): HasMany
    {
        return $this->hasMany(NodeRunRecord::class, 'run_id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(Artifact::class, 'run_id');
    }
}
