<?php

declare(strict_types=1);

namespace App\Services\AI;

use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Thin wrapper around {@see Embeddings} that batches inputs and exposes a
 * single `embedMany`/`embed` API keyed on the VoyageAI (voyage-4, 1024 dim)
 * provider configured in config/ai.php.
 *
 * LK-G2 — see docs/plans/2026-04-19-laravel-ai-capabilities.md.
 */
final class EmbeddingService
{
    /**
     * Max texts submitted per HTTP round-trip. VoyageAI caps at 128 per call
     * but 16 keeps tail latency predictable and makes a failed batch cheap to
     * retry.
     */
    private const BATCH_SIZE = 16;

    public function __construct(
        private readonly string $provider = 'voyageai',
    ) {}

    /**
     * Embed a single string. Returns a 1024-float vector on success.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $vectors = $this->embedMany([$text]);
        return $vectors[0] ?? [];
    }

    /**
     * Embed many strings. Preserves input order. Returns one vector per input.
     *
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $response = Embeddings::for(array_values($batch))
                ->generate(provider: $this->provider);

            foreach ($response->embeddings as $vec) {
                $out[] = $vec;
            }
        }

        return $out;
    }

    /**
     * Best-effort embed — returns null on failure (missing API key, network,
     * provider outage). Callers that want to fall back to ILIKE search use
     * this variant.
     *
     * @return array<int, float>|null
     */
    public function tryEmbed(string $text): ?array
    {
        try {
            $vec = $this->embed($text);
            return $vec === [] ? null : $vec;
        } catch (Throwable) {
            return null;
        }
    }
}
