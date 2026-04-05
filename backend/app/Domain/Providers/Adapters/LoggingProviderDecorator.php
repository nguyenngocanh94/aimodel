<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Log;

class LoggingProviderDecorator implements ProviderContract
{
    public function __construct(
        private ProviderContract $inner,
        private string $driverName,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        $startTime = hrtime(true);
        $redactedConfig = $this->redactConfig($config);

        Log::channel('providers')->info('Provider call started', [
            'driver' => $this->driverName,
            'capability' => $capability->value,
            'inputKeys' => array_keys($input),
            'config' => $redactedConfig,
        ]);

        try {
            $result = $this->inner->execute($capability, $input, $config);

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            Log::channel('providers')->info('Provider call succeeded', [
                'driver' => $this->driverName,
                'capability' => $capability->value,
                'durationMs' => $durationMs,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            Log::channel('providers')->error('Provider call failed', [
                'driver' => $this->driverName,
                'capability' => $capability->value,
                'durationMs' => $durationMs,
                'error' => $e->getMessage(),
                'errorClass' => get_class($e),
            ]);

            throw $e;
        }
    }

    private function redactConfig(array $config): array
    {
        $redacted = $config;
        foreach (['apiKey', 'api_key', 'secret', 'token'] as $key) {
            if (isset($redacted[$key])) {
                $redacted[$key] = '***REDACTED***';
            }
        }
        return $redacted;
    }
}
