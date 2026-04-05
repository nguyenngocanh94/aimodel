<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\AnthropicAdapter;
use App\Domain\Providers\Adapters\FalAdapter;
use App\Domain\Providers\Adapters\LoggingProviderDecorator;
use App\Domain\Providers\Adapters\OpenAiAdapter;
use App\Domain\Providers\Adapters\ReplicateAdapter;
use App\Domain\Providers\Adapters\StubAdapter;

class ProviderRouter
{
    public function resolve(Capability $capability, array $nodeConfig): ProviderContract
    {
        $driver = $nodeConfig['provider'] ?? 'stub';
        $apiKey = $nodeConfig['apiKey'] ?? '';
        $model = $nodeConfig['model'] ?? null;

        $adapter = match ($driver) {
            'openai' => new OpenAiAdapter($apiKey, $model),
            'anthropic' => new AnthropicAdapter($apiKey, $model),
            'replicate' => new ReplicateAdapter($apiKey, $model),
            'fal' => new FalAdapter($apiKey, $model),
            'stub' => new StubAdapter(),
            default => throw new \InvalidArgumentException("Unknown provider driver: {$driver}"),
        };

        if (config('app.debug')) {
            $adapter = new LoggingProviderDecorator($adapter);
        }

        return $adapter;
    }
}
