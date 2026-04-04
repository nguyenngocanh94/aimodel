<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeRunRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'node_run_records';

    protected $fillable = [
        'run_id',
        'node_id',
        'status',
        'skip_reason',
        'blocked_by_node_ids',
        'input_payloads',
        'output_payloads',
        'error_message',
        'used_cache',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked_by_node_ids' => 'array',
            'input_payloads' => 'array',
            'output_payloads' => 'array',
            'used_cache' => 'boolean',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class, 'run_id');
    }
}
