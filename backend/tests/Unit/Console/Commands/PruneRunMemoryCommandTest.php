<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Models\RunMemoryEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneRunMemoryCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dry_run_reports_count_without_deleting(): void
    {
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'expired',
            'value' => ['v' => 1],
            'expires_at' => now()->subMinute(),
        ]);
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'fresh',
            'value' => ['v' => 2],
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('memory:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 expired')
            ->assertSuccessful();

        $this->assertSame(2, RunMemoryEntry::count());
    }

    #[Test]
    public function delete_removes_only_expired_entries(): void
    {
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'expired1',
            'value' => ['v' => 1],
            'expires_at' => now()->subMinute(),
        ]);
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'expired2',
            'value' => ['v' => 2],
            'expires_at' => now()->subDay(),
        ]);
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'never',
            'value' => ['v' => 3],
            'expires_at' => null,
        ]);
        RunMemoryEntry::create([
            'scope' => 's',
            'key' => 'fresh',
            'value' => ['v' => 4],
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('memory:prune')
            ->expectsOutputToContain('Deleted 2 expired')
            ->assertSuccessful();

        $remaining = RunMemoryEntry::pluck('key')->sort()->values()->all();
        $this->assertSame(['fresh', 'never'], $remaining);
    }
}
