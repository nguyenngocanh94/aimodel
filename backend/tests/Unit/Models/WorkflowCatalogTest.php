<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Workflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkflowCatalogTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeWorkflow(array $overrides = []): Workflow
    {
        return Workflow::create(array_merge([
            'name' => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ], $overrides));
    }

    // ---------------------------------------------------------------------------
    // scopeTriggerable
    // ---------------------------------------------------------------------------

    #[Test]
    public function triggerable_scope_returns_only_triggerable_rows(): void
    {
        $triggerable = $this->makeWorkflow([
            'name' => 'Triggerable Workflow',
            'slug' => 'triggerable-one',
            'triggerable' => true,
        ]);

        $nonTriggerable = $this->makeWorkflow([
            'name' => 'Non-Triggerable Workflow',
            'slug' => 'non-triggerable-one',
            'triggerable' => false,
        ]);

        $results = Workflow::triggerable()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($triggerable));
        $this->assertFalse($results->contains(fn (Workflow $w) => $w->is($nonTriggerable)));
    }

    #[Test]
    public function triggerable_scope_returns_empty_when_no_triggerable_rows_exist(): void
    {
        $this->makeWorkflow(['triggerable' => false]);

        $results = Workflow::triggerable()->get();

        $this->assertCount(0, $results);
    }

    // ---------------------------------------------------------------------------
    // scopeBySlug
    // ---------------------------------------------------------------------------

    #[Test]
    public function by_slug_scope_matches_exactly(): void
    {
        $target = $this->makeWorkflow(['slug' => 'story-writer-gated', 'triggerable' => true]);
        $this->makeWorkflow(['slug' => 'tvc-pipeline', 'triggerable' => true]);

        $found = Workflow::bySlug('story-writer-gated')->first();

        $this->assertNotNull($found);
        $this->assertTrue($found->is($target));
    }

    #[Test]
    public function by_slug_scope_can_be_chained_with_triggerable(): void
    {
        $this->makeWorkflow(['slug' => 'story-writer-gated', 'triggerable' => true]);
        $this->makeWorkflow(['slug' => 'hidden-slug', 'triggerable' => false]);

        $found = Workflow::triggerable()->bySlug('story-writer-gated')->first();

        $this->assertNotNull($found);
        $this->assertSame('story-writer-gated', $found->slug);
    }

    #[Test]
    public function by_slug_scope_returns_null_for_nonexistent_slug(): void
    {
        $this->makeWorkflow(['slug' => 'existing-slug']);

        $found = Workflow::bySlug('does-not-exist')->first();

        $this->assertNull($found);
    }

    // ---------------------------------------------------------------------------
    // param_schema cast
    // ---------------------------------------------------------------------------

    #[Test]
    public function param_schema_casts_to_array_from_json(): void
    {
        $schema = ['productBrief' => ['required', 'string', 'min:5']];

        $workflow = $this->makeWorkflow([
            'slug' => 'cast-test',
            'param_schema' => $schema,
        ]);

        $fresh = Workflow::find($workflow->id);

        $this->assertIsArray($fresh->param_schema);
        $this->assertSame($schema, $fresh->param_schema);
    }

    #[Test]
    public function param_schema_is_null_when_not_set(): void
    {
        $workflow = $this->makeWorkflow(['slug' => 'no-schema']);

        $fresh = Workflow::find($workflow->id);

        $this->assertNull($fresh->param_schema);
    }

    // ---------------------------------------------------------------------------
    // triggerable cast
    // ---------------------------------------------------------------------------

    #[Test]
    public function triggerable_casts_to_bool(): void
    {
        $workflow = $this->makeWorkflow(['triggerable' => true]);
        $fresh = Workflow::find($workflow->id);

        $this->assertTrue($fresh->triggerable);
        $this->assertIsBool($fresh->triggerable);
    }

    // ---------------------------------------------------------------------------
    // Unique constraint on slug
    // ---------------------------------------------------------------------------

    #[Test]
    public function unique_constraint_on_slug_is_enforced(): void
    {
        $this->makeWorkflow(['slug' => 'duplicate-slug']);

        $this->expectException(QueryException::class);

        $this->makeWorkflow(['name' => 'Second Workflow', 'slug' => 'duplicate-slug']);
    }

    #[Test]
    public function multiple_null_slugs_are_allowed(): void
    {
        // NULL values should not violate the unique index
        $this->makeWorkflow(['slug' => null]);
        $this->makeWorkflow(['slug' => null]);

        $this->assertCount(2, Workflow::whereNull('slug')->get());
    }
}
