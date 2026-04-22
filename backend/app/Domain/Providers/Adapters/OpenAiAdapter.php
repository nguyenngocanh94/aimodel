<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Http;

class OpenAiAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextGeneration => $this->textGeneration($input, $config),
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::TextToSpeech => $this->textToSpeech($input, $config),
            default => throw new \RuntimeException("OpenAI adapter does not support: {$capability->value}"),
        };
    }

    private function textGeneration(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'gpt-4o';
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $input['systemPrompt'] ?? ''],
                    ['role' => 'user', 'content' => $input['prompt'] ?? ''],
                ],
                'temperature' => $config['temperature'] ?? 0.7,
            ]);

        $response->throw();

        return $response->json('choices.0.message.content', '');
    }

    private function textToImage(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'dall-e-3';
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/images/generations', [
                'model' => $model,
                'prompt' => $input['prompt'] ?? '',
                'size' => $config['size'] ?? '1024x1024',
                'response_format' => 'b64_json',
                'n' => 1,
            ]);

        $response->throw();

        return base64_decode($response->json('data.0.b64_json', ''));
    }

    private function textToSpeech(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'tts-1';
        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => $model,
                'input' => $input['text'] ?? '',
                'voice' => $config['voice'] ?? 'alloy',
            ]);

        $response->throw();

        return $response->body();
    }
}
