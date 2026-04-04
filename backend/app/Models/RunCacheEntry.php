<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RunCacheEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'run_cache_entries';

    protected $fillable = [
        'cache_key',
        'node_type',
        'template_version',
        'output_payloads',
        'created_at',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'output_payloads' => 'array',
            'created_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }
}
