<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Http;

class ReplicateAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::TextGeneration => $this->textGeneration($input, $config),
            default => throw new \RuntimeException("Replicate adapter does not support: {$capability->value}"),
        };
    }

    private function textToImage(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'stability-ai/sdxl';
        $prediction = Http::withToken($this->apiKey)
            ->post('https://api.replicate.com/v1/predictions', [
                'version' => $model,
                'input' => [
                    'prompt' => $input['prompt'] ?? '',
                    'width' => $config['width'] ?? 1024,
                    'height' => $config['height'] ?? 1024,
                ],
            ]);

        $prediction->throw();

        $getUrl = $prediction->json('urls.get');
        $result = $this->pollForCompletion($getUrl);

        $imageUrl = $result['output'][0] ?? '';

        return Http::get($imageUrl)->body();
    }

    private function textGeneration(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'meta/llama-2-70b-chat';
        $prediction = Http::withToken($this->apiKey)
            ->post('https://api.replicate.com/v1/predictions', [
                'version' => $model,
                'input' => [
                    'prompt' => $input['prompt'] ?? '',
                    'system_prompt' => $input['systemPrompt'] ?? '',
                    'temperature' => $config['temperature'] ?? 0.7,
                ],
            ]);

        $prediction->throw();

        $getUrl = $prediction->json('urls.get');
        $result = $this->pollForCompletion($getUrl);

        return implode('', $result['output'] ?? []);
    }

    private function pollForCompletion(string $url, int $maxAttempts = 60): array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = Http::withToken($this->apiKey)->get($url);
            $response->throw();
            $data = $response->json();

            if (in_array($data['status'], ['succeeded', 'failed', 'canceled'], true)) {
                if ($data['status'] !== 'succeeded') {
                    throw new \RuntimeException("Replicate prediction {$data['status']}: " . ($data['error'] ?? 'unknown'));
                }
                return $data;
            }

            usleep(1_000_000);
        }

        throw new \RuntimeException('Replicate prediction timed out');
    }
}
