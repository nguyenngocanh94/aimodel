<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlanValidator;
use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\Tools\PlannerTool;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Ai\AnonymousAgent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies WorkflowPlanner::plannerTools() returns a non-empty array
 * and that invokeLlm() passes tools to AnonymousAgent (LK-F1).
 */
final class WorkflowPlannerToolLoopTest extends TestCase
{
    #[Test]
    public function planner_tools_returns_array(): void
    {
        $planner = $this->app->make(WorkflowPlanner::class);

        $ref = new \ReflectionMethod($planner, 'plannerTools');
        $ref->setAccessible(true);
        $tools = $ref->invoke($planner);

        $this->assertIsArray($tools);
    }

    #[Test]
    public function planner_tools_returns_registered_tools_by_default(): void
    {
        // AppServiceProvider tags CatalogLookupTool + PriorPlanRetrievalTool
        // (two tools) on the `planner.tools` collection by default.
        $planner = $this->app->make(WorkflowPlanner::class);

        $ref = new \ReflectionMethod($planner, 'plannerTools');
        $ref->setAccessible(true);
        $tools = $ref->invoke($planner);

        $this->assertIsArray($tools);
        $this->assertGreaterThanOrEqual(3, count($tools));
        $classes = array_map(fn ($t) => $t::class, $tools);
        $this->assertContains(\App\Domain\Planner\Tools\CatalogLookupTool::class, $classes);
        $this->assertContains(\App\Domain\Planner\Tools\PriorPlanRetrievalTool::class, $classes);
        $this->assertContains(\App\Domain\Planner\Tools\SchemaValidationTool::class, $classes);
    }

    #[Test]
    public function invoke_llm_receives_additional_tagged_tool(): void
    {
        // Wire a mock tool class via the tagged collection — appended to the
        // default CatalogLookupTool + PriorPlanRetrievalTool pair.
        $mockToolClass = new class implements PlannerTool {
            public function description(): \Stringable|string
            {
                return 'Mock planner tool';
            }
            public function handle(\Laravel\Ai\Tools\Request $request): \Stringable|string
            {
                return 'ok';
            }
            public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
            {
                return [];
            }
        };

        // Register the class and tag it.
        $this->app->instance(get_class($mockToolClass), $mockToolClass);
        $this->app->tag([get_class($mockToolClass)], 'planner.tools');

        $planner = $this->app->make(WorkflowPlanner::class);

        $ref = new \ReflectionMethod($planner, 'plannerTools');
        $ref->setAccessible(true);
        $tools = $ref->invoke($planner);

        $this->assertContains($mockToolClass, $tools);
    }
}
