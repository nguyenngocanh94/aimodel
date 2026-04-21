<?php

declare(strict_types=1);

namespace App\Services\MediaProviders;

use Illuminate\Support\Facades\Http;

final readonly class ReplicateClient
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function textToImage(string $prompt, array $options = []): string
    {
        $model = $this->model ?? ($options['model'] ?? 'stability-ai/sdxl');
        $prediction = Http::withToken($this->apiKey)
            ->post('https://api.replicate.com/v1/predictions', [
                'version' => $model,
                'input' => [
                    'prompt' => $prompt,
                    'width' => $options['width'] ?? 1024,
                    'height' => $options['height'] ?? 1024,
                ],
            ]);

        $prediction->throw();

        $getUrl = (string) $prediction->json('urls.get');
        $result = $this->pollForCompletion($getUrl);
        $imageUrl = (string) ($result['output'][0] ?? '');

        return Http::get($imageUrl)->body();
    }

    /** @return array<string, mixed> */
    private function pollForCompletion(string $url, int $maxAttempts = 60): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withToken($this->apiKey)->get($url);
            $response->throw();
            $data = (array) $response->json();
            $status = (string) ($data['status'] ?? '');

            if (in_array($status, ['succeeded', 'failed', 'canceled'], true)) {
                if ($status !== 'succeeded') {
                    throw new \RuntimeException("Replicate prediction {$status}: " . ($data['error'] ?? 'unknown'));
                }

                return $data;
            }

            usleep(1_000_000);
        }

        throw new \RuntimeException('Replicate prediction timed out');
    }
}
