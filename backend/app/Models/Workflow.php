<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkflowFactory;
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
        'catalog_embedding',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'document' => 'array',
            'schema_version' => 'integer',
        ];
    }

    public function executionRuns(): HasMany
    {
        return $this->hasMany(ExecutionRun::class);
    }
}
