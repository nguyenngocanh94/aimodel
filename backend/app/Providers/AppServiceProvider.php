<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
