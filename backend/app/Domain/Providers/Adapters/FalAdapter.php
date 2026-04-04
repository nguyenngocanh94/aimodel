<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Http;

class FalAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::MediaComposition => $this->mediaComposition($input, $config),
            default => throw new \RuntimeException("Fal adapter does not support: {$capability->value}"),
        };
    }

    private function textToImage(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'fal-ai/flux/dev';
        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'prompt' => $input['prompt'] ?? '',
            'image_size' => $config['imageSize'] ?? 'landscape_16_9',
            'num_images' => 1,
        ]);

        $response->throw();

        $imageUrl = $response->json('images.0.url', '');

        return Http::get($imageUrl)->body();
    }

    private function mediaComposition(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'fal-ai/video-composer';
        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'scenes' => $input['scenes'] ?? [],
            'audio' => $input['audio'] ?? null,
            'subtitles' => $input['subtitles'] ?? null,
        ]);

        $response->throw();

        $videoUrl = $response->json('video.url', '');

        return Http::get($videoUrl)->body();
    }
}
