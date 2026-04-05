<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use RuntimeException;
use Throwable;

class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly string $capability,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function toArray(): array
    {
        return [
            'error' => [
                'code' => 'provider_error',
                'message' => $this->getMessage(),
                'provider' => $this->provider,
                'capability' => $this->capability,
                'retryable' => $this->retryable,
                'original' => $this->getPrevious()?->getMessage(),
            ],
        ];
    }
}
