<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use App\Domain\NodeCategory;
use App\Domain\Nodes\HumanProposal;
use App\Domain\Nodes\HumanResponse;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;

abstract class NodeTemplate
{
    abstract public string $type { get; }
    abstract public string $version { get; }
    abstract public string $title { get; }
    abstract public NodeCategory $category { get; }
    abstract public string $description { get; }

    abstract public function ports(): PortSchema;

    /**
     * @return array<string, mixed> Laravel validation rules for node config.
     */
    abstract public function configRules(): array;

    /**
     * @return array<string, mixed> Default config values for a new node of this type.
     */
    abstract public function defaultConfig(): array;

    /**
     * Execute the node. Every node has this — non-executable nodes
     * return preview output. Executable nodes call AI providers.
     *
     * @return array<string, PortPayload> Keyed by output port key.
     */
    abstract public function execute(NodeExecutionContext $ctx): array;

    /**
     * Which ports are active for a given config?
     * Default: all ports active. Override for config-dependent nodes like ImageGenerator.
     *
     * @param array<string, mixed> $config
     */
    public function activePorts(array $config): PortSchema
    {
        return $this->ports();
    }

    /**
     * The planner-readable guide card for this node.
     * Override in each template to provide real planner data.
     * Default returns a skeleton derived from ports + metadata.
     */
    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            nodeId: $this->type,
            purpose: $this->description,
            position: 'unassigned',
            vibeImpact: VibeImpact::Neutral,
            humanGate: false,
            knobs: [],
            readsFrom: [],
            writesTo: [],
            ports: array_merge(
                array_map(
                    fn (PortDefinition $p) => GuidePort::input($p->key, $p->dataType->value, $p->required),
                    $this->ports()->inputs,
                ),
                array_map(
                    fn (PortDefinition $p) => GuidePort::output($p->key, $p->dataType->value),
                    $this->ports()->outputs,
                ),
            ),
            whenToInclude: 'unassigned',
            whenToSkip: 'unassigned',
        );
    }

    /**
     * Does this node require human interaction during execution?
     * Override and return true for nodes that need propose/handleResponse cycle.
     */
    public function needsHumanLoop(): bool
    {
        return false;
    }

    /**
     * Generate initial output to present to a human for review/selection.
     * Only called if needsHumanLoop() returns true.
     *
     * @throws \LogicException if not overridden when needsHumanLoop is true
     */
    public function propose(NodeExecutionContext $ctx): HumanProposal
    {
        throw new \LogicException(
            static::class . ' declares needsHumanLoop() but does not implement propose()'
        );
    }

    /**
     * Process a human's response to a proposal.
     * Returns output array (execution complete) or new HumanProposal (loop for more input).
     *
     * @return array<string, \App\Domain\PortPayload>|HumanProposal
     * @throws \LogicException if not overridden when needsHumanLoop is true
     */
    public function handleResponse(NodeExecutionContext $ctx, HumanResponse $response): array|HumanProposal
    {
        throw new \LogicException(
            static::class . ' declares needsHumanLoop() but does not implement handleResponse()'
        );
    }
}
