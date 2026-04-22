<?php

declare(strict_types=1);

namespace App\Domain\Planner\Tools;

use Laravel\Ai\Contracts\Tool;

/**
 * Marker interface grouping planner-side tools for tagged collection registration.
 *
 * Tools implementing this interface are auto-registered in the "planner.tools"
 * tag via AppServiceProvider and returned by WorkflowPlanner::plannerTools().
 */
interface PlannerTool extends Tool
{
}
