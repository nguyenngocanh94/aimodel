<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies LK-G1 embedding columns + ivfflat indexes are present after
 * migration on pgsql.
 *
 * The default test harness uses sqlite (:memory:), so the migration no-ops
 * there. This test short-circuits when not on pgsql rather than inventing
 * fake schema — a dedicated live-DB suite should run these checks against
 * a pgvector-enabled postgres (e.g. in CI).
 */
final class EmbeddingMigrationTest extends TestCase
{
    #[Test]
    public function embedding_columns_are_nullable_and_named_correctly_on_pgsql(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('LK-G1 embedding migration is pgsql-only; skipping on sqlite.');
        }

        $this->assertTrue(
            Schema::hasColumn('workflows', 'catalog_embedding'),
            'workflows.catalog_embedding should be created by LK-G1 migration',
        );
        $this->assertTrue(
            Schema::hasColumn('workflow_plans', 'brief_embedding'),
            'workflow_plans.brief_embedding should be created by LK-G1 migration',
        );

        // Verify the underlying column type is vector(1024), not just present.
        $workflowsType = DB::selectOne(
            'SELECT format_type(atttypid, atttypmod) AS t
             FROM pg_attribute
             WHERE attrelid = ?::regclass AND attname = ?',
            ['workflows', 'catalog_embedding'],
        );
        $this->assertNotNull($workflowsType);
        $this->assertSame('vector(1024)', $workflowsType->t);

        $priorsType = DB::selectOne(
            'SELECT format_type(atttypid, atttypmod) AS t
             FROM pg_attribute
             WHERE attrelid = ?::regclass AND attname = ?',
            ['workflow_plans', 'brief_embedding'],
        );
        $this->assertNotNull($priorsType);
        $this->assertSame('vector(1024)', $priorsType->t);
    }

    #[Test]
    public function migration_is_noop_on_sqlite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Only runs under sqlite test harness.');
        }

        // Columns should NOT exist on sqlite (ALTER TABLE vector() unsupported).
        $this->assertFalse(
            Schema::hasColumn('workflows', 'catalog_embedding'),
            'sqlite migration must be a no-op for pgsql-specific columns',
        );
    }
}
