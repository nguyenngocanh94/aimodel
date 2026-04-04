<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use App\Domain\NodeCategory;
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
}
