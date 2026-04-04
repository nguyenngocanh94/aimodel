<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use App\Domain\Capability;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderContract;
use App\Domain\Providers\ProviderRouter;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;

readonly class NodeExecutionContext
{
    /**
     * @param array<string, PortPayload> $inputs
     */
    public function __construct(
        public string $nodeId,
        public array $config,
        public array $inputs,
        public string $runId,
        private ProviderRouter $providerRouter,
        private ArtifactStoreContract $artifactStore,
    ) {}

    public function input(string $key): ?PortPayload
    {
        return $this->inputs[$key] ?? null;
    }

    public function inputValue(string $key): mixed
    {
        return $this->input($key)?->value;
    }

    public function provider(Capability $capability): ProviderContract
    {
        return $this->providerRouter->resolve($capability, $this->config);
    }

    public function storeArtifact(string $name, string $contents, string $mimeType): Artifact
    {
        return $this->artifactStore->put($this->runId, $this->nodeId, $name, $contents, $mimeType);
    }
}
