<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LK-G1 — pgvector embedding columns.
 *
 * Adds:
 *   workflows.catalog_embedding       vector(1024)
 *   workflow_plans.brief_embedding    vector(1024)
 *   personas.description_embedding    vector(1024)  (only if the personas
 *                                                    table exists — personas
 *                                                    are out of scope for
 *                                                    this epic, so we guard
 *                                                    the ALTER defensively)
 *
 * Each column gets an ivfflat cosine index with lists=100. On non-pgsql
 * drivers (e.g. the sqlite-backed unit-test harness) the migration is a no-op
 * — ALTER TABLE ... ADD COLUMN vector(...) is not portable.
 *
 * Voyage voyage-4 default dimension is 1024 — see config/ai.php voyageai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::statement('ALTER TABLE workflows ADD COLUMN IF NOT EXISTS catalog_embedding vector(1024)');
        DB::statement('ALTER TABLE workflow_plans ADD COLUMN IF NOT EXISTS brief_embedding vector(1024)');

        if (Schema::hasTable('personas')) {
            DB::statement('ALTER TABLE personas ADD COLUMN IF NOT EXISTS description_embedding vector(1024)');
        }

        // ivfflat cosine indexes. lists=100 is the laravel/ai recommended
        // default for small-to-mid catalogs. Use IF NOT EXISTS for idempotency.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS workflows_catalog_embedding_ivfflat '
            . 'ON workflows USING ivfflat (catalog_embedding vector_cosine_ops) WITH (lists = 100)'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS workflow_plans_brief_embedding_ivfflat '
            . 'ON workflow_plans USING ivfflat (brief_embedding vector_cosine_ops) WITH (lists = 100)'
        );

        if (Schema::hasTable('personas')) {
            DB::statement(
                'CREATE INDEX IF NOT EXISTS personas_description_embedding_ivfflat '
                . 'ON personas USING ivfflat (description_embedding vector_cosine_ops) WITH (lists = 100)'
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS workflow_plans_brief_embedding_ivfflat');
        DB::statement('DROP INDEX IF EXISTS workflows_catalog_embedding_ivfflat');

        DB::statement('ALTER TABLE workflow_plans DROP COLUMN IF EXISTS brief_embedding');
        DB::statement('ALTER TABLE workflows DROP COLUMN IF EXISTS catalog_embedding');

        if (Schema::hasTable('personas')) {
            DB::statement('DROP INDEX IF EXISTS personas_description_embedding_ivfflat');
            DB::statement('ALTER TABLE personas DROP COLUMN IF EXISTS description_embedding');
        }
    }
};
