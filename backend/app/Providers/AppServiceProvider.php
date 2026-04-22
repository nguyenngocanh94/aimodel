<?php

namespace App\Providers;

use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use App\Services\LocalArtifactStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArtifactStoreContract::class, LocalArtifactStore::class);

        $this->app->singleton(ProviderRouter::class, fn () => ProviderRouter::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
