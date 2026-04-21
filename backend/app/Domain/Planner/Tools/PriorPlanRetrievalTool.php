<?php

declare(strict_types=1);

namespace App\Domain\Planner\Tools;

use App\Models\PastPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

/**
 * Planner tool: retrieve up to 3 past workflow plans with briefs similar to the
 * current brief. Gives the model prior art to reference before emitting a fresh
 * plan.
 *
 * LK-F2 uses ILIKE on a truncated brief prefix. LK-G3 replaces the query with
 * `whereVectorSimilarTo('brief_embedding', ...)` backed by pgvector.
 */
final class PriorPlanRetrievalTool implements PlannerTool
{
    public function description(): string
    {
        return 'Retrieve up to 3 past workflow plans with briefs similar to the current brief. '
            . 'Use this to avoid re-inventing common patterns.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'brief' => $schema->string()
                ->description('Free-text brief describing the desired workflow outcome.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $brief = trim((string) $request->string('brief', ''));

        if ($brief === '') {
            return json_encode(
                ['priors' => [], 'note' => 'empty brief'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        }

        // LIKE on a bounded prefix keeps the query selective — full-brief LIKE
        // would either never match (too specific) or return everything.
        // Use LOWER() + LIKE for cross-database case-insensitivity (pgsql + sqlite).
        $needle = '%' . mb_strtolower(mb_substr($brief, 0, 80)) . '%';

        $rows = PastPlan::query()
            ->whereRaw('LOWER(brief) LIKE ?', [$needle])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'brief', 'plan', 'provider', 'model', 'created_at']);

        $priors = [];
        foreach ($rows as $row) {
            $priors[] = [
                'id' => (string) $row->id,
                'brief' => (string) $row->brief,
                'plan' => $row->plan,
                'provider' => $row->provider,
                'model' => $row->model,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ];
        }

        return json_encode(
            ['priors' => $priors],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
