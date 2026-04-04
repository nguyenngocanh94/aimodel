<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Support\ServiceProvider;

class NodeTemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NodeTemplateRegistry::class);
    }

    public function boot(): void
    {
        // Templates are registered here by AiModel-557 and AiModel-593 beads.
    }
}
