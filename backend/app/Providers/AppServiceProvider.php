<?php

namespace App\Providers;

use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\Evaluation\CharacteristicExtractor;
use App\Domain\Planner\Evaluation\Scorer;
use App\Domain\Planner\Evaluation\WorkflowPlanEvaluator;
use App\Services\MediaProviders\DashScopeClient;
use App\Services\MediaProviders\FalClient;
use App\Services\MediaProviders\ReplicateClient;
use App\Services\ArtifactStoreContract;
use App\Services\LocalArtifactStore;
use App\Services\Memory\DatabaseRunMemoryStore;
use App\Services\Memory\RunMemoryStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ArtifactStoreContract::class, LocalArtifactStore::class);
        $this->app->singleton(RunMemoryStore::class, DatabaseRunMemoryStore::class);

        $this->app->bind(FalClient::class, fn () => new FalClient((string) env('FAL_KEY', '')));
        $this->app->bind(ReplicateClient::class, fn () => new ReplicateClient((string) env('REPLICATE_API_TOKEN', '')));
        $this->app->bind(DashScopeClient::class, fn () => new DashScopeClient((string) env('DASHSCOPE_API_KEY', '')));

        $this->app->singleton(WorkflowPlanEvaluator::class, function ($app): WorkflowPlanEvaluator {
            /** @var list<class-string<Scorer>> $scorerClasses */
            $scorerClasses = (array) config('planner.evaluation.scorers', []);
            $scorers = [];
            foreach ($scorerClasses as $class) {
                $instance = $app->make($class);
                if (!$instance instanceof Scorer) {
                    throw new \InvalidArgumentException(
                        "planner.evaluation.scorers entry '{$class}' must implement " . Scorer::class
                    );
                }
                $scorers[] = $instance;
            }

            return new WorkflowPlanEvaluator(
                scorers: $scorers,
                extractor: $app->make(CharacteristicExtractor::class),
                registry: $app->make(NodeTemplateRegistry::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
