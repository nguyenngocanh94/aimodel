<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PastPlan;
use App\Models\Workflow;
use App\Services\AI\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Backfill pgvector embedding columns added in LK-G1 (workflows.catalog_embedding,
 * workflow_plans.brief_embedding) using VoyageAI (voyage-4, 1024 dim).
 *
 * Usage:
 *   php artisan embeddings:backfill                       # default: workflows
 *   php artisan embeddings:backfill --table=workflow_plans
 *
 * Safe to re-run; only rows whose embedding column is NULL are processed.
 */
final class BackfillEmbeddingsCommand extends Command
{
    protected $signature = 'embeddings:backfill
                            {--table=workflows : Table to backfill (workflows|workflow_plans)}
                            {--batch=16 : Texts per VoyageAI call (capped at 64)}';

    protected $description = 'Backfill pgvector embedding columns via VoyageAI (voyage-4).';

    public function handle(EmbeddingService $embedder): int
    {
        $table = (string) $this->option('table');
        $batch = max(1, min(64, (int) $this->option('batch')));

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->warn('Embeddings backfill requires pgsql (pgvector). Skipping.');
            return self::SUCCESS;
        }

        if (empty(config('ai.providers.voyageai.key'))) {
            $this->warn('VOYAGEAI_API_KEY not configured; backfill skipped.');
            return self::SUCCESS;
        }

        $start = microtime(true);
        $processed = 0;

        $handler = match ($table) {
            'workflows'       => fn () => $this->backfillWorkflows($embedder, $batch),
            'workflow_plans'  => fn () => $this->backfillPastPlans($embedder, $batch),
            default => null,
        };

        if ($handler === null) {
            $this->error("Unknown --table={$table}. Expected: workflows|workflow_plans");
            return self::FAILURE;
        }

        $processed = $handler();

        $elapsed = number_format(microtime(true) - $start, 2);
        $this->info("Backfilled {$processed} row(s) from `{$table}` in {$elapsed}s.");

        return self::SUCCESS;
    }

    private function backfillWorkflows(EmbeddingService $embedder, int $batch): int
    {
        $count = 0;

        Workflow::query()
            ->whereNull('catalog_embedding')
            ->orderBy('id')
            ->chunk($batch, function ($rows) use ($embedder, &$count) {
                $texts = $rows->map(fn (Workflow $w) => trim(
                    ($w->name ?? '') . "\n" . ($w->description ?? ''),
                ))->all();

                $vectors = $embedder->embedMany(array_values($texts));

                foreach ($rows as $i => $row) {
                    if (! isset($vectors[$i])) { continue; }
                    $this->storeVector('workflows', 'catalog_embedding', (string) $row->id, $vectors[$i]);
                    $count++;
                }
            });

        return $count;
    }

    private function backfillPastPlans(EmbeddingService $embedder, int $batch): int
    {
        $count = 0;

        PastPlan::query()
            ->whereNull('brief_embedding')
            ->orderBy('id')
            ->chunk($batch, function ($rows) use ($embedder, &$count) {
                $texts = $rows->map(fn (PastPlan $p) => (string) $p->brief)->all();
                $vectors = $embedder->embedMany(array_values($texts));

                foreach ($rows as $i => $row) {
                    if (! isset($vectors[$i])) { continue; }
                    $this->storeVector('workflow_plans', 'brief_embedding', (string) $row->id, $vectors[$i]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Write a vector to pgvector via a raw UPDATE (Eloquent cast round-trips
     * as JSON otherwise). The column type is `vector(1024)`.
     *
     * @param  array<int, float>  $vector
     */
    private function storeVector(string $table, string $column, string $id, array $vector): void
    {
        try {
            $literal = json_encode(array_values($vector), JSON_THROW_ON_ERROR);
            DB::update(
                "UPDATE {$table} SET {$column} = ?::vector WHERE id = ?",
                [$literal, $id],
            );
        } catch (Throwable $e) {
            $this->warn("Failed to store vector for {$table}.{$id}: {$e->getMessage()}");
        }
    }
}
