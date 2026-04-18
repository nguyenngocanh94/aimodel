<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\OpenAI\OpenAI;

/**
 * Registers custom Prism providers not built into the library.
 *
 * Fireworks is OpenAI-compatible: we reuse Prism's OpenAI driver pointed at
 * https://api.fireworks.ai/inference/v1.  The driver key is 'openai' internally
 * but the provider name we register here is 'fireworks', so callers do:
 *
 *   Prism::text()->using('fireworks', $model)->…
 */
class PrismServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var PrismManager $manager */
        $manager = $this->app->make(PrismManager::class);

        $manager->extend('fireworks', function ($app, array $config): OpenAI {
            return new OpenAI(
                apiKey: $config['api_key'] ?? '',
                url: $config['url'] ?? 'https://api.fireworks.ai/inference/v1',
                organization: $config['organization'] ?? null,
                project: $config['project'] ?? null,
            );
        });
    }
}
