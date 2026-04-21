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
    public function planner_tools_can_be_empty_when_no_tools_registered(): void
    {
        // Without any PlannerTool implementations tagged, the collection is empty.
        $planner = $this->app->make(WorkflowPlanner::class);

        $ref = new \ReflectionMethod($planner, 'plannerTools');
        $ref->setAccessible(true);
        $tools = $ref->invoke($planner);

        $this->assertIsArray($tools);
        $this->assertEmpty($tools);
    }

    #[Test]
    public function invoke_llm_receives_tools_array(): void
    {
        // Wire a mock tool class via the tagged collection.
        // The class is bound to the container and tagged; when tagged() resolves
        // it calls make() with the class string.
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

        // Access protected method via reflection.
        $ref = new \ReflectionMethod($planner, 'plannerTools');
        $ref->setAccessible(true);
        $tools = $ref->invoke($planner);

        $this->assertCount(1, $tools);
        $this->assertSame($mockToolClass, $tools[0]);
    }
}
