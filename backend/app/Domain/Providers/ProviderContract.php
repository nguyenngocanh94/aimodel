<?php

declare(strict_types=1);

namespace App\Domain\Providers;

use App\Domain\Capability;

interface ProviderContract
{
    public function execute(Capability $capability, array $input, array $config): mixed;
}
