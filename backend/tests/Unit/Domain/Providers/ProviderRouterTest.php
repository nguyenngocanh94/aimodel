<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\AnthropicAdapter;
use App\Domain\Providers\Adapters\DashScopeAdapter;
use App\Domain\Providers\Adapters\FalAdapter;
use App\Domain\Providers\Adapters\OpenAiAdapter;
use App\Domain\Providers\Adapters\ReplicateAdapter;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use PHPUnit\Framework\TestCase;

class ProviderRouterTest extends TestCase
{
    private ProviderRouter $router;

    protected function setUp(): void
    {
        $this->router = new ProviderRouter();
    }

    public function test_resolves_stub_adapter_by_default(): void
    {
        $adapter = $this->router->resolve(Capability::TextGeneration, []);
        $this->assertInstanceOf(StubAdapter::class, $adapter);
    }

    public function test_resolves_stub_adapter_explicitly(): void
    {
        $adapter = $this->router->resolve(Capability::TextGeneration, ['provider' => 'stub']);
        $this->assertInstanceOf(StubAdapter::class, $adapter);
    }

    public function test_resolves_openai_adapter(): void
    {
        $adapter = $this->router->resolve(Capability::TextGeneration, [
            'provider' => 'openai',
            'apiKey' => 'sk-test',
            'model' => 'gpt-4o',
        ]);
        $this->assertInstanceOf(OpenAiAdapter::class, $adapter);
    }

    public function test_resolves_anthropic_adapter(): void
    {
        $adapter = $this->router->resolve(Capability::TextGeneration, [
            'provider' => 'anthropic',
            'apiKey' => 'sk-ant-test',
        ]);
        $this->assertInstanceOf(AnthropicAdapter::class, $adapter);
    }

    public function test_resolves_replicate_adapter(): void
    {
        $adapter = $this->router->resolve(Capability::TextToImage, [
            'provider' => 'replicate',
            'apiKey' => 'r8-test',
        ]);
        $this->assertInstanceOf(ReplicateAdapter::class, $adapter);
    }

    public function test_resolves_fal_adapter(): void
    {
        $adapter = $this->router->resolve(Capability::TextToImage, [
            'provider' => 'fal',
            'apiKey' => 'fal-test',
        ]);
        $this->assertInstanceOf(FalAdapter::class, $adapter);
    }

    public function test_resolves_dashscope_adapter(): void
    {
        $adapter = $this->router->resolve(Capability::ReferenceToVideo, [
            'provider' => 'dashscope',
            'apiKey' => 'dk-test',
        ]);
        $this->assertInstanceOf(DashScopeAdapter::class, $adapter);
    }

    public function test_resolves_dashscope_adapter_with_region(): void
    {
        $adapter = $this->router->resolve(Capability::ReferenceToVideo, [
            'provider' => 'dashscope',
            'apiKey' => 'dk-test',
            'region' => 'cn',
        ]);
        $this->assertInstanceOf(DashScopeAdapter::class, $adapter);
    }

    public function test_throws_on_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider driver: nonexistent');

        $this->router->resolve(Capability::TextGeneration, ['provider' => 'nonexistent']);
    }
}
