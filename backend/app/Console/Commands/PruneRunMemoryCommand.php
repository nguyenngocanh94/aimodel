<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RunMemoryEntry;
use Illuminate\Console\Command;

/**
 * Garbage-collect expired entries from the run_memory table.
 *
 * Scheduled daily at 03:15 from routes/console.php. Run manually:
 *   docker exec backend-app-1 php artisan memory:prune --dry-run
 */
class PruneRunMemoryCommand extends Command
{
    protected $signature = 'memory:prune
        {--dry-run : Report expired entries without deleting}';

    protected $description = 'Delete expired run_memory rows (expires_at in the past).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = RunMemoryEntry::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());

        $count = $query->count();

        if ($dryRun) {
            $this->info("[DRY RUN] Would delete {$count} expired run_memory entries.");
            return Command::SUCCESS;
        }

        $query->delete();
        $this->info("Deleted {$count} expired run_memory entries.");

        return Command::SUCCESS;
    }
}
