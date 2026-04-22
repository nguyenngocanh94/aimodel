<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Log;

final class LoggingProviderDecorator implements ProviderContract
{
    public function __construct(
        private readonly ProviderContract $inner,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        $channel = Log::channel('providers');

        $channel->info('Provider call started', [
            'capability' => $capability->value,
            'input_keys' => array_keys($input),
            'config' => $this->redactConfig($config),
            'adapter' => $this->inner::class,
        ]);

        $start = hrtime(true);

        try {
            $result = $this->inner->execute($capability, $input, $config);
        } catch (\Throwable $e) {
            $durationMs = (hrtime(true) - $start) / 1_000_000;

            $channel->error('Provider call failed', [
                'capability' => $capability->value,
                'adapter' => $this->inner::class,
                'duration_ms' => round($durationMs, 2),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $durationMs = (hrtime(true) - $start) / 1_000_000;

        $channel->info('Provider call succeeded', [
            'capability' => $capability->value,
            'adapter' => $this->inner::class,
            'duration_ms' => round($durationMs, 2),
        ]);

        return $result;
    }

    private function redactConfig(array $config): array
    {
        $redacted = $config;

        if (array_key_exists('apiKey', $redacted)) {
            $redacted['apiKey'] = '***REDACTED***';
        }

        return $redacted;
    }
}
