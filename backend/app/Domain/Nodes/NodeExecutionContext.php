<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use App\Domain\PortPayload;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use App\Services\Memory\RunMemoryStore;
use Closure;
use DateTimeInterface;

readonly class NodeExecutionContext
{
    /**
     * @param array<string, PortPayload> $inputs
     * @param (Closure(string, string, string, string): void)|null $onTokenDelta
     *        Optional sink invoked as ($delta, $messageId, $nodeId, $runId) when
     *        a text-gen node streams tokens. Null means: no live streaming subscriber.
     */
    public function __construct(
        public string $nodeId,
        public array $config,
        public array $inputs,
        public string $runId,
        private ArtifactStoreContract $artifactStore,
        public array $humanProposalState = [],
        public ?RunMemoryStore $memory = null,
        public ?string $workflowSlug = null,
        public ?Closure $onTokenDelta = null,
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
            memory: $this->memory,
            workflowSlug: $this->workflowSlug,
            onTokenDelta: $this->onTokenDelta,
        );
    }

    // ─── Memory helpers (LP-I3) ────────────────────────────────────────────

    /**
     * True when a memory store has been threaded in. Templates may guard
     * recall/remember on this.
     */
    public function hasMemory(): bool
    {
        return $this->memory !== null;
    }

    /**
     * Read a value from the run memory store. Defaults to the per-workflow
     * scope ("workflow:{slug}"); pass $scopeOverride for a custom scope.
     *
     * @return array<string, mixed>|null
     */
    public function recall(string $key, ?string $scopeOverride = null): ?array
    {
        if ($this->memory === null) {
            return null;
        }

        return $this->memory->get($this->resolveScope($scopeOverride), $key);
    }

    /**
     * Upsert a value into the run memory store. No-op when no store is attached.
     *
     * @param array<string, mixed> $value
     */
    public function remember(
        string $key,
        array $value,
        ?DateTimeInterface $expires = null,
        ?string $scopeOverride = null,
    ): void {
        if ($this->memory === null) {
            return;
        }

        $this->memory->put(
            $this->resolveScope($scopeOverride),
            $key,
            $value,
            meta: ['source_run_id' => $this->runId, 'source_node_id' => $this->nodeId],
            expiresAt: $expires,
        );
    }

    // ─── Streaming helpers (LP-C1) ─────────────────────────────────────────

    /**
     * True when the caller has subscribed to token-level deltas (e.g., the
     * run page SSE stream). Text-gen templates may switch to streaming.
     */
    public function hasTokenDeltaSink(): bool
    {
        return $this->onTokenDelta !== null;
    }

    /**
     * Emit a token delta if a sink is attached, otherwise no-op.
     */
    public function emitTokenDelta(string $delta, string $messageId): void
    {
        if ($this->onTokenDelta === null) {
            return;
        }

        ($this->onTokenDelta)($delta, $messageId, $this->nodeId, $this->runId);
    }

    // ─── Internal ───────────────────────────────────────────────────────────

    private function resolveScope(?string $scopeOverride): string
    {
        if ($scopeOverride !== null) {
            return $scopeOverride;
        }

        $slug = $this->workflowSlug ?? 'unknown';

        return "workflow:{$slug}";
    }
}
