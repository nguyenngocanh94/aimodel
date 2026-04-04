<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Artifact extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'artifacts';

    protected $fillable = [
        'run_id',
        'node_id',
        'name',
        'mime_type',
        'size_bytes',
        'disk',
        'path',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function executionRun(): BelongsTo
    {
        return $this->belongsTo(ExecutionRun::class, 'run_id');
    }
}
