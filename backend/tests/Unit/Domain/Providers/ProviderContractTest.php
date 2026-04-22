<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use App\Domain\Providers\ProviderRouter;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderContractTest extends TestCase
{
    #[Test]
    public function provider_contract_can_be_implemented_by_anonymous_class(): void
    {
        $provider = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                return ['result' => 'test'];
            }
        };

        $result = $provider->execute(Capability::TextGeneration, ['prompt' => 'hello'], []);

        $this->assertSame(['result' => 'test'], $result);
    }

    #[Test]
    public function provider_router_throws_for_unknown_driver(): void
    {
        $router = new ProviderRouter();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown provider driver: nonexistent');

        $router->resolve(Capability::TextGeneration, ['provider' => 'nonexistent']);
    }

    #[Test]
    public function provider_router_resolves_default_stub_driver(): void
    {
        $router = new ProviderRouter();

        $provider = $router->resolve(Capability::TextGeneration, []);

        $this->assertInstanceOf(ProviderContract::class, $provider);
    }

    #[Test]
    public function artifact_store_contract_declares_all_required_methods(): void
    {
        $reflection = new \ReflectionClass(ArtifactStoreContract::class);
        $methods = array_map(fn (\ReflectionMethod $m) => $m->getName(), $reflection->getMethods());

        $this->assertContains('put', $methods);
        $this->assertContains('url', $methods);
        $this->assertContains('get', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('deleteForRun', $methods);
        $this->assertCount(5, $methods);
    }

    #[Test]
    public function artifact_store_put_method_has_correct_signature(): void
    {
        $reflection = new \ReflectionClass(ArtifactStoreContract::class);
        $put = $reflection->getMethod('put');
        $params = $put->getParameters();

        $this->assertCount(5, $params);
        $this->assertSame('runId', $params[0]->getName());
        $this->assertSame('nodeId', $params[1]->getName());
        $this->assertSame('name', $params[2]->getName());
        $this->assertSame('contents', $params[3]->getName());
        $this->assertSame('mimeType', $params[4]->getName());

        $returnType = $put->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame(Artifact::class, $returnType->getName());
    }
}
