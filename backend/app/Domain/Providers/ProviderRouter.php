<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\AnthropicAdapter;
use App\Domain\Providers\Adapters\DashScopeAdapter;
use App\Domain\Providers\Adapters\FalAdapter;
use App\Domain\Providers\Adapters\LoggingProviderDecorator;
use App\Domain\Providers\Adapters\OpenAiAdapter;
use App\Domain\Providers\Adapters\ReplicateAdapter;
use App\Domain\Providers\Adapters\StubAdapter;

class ProviderRouter
{
    public function __construct(
        private readonly bool $debug = false,
    ) {}

    public static function fromConfig(): self
    {
        return new self((bool) config('app.debug'));
    }

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
            'dashscope' => new DashScopeAdapter($apiKey, $model, $nodeConfig['region'] ?? 'intl'),
            'stub' => new StubAdapter(),
            default => throw new \InvalidArgumentException("Unknown provider driver: {$driver}"),
        };

        if ($this->debug) {
            $adapter = new LoggingProviderDecorator($adapter);
        }

        return $adapter;
    }
}
