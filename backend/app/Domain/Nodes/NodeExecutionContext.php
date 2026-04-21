<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use App\Domain\PortPayload;
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
        private ArtifactStoreContract $artifactStore,
        public array $humanProposalState = [],
    ) {}

    public function input(string $key): ?PortPayload
    {
        return $this->inputs[$key] ?? null;
    }

    public function inputValue(string $key): mixed
    {
        return $this->input($key)?->value;
    }

    public function storeArtifact(string $name, string $contents, string $mimeType): Artifact
    {
        return $this->artifactStore->put($this->runId, $this->nodeId, $name, $contents, $mimeType);
    }

    /**
     * Derive a new context with an overridden config, preserving wiring.
     *
     * @param array<string, mixed> $config
     */
    public function withConfig(array $config): self
    {
        return new self(
            nodeId: $this->nodeId,
            config: $config,
            inputs: $this->inputs,
            runId: $this->runId,
            artifactStore: $this->artifactStore,
            humanProposalState: $this->humanProposalState,
        );
    }
}
