<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Domain\RunTrigger;

final readonly class ExecutionPlan
{
    /**
     * @param  list<string>  $orderedNodeIds  Topologically sorted node IDs to execute.
     * @param  list<string>  $skippedNodeIds  Node IDs excluded (disabled nodes).
     */
    public function __construct(
        public array $orderedNodeIds,
        public array $skippedNodeIds,
        public RunTrigger $trigger,
        public ?string $targetNodeId = null,
    ) {}
}
