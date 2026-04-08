<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\Templates\FinalExportTemplate;
use App\Domain\Nodes\Templates\HumanGateTemplate;
use App\Domain\Nodes\Templates\ImageAssetMapperTemplate;
use App\Domain\Nodes\Templates\ImageGeneratorTemplate;
use App\Domain\Nodes\Templates\PromptRefinerTemplate;
use App\Domain\Nodes\Templates\ReviewCheckpointTemplate;
use App\Domain\Nodes\Templates\SceneSplitterTemplate;
use App\Domain\Nodes\Templates\ScriptWriterTemplate;
use App\Domain\Nodes\Templates\SubtitleFormatterTemplate;
use App\Domain\Nodes\Templates\TtsVoiceoverPlannerTemplate;
use App\Domain\Nodes\Templates\UserPromptTemplate;
use App\Domain\Nodes\Templates\VideoComposerTemplate;
use App\Domain\Nodes\Templates\TrendResearcherTemplate;
use App\Domain\Nodes\Templates\ProductAnalyzerTemplate;
use App\Domain\Nodes\Templates\WanR2VTemplate;
use Illuminate\Support\ServiceProvider;

class NodeTemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NodeTemplateRegistry::class);
    }

    public function boot(): void
    {
        /** @var NodeTemplateRegistry $registry */
        $registry = $this->app->make(NodeTemplateRegistry::class);

        $registry->register(new UserPromptTemplate());
        $registry->register(new ScriptWriterTemplate());
        $registry->register(new SceneSplitterTemplate());
        $registry->register(new PromptRefinerTemplate());
        $registry->register(new ImageGeneratorTemplate());
        $registry->register(new ReviewCheckpointTemplate());
        $registry->register(new HumanGateTemplate());
        $registry->register(new ImageAssetMapperTemplate());
        $registry->register(new TtsVoiceoverPlannerTemplate());
        $registry->register(new SubtitleFormatterTemplate());
        $registry->register(new VideoComposerTemplate());
        $registry->register(new FinalExportTemplate());
        $registry->register(new WanR2VTemplate());
        $registry->register(new TrendResearcherTemplate());
        $registry->register(new ProductAnalyzerTemplate());
    }
}
