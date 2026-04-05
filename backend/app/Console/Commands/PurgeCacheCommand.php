<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RunCacheEntry;
use Illuminate\Console\Command;

class PurgeCacheCommand extends Command
{
    protected $signature = 'cache:purge
        {--ttl-days= : Days after which entries are considered expired}
        {--max-entries= : Maximum total entries to keep}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Purge expired run cache entries';

    public function handle(): int
    {
        $ttlDays = $this->option('ttl-days') !== null
            ? (int) $this->option('ttl-days')
            : (int) config('aimodel.cache_ttl_days', 7);
        $maxEntries = $this->option('max-entries') ?? config('aimodel.cache_max_entries');
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($ttlDays);

        // Find expired entries
        $expiredQuery = RunCacheEntry::where('last_accessed_at', '<', $cutoff);
        $expiredCount = $expiredQuery->count();

        if ($dryRun) {
            $this->info("[DRY RUN] Would delete {$expiredCount} expired entries (older than {$ttlDays} days).");
        } else {
            $expiredQuery->delete();
            $this->info("Deleted {$expiredCount} expired cache entries.");
        }

        // Enforce max entries cap
        if ($maxEntries !== null) {
            $maxEntries = (int) $maxEntries;
            $totalCount = RunCacheEntry::count();

            if ($totalCount > $maxEntries) {
                $excess = $totalCount - $maxEntries;
                $excessQuery = RunCacheEntry::orderBy('last_accessed_at')
                    ->limit($excess);

                if ($dryRun) {
                    $this->info("[DRY RUN] Would delete {$excess} entries to enforce max {$maxEntries} cap.");
                } else {
                    // Delete oldest entries exceeding cap
                    $ids = $excessQuery->pluck('id');
                    RunCacheEntry::whereIn('id', $ids)->delete();
                    $this->info("Deleted {$excess} entries to enforce max {$maxEntries} cap.");
                }
            }
        }

        return Command::SUCCESS;
    }
}
