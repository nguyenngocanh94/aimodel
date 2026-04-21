<?php

declare(strict_types=1);

namespace App\Services\MediaProviders;

use Illuminate\Support\Facades\Http;

final readonly class FalClient
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function textToImage(string $prompt, array $options = []): string
    {
        $model = $this->model ?? ($options['model'] ?? 'fal-ai/flux/dev');

        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'prompt' => $prompt,
            'image_size' => $options['imageSize'] ?? 'landscape_16_9',
            'num_images' => 1,
        ]);

        $response->throw();

        $imageUrl = (string) $response->json('images.0.url', '');

        return Http::get($imageUrl)->body();
    }

    /** @return array<string, mixed> */
    public function referenceToVideo(string $prompt, array $options = []): array
    {
        if ($prompt === '') {
            throw new \InvalidArgumentException('ReferenceToVideo requires a prompt');
        }

        $model = $this->model ?? ($options['model'] ?? 'fal-ai/wan/v2.7/reference-to-video');

        $payload = [
            'prompt' => $prompt,
            'aspect_ratio' => $options['aspectRatio'] ?? '9:16',
            'resolution' => $options['resolution'] ?? '1080p',
            'duration' => $options['duration'] ?? '5',
            'multi_shots' => $options['multiShots'] ?? false,
        ];

        if (!empty($options['reference_urls'])) {
            $payload['reference_video_urls'] = $options['reference_urls'];
        }

        if (!empty($options['negative_prompt'])) {
            $payload['negative_prompt'] = $options['negative_prompt'];
        }

        if (isset($options['seed'])) {
            $payload['seed'] = (int) $options['seed'];
        }

        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->timeout(300)->post("https://fal.run/{$model}", $payload);

        $response->throw();

        return (array) $response->json();
    }

    /** @return array<string, mixed> */
    public function mediaComposition(array $frames, mixed $audio = null, array $options = []): array
    {
        $model = $this->model ?? ($options['model'] ?? 'fal-ai/video-composer');

        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'scenes' => $frames,
            'audio' => $audio,
            'subtitles' => $options['subtitles'] ?? null,
        ]);

        $response->throw();

        return (array) $response->json();
    }
}
