<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Http;

class AnthropicAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextGeneration => $this->textGeneration($input, $config),
            default => throw new \RuntimeException("Anthropic adapter does not support: {$capability->value}"),
        };
    }

    private function textGeneration(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'claude-sonnet-4-20250514';
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $config['maxTokens'] ?? 4096,
            'system' => $input['systemPrompt'] ?? '',
            'messages' => [
                ['role' => 'user', 'content' => $input['prompt'] ?? ''],
            ],
        ]);

        $response->throw();

        return $response->json('content.0.text', '');
    }
}
