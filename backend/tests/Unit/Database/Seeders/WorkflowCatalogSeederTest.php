<?php

declare(strict_types=1);

namespace Tests\Unit\Database\Seeders;

use App\Models\Workflow;
use Database\Seeders\DemoWorkflowSeeder;
use Database\Seeders\HumanGateDemoSeeder;
use Database\Seeders\WorkflowCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkflowCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Seed prerequisite rows then run the catalog seeder.
     */
    private function seedAll(): void
    {
        $this->seed(HumanGateDemoSeeder::class);
        $this->seed(DemoWorkflowSeeder::class);
        $this->seed(WorkflowCatalogSeeder::class);
    }

    // ---------------------------------------------------------------------------
    // Triggerable count
    // ---------------------------------------------------------------------------

    #[Test]
    public function triggerable_scope_returns_exactly_two_rows_after_seeding(): void
    {
        $this->seedAll();

        $triggerable = Workflow::triggerable()->get();

        $this->assertCount(2, $triggerable);
    }

    // ---------------------------------------------------------------------------
    // Catalog field completeness
    // ---------------------------------------------------------------------------

    #[Test]
    public function each_triggerable_row_has_non_empty_slug_nl_description_and_param_schema(): void
    {
        $this->seedAll();

        $triggerable = Workflow::triggerable()->get();

        foreach ($triggerable as $workflow) {
            $this->assertNotEmpty($workflow->slug, "slug is empty for \"{$workflow->name}\"");
            $this->assertNotEmpty($workflow->nl_description, "nl_description is empty for \"{$workflow->name}\"");
            $this->assertNotNull($workflow->param_schema, "param_schema is null for \"{$workflow->name}\"");
            $this->assertIsArray($workflow->param_schema, "param_schema is not an array for \"{$workflow->name}\"");
        }
    }

    // ---------------------------------------------------------------------------
    // slug lookup
    // ---------------------------------------------------------------------------

    #[Test]
    public function by_slug_story_writer_gated_returns_non_null(): void
    {
        $this->seedAll();

        $found = Workflow::bySlug('story-writer-gated')->first();

        $this->assertNotNull($found);
        $this->assertSame('StoryWriter (per-node gate) – Telegram', $found->name);
    }

    #[Test]
    public function by_slug_tvc_pipeline_returns_non_null(): void
    {
        $this->seedAll();

        $found = Workflow::bySlug('tvc-pipeline')->first();

        $this->assertNotNull($found);
        $this->assertSame('M1 Demo – AI Video Pipeline', $found->name);
    }

    // ---------------------------------------------------------------------------
    // param_schema cast
    // ---------------------------------------------------------------------------

    #[Test]
    public function param_schema_cast_returns_array_for_triggerable_rows(): void
    {
        $this->seedAll();

        $storyWriter = Workflow::bySlug('story-writer-gated')->first();
        $this->assertIsArray($storyWriter->param_schema);
        $this->assertArrayHasKey('productBrief', $storyWriter->param_schema);

        $tvcPipeline = Workflow::bySlug('tvc-pipeline')->first();
        $this->assertIsArray($tvcPipeline->param_schema);
        $this->assertArrayHasKey('prompt', $tvcPipeline->param_schema);
    }

    // ---------------------------------------------------------------------------
    // Non-triggerable demos
    // ---------------------------------------------------------------------------

    #[Test]
    public function internal_demo_workflows_are_not_triggerable(): void
    {
        $this->seedAll();

        $uiDemo = Workflow::where('name', 'HumanGate Demo – UI')->first();
        $this->assertNotNull($uiDemo);
        $this->assertFalse($uiDemo->triggerable);
        $this->assertNull($uiDemo->slug);

        $telegramDemo = Workflow::where('name', 'HumanGate Demo – Telegram')->first();
        $this->assertNotNull($telegramDemo);
        $this->assertFalse($telegramDemo->triggerable);
        $this->assertNull($telegramDemo->slug);
    }

    // ---------------------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------------------

    #[Test]
    public function running_catalog_seeder_twice_is_idempotent(): void
    {
        $this->seed(HumanGateDemoSeeder::class);
        $this->seed(DemoWorkflowSeeder::class);

        // Run twice
        $this->seed(WorkflowCatalogSeeder::class);
        $this->seed(WorkflowCatalogSeeder::class);

        // Row count unchanged
        $this->assertCount(4, Workflow::all());

        // Triggerable count still exactly 2
        $this->assertCount(2, Workflow::triggerable()->get());

        // Slug values stable
        $this->assertSame(
            'story-writer-gated',
            Workflow::bySlug('story-writer-gated')->value('slug'),
        );
        $this->assertSame(
            'tvc-pipeline',
            Workflow::bySlug('tvc-pipeline')->value('slug'),
        );
    }

    // ---------------------------------------------------------------------------
    // Missing row graceful skip
    // ---------------------------------------------------------------------------

    #[Test]
    public function catalog_seeder_skips_gracefully_when_workflow_row_is_missing(): void
    {
        // Only seed HumanGateDemoSeeder — M1 Demo row is absent
        $this->seed(HumanGateDemoSeeder::class);

        // Should not throw; missing rows trigger a warning and are skipped
        $this->seed(WorkflowCatalogSeeder::class);

        // 3 rows from HumanGateDemoSeeder; M1 absent
        $this->assertCount(3, Workflow::all());

        // StoryWriter still got catalog fields from HumanGateDemoSeeder inline
        $found = Workflow::bySlug('story-writer-gated')->first();
        $this->assertNotNull($found);
    }
}
