<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RunMemoryEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunMemoryEntryTest extends TestCase
{
    #[Test]
    public function fillable_matches_schema(): void
    {
        $model = new RunMemoryEntry();

        $this->assertSame(
            ['workflow_id', 'scope', 'key', 'value', 'meta', 'expires_at'],
            $model->getFillable(),
        );
    }

    #[Test]
    public function casts_include_value_meta_and_expires_at(): void
    {
        $model = new RunMemoryEntry();
        $casts = $model->getCasts();

        $this->assertSame('array', $casts['value']);
        $this->assertSame('array', $casts['meta']);
        $this->assertSame('datetime', $casts['expires_at']);
    }

    #[Test]
    public function table_name_is_run_memory(): void
    {
        $this->assertSame('run_memory', (new RunMemoryEntry())->getTable());
    }

    #[Test]
    public function round_trip_value_casts_to_array(): void
    {
        $model = new RunMemoryEntry();
        $model->value = ['foo' => 'bar', 'n' => 42];

        $this->assertSame(['foo' => 'bar', 'n' => 42], $model->value);
    }
}
