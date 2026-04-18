<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prism;

use Tests\TestCase;

/**
 * Smoke-tests for config/prism.php wiring.
 *
 * Verifies that both providers are present with the expected structure and
 * that the default provider env-default resolves to 'fireworks'.
 *
 * Key mapping (for PT2/PT3 reference):
 *   config('prism.default_provider')            → the active provider name
 *   config('prism.providers.anthropic')          → anthropic config array
 *   config('prism.providers.anthropic.api_key')  → ANTHROPIC_API_KEY
 *   config('prism.providers.anthropic.default_model') → ANTHROPIC_DEFAULT_MODEL
 *   config('prism.providers.fireworks')          → fireworks config array
 *   config('prism.providers.fireworks.url')      → Fireworks base URL
 *   config('prism.providers.fireworks.api_key')  → FIREWORKS_API_KEY
 *   config('prism.providers.fireworks.default_model') → FIREWORKS_MODEL
 */
class PrismConfigSmokeTest extends TestCase
{
    public function test_anthropic_provider_config_exists_with_api_key(): void
    {
        $config = config('prism.providers.anthropic');

        $this->assertIsArray($config, 'prism.providers.anthropic must be an array');
        $this->assertArrayHasKey('api_key', $config, 'anthropic config must have api_key');
        $this->assertArrayHasKey('default_model', $config, 'anthropic config must have default_model');
        $this->assertSame('claude-sonnet-4-6', $config['default_model']);
    }

    public function test_fireworks_provider_config_exists_and_url_contains_fireworks_ai(): void
    {
        $config = config('prism.providers.fireworks');

        $this->assertIsArray($config, 'prism.providers.fireworks must be an array');
        $this->assertArrayHasKey('url', $config, 'fireworks config must have url');
        $this->assertStringContainsString(
            'fireworks.ai',
            (string) $config['url'],
            'fireworks url must contain fireworks.ai'
        );
        $this->assertArrayHasKey('api_key', $config, 'fireworks config must have api_key');
    }

    public function test_default_provider_is_fireworks_when_env_not_overridden(): void
    {
        // The test environment does not set PRISM_PROVIDER, so the default
        // ('fireworks') from config/prism.php must be in effect.
        $default = config('prism.default_provider');

        $this->assertSame('fireworks', $default);
    }

    public function test_fireworks_custom_creator_resolves_to_openai_driver(): void
    {
        // Resolve the 'fireworks' provider through PrismManager — confirms our
        // PrismServiceProvider::extend() registration is working.
        $manager = app(\Prism\Prism\PrismManager::class);
        $provider = $manager->resolve('fireworks');

        $this->assertInstanceOf(
            \Prism\Prism\Providers\OpenAI\OpenAI::class,
            $provider,
            'fireworks provider must resolve to the OpenAI driver'
        );
    }
}
