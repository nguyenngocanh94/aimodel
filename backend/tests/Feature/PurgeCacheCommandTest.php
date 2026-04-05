<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RunCacheEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PurgeCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deletes_expired_entries(): void
    {
        // Create an old entry
        RunCacheEntry::create([
            'cache_key' => 'old-key',
            'node_type' => 'scriptWriter',
            'template_version' => '1.0.0',
            'output_payloads' => ['data' => 'old'],
            'created_at' => now()->subDays(30),
            'last_accessed_at' => now()->subDays(30),
        ]);

        // Create a recent entry
        RunCacheEntry::create([
            'cache_key' => 'new-key',
            'node_type' => 'scriptWriter',
            'template_version' => '1.0.0',
            'output_payloads' => ['data' => 'new'],
            'created_at' => now(),
            'last_accessed_at' => now(),
        ]);

        $this->artisan('cache:purge')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'old-key']);
        $this->assertDatabaseHas('run_cache_entries', ['cache_key' => 'new-key']);
    }

    #[Test]
    public function dry_run_does_not_delete(): void
    {
        RunCacheEntry::create([
            'cache_key' => 'old-key',
            'node_type' => 'test',
            'template_version' => '1.0.0',
            'output_payloads' => [],
            'created_at' => now()->subDays(30),
            'last_accessed_at' => now()->subDays(30),
        ]);

        $this->artisan('cache:purge --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('run_cache_entries', ['cache_key' => 'old-key']);
    }

    #[Test]
    public function enforces_max_entries_cap(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RunCacheEntry::create([
                'cache_key' => "key-{$i}",
                'node_type' => 'test',
                'template_version' => '1.0.0',
                'output_payloads' => [],
                'created_at' => now(),
                'last_accessed_at' => now()->subMinutes(5 - $i),
            ]);
        }

        $this->artisan('cache:purge --max-entries=3')
            ->assertExitCode(0);

        $this->assertSame(3, RunCacheEntry::count());
    }
}
