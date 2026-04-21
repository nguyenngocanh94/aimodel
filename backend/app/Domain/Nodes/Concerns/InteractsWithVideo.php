<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use App\Services\MediaProviders\DashScopeClient;
use App\Services\MediaProviders\FalClient;
use App\Services\MediaProviders\ReplicateClient;

trait InteractsWithVideo
{
    protected function resolveVideoProvider(array $config): string
    {
        return (string) ($config['video']['provider'] ?? $config['provider'] ?? 'fal');
    }

    /** @param array<string> $referenceUrls */
    protected function callReferenceToVideo(
        NodeExecutionContext $ctx,
        string $prompt,
        array $referenceUrls = [],
        array $options = [],
    ): array {
        $provider = $this->resolveVideoProvider($ctx->config);
        $payload = $ctx->config + $options + ['reference_urls' => $referenceUrls];

        if ($provider === 'stub') {
            return ['video' => ['url' => 'https://example.com/fake.mp4', 'duration' => 5.0]];
        }

        return match ($provider) {
            'fal' => app(FalClient::class)->referenceToVideo($prompt, $payload),
            'dashscope' => app(DashScopeClient::class)->referenceToVideo($prompt, $payload),
            'replicate' => throw new \RuntimeException('replicate does not support referenceToVideo'),
            default => throw new \RuntimeException("unknown video provider: {$provider}"),
        };
    }

    protected function callMediaComposition(
        NodeExecutionContext $ctx,
        array $frames,
        mixed $audio = null,
        array $options = [],
    ): array {
        $provider = $this->resolveVideoProvider($ctx->config);
        $payload = $ctx->config + $options;

        if ($provider === 'stub') {
            return ['url' => 'https://example.com/fake-composition.mp4', 'duration' => 5.0];
        }

        return match ($provider) {
            'fal' => (array) app(FalClient::class)->mediaComposition($frames, $audio, $payload),
            'dashscope' => throw new \RuntimeException('dashscope does not support mediaComposition'),
            'replicate' => throw new \RuntimeException('replicate does not support mediaComposition'),
            default => throw new \RuntimeException("unknown video provider: {$provider}"),
        };
    }
}
