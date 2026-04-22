<?php

declare(strict_types=1);

namespace App\Domain\Planner\Tools;

use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Models\Workflow;
use App\Services\AI\EmbeddingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Planner tool: search the workflow + node-template catalog for entries that
 * match a free-text query. Returns up to `limit` hits with {kind, id, name, why}.
 *
 * LK-F2 implementation uses ILIKE against `workflows.name` / `workflows.description`
 * for the workflow kind and substring scoring against NodeGuide.purpose /
 * whenToInclude for the node kind. LK-G3 replaces the workflow branch with
 * pgvector cosine search while keeping the ILIKE path as fallback.
 */
final class CatalogLookupTool implements PlannerTool
{
    public function __construct(
        private readonly NodeTemplateRegistry $registry,
        private readonly EmbeddingService $embedder,
    ) {}

    public function description(): string
    {
        return 'Search the workflow + node catalog for entries matching a free-text query. '
            . 'Returns up to 10 matches (kind, id/slug, name, why-it-matched). Use this before '
            . 'picking a node type or before composing a plan from scratch.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Free-text query — e.g. "generate product image" or "human approval".')
                ->required(),
            'kind' => $schema->string()
                ->description('Restrict the search. One of: workflow, node, any. Defaults to any.'),
            'limit' => $schema->integer()
                ->description('Max matches returned (default 10, capped at 20).'),
        ];
    }

    public function handle(Request $request): string
    {
        $query = trim((string) $request->string('query', ''));
        $kind = (string) $request->string('kind', 'any');
        $limit = (int) $request->integer('limit', 10);
        if ($limit <= 0) { $limit = 10; }
        if ($limit > 20) { $limit = 20; }

        if ($query === '') {
            return json_encode(
                ['matches' => [], 'note' => 'empty query'],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        }

        $matches = [];

        if ($kind === 'workflow' || $kind === 'any') {
            foreach ($this->workflowRows($query, $limit) as $row) {
                $matches[] = $row;
            }
        }

        if ($kind === 'node' || $kind === 'any') {
            foreach ($this->nodeRows($query, $limit) as $row) {
                $matches[] = $row;
            }
        }

        // Trim to the final cap, workflows first (most actionable).
        $matches = array_slice($matches, 0, $limit);

        return json_encode(
            ['matches' => $matches],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Workflow retrieval. Prefers pgvector cosine similarity via
     * {@see EmbeddingService}; falls back to case-insensitive LIKE on any
     * failure (missing API key, Voyage outage, non-pgsql driver).
     *
     * @return list<array{kind:string, id:string, name:string, why:string}>
     */
    protected function workflowRows(string $query, int $limit): array
    {
        $vectorRows = $this->workflowRowsByVector($query, $limit);
        if ($vectorRows !== null) {
            return $vectorRows;
        }

        return $this->workflowRowsByLike($query, $limit);
    }

    /**
     * @return list<array{kind:string, id:string, name:string, why:string}>|null
     *         null signals "fall back to ILIKE" — caller decides how to handle.
     */
    private function workflowRowsByVector(string $query, int $limit): ?array
    {
        // pgvector requires pgsql; sqlite tests always fall through.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            $vector = $this->embedder->tryEmbed($query);
            if ($vector === null) {
                return null;
            }

            $threshold = (float) config('planner.catalog_min_similarity', 0.6);

            $rows = Workflow::query()
                ->whereNotNull('catalog_embedding')
                ->whereVectorSimilarTo('catalog_embedding', $vector, $threshold)
                ->limit($limit)
                ->get(['id', 'name', 'description']);

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'kind' => 'workflow',
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'why' => 'matched via semantic similarity',
                ];
            }

            return $out;
        } catch (Throwable $e) {
            Log::warning('CatalogLookupTool: vector search failed, falling back to ILIKE', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return list<array{kind:string, id:string, name:string, why:string}>
     */
    private function workflowRowsByLike(string $query, int $limit): array
    {
        $like = '%' . mb_strtolower($query) . '%';

        // Use LOWER() + LIKE for cross-database case-insensitivity (pgsql + sqlite).
        // Postgres-specific ILIKE is avoided so unit tests against sqlite work.
        $rows = Workflow::query()
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'name', 'description']);

        $out = [];
        foreach ($rows as $row) {
            $why = $this->matchedField(
                $query,
                ['name' => (string) $row->name, 'description' => (string) ($row->description ?? '')],
            );
            $out[] = [
                'kind' => 'workflow',
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'why' => $why,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{kind:string, id:string, name:string, why:string}>
     */
    private function nodeRows(string $query, int $limit): array
    {
        $guides = array_values($this->registry->guides());
        $needle = mb_strtolower($query);

        /** @var list<array{score:int, row:array{kind:string,id:string,name:string,why:string}}> $scored */
        $scored = [];
        foreach ($guides as $guide) {
            /** @var NodeGuide $guide */
            $score = 0;
            $purpose = mb_strtolower($guide->purpose);
            $when = mb_strtolower($guide->whenToInclude);
            if (str_contains(mb_strtolower($guide->nodeId), $needle)) { $score += 3; }
            if (str_contains($purpose, $needle)) { $score += 2; }
            if (str_contains($when, $needle)) { $score += 1; }
            if ($score === 0) { continue; }

            $scored[] = [
                'score' => $score,
                'row' => [
                    'kind' => 'node',
                    'id' => $guide->nodeId,
                    'name' => $guide->nodeId,
                    'why' => $guide->purpose,
                ],
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($x) => $x['row'], array_slice($scored, 0, $limit));
    }

    /**
     * @param array<string,string> $fields
     */
    private function matchedField(string $query, array $fields): string
    {
        $needle = mb_strtolower($query);
        foreach ($fields as $label => $value) {
            if ($value !== '' && str_contains(mb_strtolower($value), $needle)) {
                return "matched {$label}";
            }
        }
        return 'matched (no field identified)';
    }
}
