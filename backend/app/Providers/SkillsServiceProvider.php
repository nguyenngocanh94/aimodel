<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\SkillsListCommand;
use App\Console\Commands\SkillsMakeCommand;
use App\Services\Skills\SkillRegistry;
use Illuminate\Support\ServiceProvider;

class SkillsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SkillRegistry::class, fn () => new SkillRegistry());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SkillsListCommand::class,
                SkillsMakeCommand::class,
            ]);
        }
    }
}
