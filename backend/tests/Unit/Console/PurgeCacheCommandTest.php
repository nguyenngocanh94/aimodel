<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Models\RunCacheEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PurgeCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createEntry(string $key, string $lastAccessedDaysAgo): RunCacheEntry
    {
        return RunCacheEntry::create([
            'cache_key' => $key,
            'node_type' => 'prompt-refiner',
            'template_version' => '1.0.0',
            'output_payloads' => ['out' => 'data'],
            'created_at' => now()->subDays((int) $lastAccessedDaysAgo),
            'last_accessed_at' => now()->subDays((int) $lastAccessedDaysAgo),
        ]);
    }

    // ---------------------------------------------------------------
    // TTL expiry
    // ---------------------------------------------------------------

    public function test_deletes_expired_entries(): void
    {
        $this->createEntry('old-1', '10');
        $this->createEntry('old-2', '14');
        $this->createEntry('recent', '2');

        $this->artisan('cache:purge', ['--ttl-days' => 7])
            ->expectsOutputToContain('Deleted 2 expired cache entries')
            ->assertSuccessful();

        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'old-1']);
        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'old-2']);
        $this->assertDatabaseHas('run_cache_entries', ['cache_key' => 'recent']);
    }

    public function test_keeps_recent_entries(): void
    {
        $this->createEntry('fresh-1', '1');
        $this->createEntry('fresh-2', '3');
        $this->createEntry('fresh-3', '6');

        $this->artisan('cache:purge', ['--ttl-days' => 7])
            ->expectsOutputToContain('Deleted 0 expired cache entries')
            ->assertSuccessful();

        $this->assertSame(3, RunCacheEntry::count());
    }

    // ---------------------------------------------------------------
    // Dry-run
    // ---------------------------------------------------------------

    public function test_dry_run_does_not_delete(): void
    {
        $this->createEntry('old-entry', '10');
        $this->createEntry('new-entry', '1');

        $this->artisan('cache:purge', ['--ttl-days' => 7, '--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN] Would delete 1 expired entries')
            ->assertSuccessful();

        $this->assertSame(2, RunCacheEntry::count());
    }

    // ---------------------------------------------------------------
    // Max-entries cap
    // ---------------------------------------------------------------

    public function test_max_entries_caps_total(): void
    {
        // Create 5 entries with varying ages (none expired with default TTL)
        $this->createEntry('e1', '1');
        $this->createEntry('e2', '2');
        $this->createEntry('e3', '3');
        $this->createEntry('e4', '4');
        $this->createEntry('e5', '5');

        $this->artisan('cache:purge', ['--ttl-days' => 7, '--max-entries' => 3])
            ->expectsOutputToContain('Deleted 0 expired cache entries')
            ->expectsOutputToContain('Deleted 2 entries to enforce max 3 cap')
            ->assertSuccessful();

        $this->assertSame(3, RunCacheEntry::count());

        // The oldest entries should have been removed
        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'e5']);
        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'e4']);
        $this->assertDatabaseHas('run_cache_entries', ['cache_key' => 'e1']);
    }

    public function test_max_entries_dry_run_does_not_delete(): void
    {
        $this->createEntry('a1', '1');
        $this->createEntry('a2', '2');
        $this->createEntry('a3', '3');

        $this->artisan('cache:purge', ['--ttl-days' => 7, '--max-entries' => 1, '--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN] Would delete 2 entries to enforce max 1 cap')
            ->assertSuccessful();

        $this->assertSame(3, RunCacheEntry::count());
    }

    public function test_defaults_ttl_from_config(): void
    {
        config(['aimodel.cache_ttl_days' => 3]);

        $this->createEntry('just-over', '4');
        $this->createEntry('within', '2');

        $this->artisan('cache:purge')
            ->expectsOutputToContain('Deleted 1 expired cache entries')
            ->assertSuccessful();

        $this->assertDatabaseMissing('run_cache_entries', ['cache_key' => 'just-over']);
        $this->assertDatabaseHas('run_cache_entries', ['cache_key' => 'within']);
    }
}
