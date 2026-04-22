<?php

declare(strict_types=1);

namespace App\Domain\Planner\Tools;

use App\Models\PastPlan;
use App\Services\AI\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Throwable;

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
    public function __construct(
        private readonly EmbeddingService $embedder,
    ) {}

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

        $rows = $this->byVector($brief) ?? $this->byLike($brief);

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

    /**
     * Vector-path retrieval; returns null on unrecoverable failure so the
     * caller drops to ILIKE.
     *
     * @return \Illuminate\Support\Collection<int, PastPlan>|null
     */
    private function byVector(string $brief)
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $vector = $this->embedder->tryEmbed($brief);
            if ($vector === null) {
                return null;
            }

            $threshold = (float) config('planner.priors_min_similarity', 0.65);

            return PastPlan::query()
                ->whereNotNull('brief_embedding')
                ->whereVectorSimilarTo('brief_embedding', $vector, $threshold)
                ->limit(3)
                ->get(['id', 'brief', 'plan', 'provider', 'model', 'created_at']);
        } catch (Throwable $e) {
            Log::warning('PriorPlanRetrievalTool: vector search failed, falling back to ILIKE', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, PastPlan>
     */
    private function byLike(string $brief)
    {
        $needle = '%' . mb_strtolower(mb_substr($brief, 0, 80)) . '%';

        return PastPlan::query()
            ->whereRaw('LOWER(brief) LIKE ?', [$needle])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'brief', 'plan', 'provider', 'model', 'created_at']);
    }
}
