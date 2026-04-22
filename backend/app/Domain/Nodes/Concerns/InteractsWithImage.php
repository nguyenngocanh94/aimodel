<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use App\Services\MediaProviders\DashScopeClient;
use App\Services\MediaProviders\FalClient;
use App\Services\MediaProviders\ReplicateClient;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;

trait InteractsWithImage
{
    /** @return array<string, array<int, string>> */
    protected function imageConfigRules(): array
    {
        return [
            'image' => ['sometimes', 'array'],
            'image.provider' => ['sometimes', 'string'],
            'image.model' => ['sometimes', 'string'],
            'image.size' => ['sometimes', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    protected function imageDefaultConfig(): array
    {
        return ['image' => ['provider' => '', 'model' => '', 'size' => '']];
    }

    protected function resolveImageProvider(array $config): string
    {
        $nested = (string) ($config['image']['provider'] ?? '');
        if ($nested !== '') {
            return $nested;
        }

        $legacy = (string) ($config['provider'] ?? '');
        if ($legacy !== '') {
            return $legacy;
        }

        return (string) config('ai.default_for_images', 'gemini');
    }

    protected function callImageGeneration(NodeExecutionContext $ctx, string $prompt, array $options = []): string
    {
        $provider = $this->resolveImageProvider($ctx->config);
        $model = $ctx->config['image']['model'] ?? null;

        if ($provider === 'stub') {
            return 'fake-image-bytes';
        }

        if (in_array($provider, ['openai', 'gemini', 'xai'], true)) {
            $response = Ai::provider($provider)->image($prompt, model: $model);
            return (string) $response->firstImage();
        }

        return match ($provider) {
            'fal' => app(FalClient::class)->textToImage($prompt, $ctx->config + $options),
            'replicate' => app(ReplicateClient::class)->textToImage($prompt, $ctx->config + $options),
            'dashscope' => $this->downloadImageFromUrl(
                (string) app(DashScopeClient::class)->textToImage($prompt, $ctx->config + $options)['url']
            ),
            default => throw new \RuntimeException("unknown image provider: {$provider}"),
        };
    }

    private function downloadImageFromUrl(string $url): string
    {
        if ($url === '') {
            Log::warning('InteractsWithImage: empty URL returned from provider');
            return '';
        }

        return \Illuminate\Support\Facades\Http::get($url)->body();
    }
}
