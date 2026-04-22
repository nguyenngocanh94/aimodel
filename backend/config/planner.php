<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Agentic mode
    |--------------------------------------------------------------------------
    |
    | When true (default), the WorkflowPlanner exposes tools (CatalogLookup,
    | PriorPlanRetrieval, SchemaValidation) to the underlying agent. Flip off
    | via env to fall back to single-shot JSON output without tool calls.
    |
    */

    'agentic' => env('PLANNER_AGENTIC', true),

    /*
    |--------------------------------------------------------------------------
    | Persist successful plans
    |--------------------------------------------------------------------------
    |
    | When true, WorkflowPlanner::plan() writes successful outputs to the
    | workflow_plans table so PriorPlanRetrievalTool has data to return.
    |
    */

    'persist_plans' => env('PLANNER_PERSIST_PLANS', true),

    /*
    |--------------------------------------------------------------------------
    | Catalog lookup thresholds
    |--------------------------------------------------------------------------
    |
    | min_similarity drives the vector search cutoff once LK-G3 lands. Until
    | then CatalogLookupTool uses ILIKE and ignores this value.
    |
    */

    'catalog_min_similarity' => env('PLANNER_CATALOG_MIN_SIMILARITY', 0.6),
    'priors_min_similarity' => env('PLANNER_PRIORS_MIN_SIMILARITY', 0.65),

];
