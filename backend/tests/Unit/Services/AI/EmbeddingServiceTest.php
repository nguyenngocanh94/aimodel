<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI;

use App\Services\AI\EmbeddingService;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EmbeddingServiceTest extends TestCase
{
    #[Test]
    public function empty_input_returns_empty_array(): void
    {
        Embeddings::fake();
        $svc = new EmbeddingService();

        $this->assertSame([], $svc->embedMany([]));
    }

    #[Test]
    public function embed_returns_single_vector(): void
    {
        $vec = Embeddings::fakeEmbedding(1024);
        Embeddings::fake([
            [$vec],
        ]);

        $svc = new EmbeddingService();
        $out = $svc->embed('hello world');

        $this->assertCount(1024, $out);
        $this->assertSame($vec, $out);
    }

    #[Test]
    public function embed_many_preserves_order_for_multiple_batches(): void
    {
        // 20 inputs → 2 batches (16 + 4). Provide 2 fake responses.
        $batch1 = array_map(fn () => Embeddings::fakeEmbedding(1024), range(1, 16));
        $batch2 = array_map(fn () => Embeddings::fakeEmbedding(1024), range(1, 4));
        Embeddings::fake([
            $batch1,
            $batch2,
        ]);

        $svc = new EmbeddingService();
        $inputs = array_map(fn ($i) => "text-{$i}", range(1, 20));

        $out = $svc->embedMany($inputs);

        $this->assertCount(20, $out);
        foreach ($out as $vec) {
            $this->assertCount(1024, $vec);
        }
    }

    #[Test]
    public function try_embed_returns_null_when_provider_throws(): void
    {
        // preventStrayEmbeddings + empty fake = RuntimeException on call.
        Embeddings::fake([])->preventStrayEmbeddings();

        $svc = new EmbeddingService();
        $out = $svc->tryEmbed('boom');

        $this->assertNull($out);
    }

    #[Test]
    public function try_embed_returns_vector_on_success(): void
    {
        $vec = Embeddings::fakeEmbedding(1024);
        Embeddings::fake([
            [$vec],
        ]);

        $svc = new EmbeddingService();
        $out = $svc->tryEmbed('ok');

        $this->assertSame($vec, $out);
    }
}
