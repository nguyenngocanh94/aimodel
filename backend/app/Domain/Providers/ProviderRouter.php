<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\StubAdapter;

class ProviderRouter
{
    public function resolve(Capability $capability, array $nodeConfig): ProviderContract
    {
        $driver = $nodeConfig['provider'] ?? 'stub';

        return match ($driver) {
            'stub' => new StubAdapter(),
            default => throw new \InvalidArgumentException("Unknown provider driver: {$driver}"),
        };
    }
}
