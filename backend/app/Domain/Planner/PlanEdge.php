<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * A directed edge between two PlanNodes on named ports.
 *
 * Field names mirror the frontend `WorkflowEdge` shape
 * (`sourceNodeId`, `sourcePortKey`, `targetNodeId`, `targetPortKey`)
 * so conversion to a runnable WorkflowDocument is a direct copy.
 */
final readonly class PlanEdge
{
    /**
     * @param string $sourceNodeId Producer PlanNode.id.
     * @param string $sourcePortKey Producer output port key.
     * @param string $targetNodeId Consumer PlanNode.id.
     * @param string $targetPortKey Consumer input port key.
     * @param string $reason Why this connection exists (data dependency narrative).
     */
    public function __construct(
        public string $sourceNodeId,
        public string $sourcePortKey,
        public string $targetNodeId,
        public string $targetPortKey,
        public string $reason,
    ) {
        if ($sourceNodeId === '' || $targetNodeId === '') {
            throw new \InvalidArgumentException('PlanEdge endpoints must be non-empty');
        }
        if ($sourcePortKey === '' || $targetPortKey === '') {
            throw new \InvalidArgumentException('PlanEdge port keys must be non-empty');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sourceNodeId' => $this->sourceNodeId,
            'sourcePortKey' => $this->sourcePortKey,
            'targetNodeId' => $this->targetNodeId,
            'targetPortKey' => $this->targetPortKey,
            'reason' => $this->reason,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceNodeId: (string) ($data['sourceNodeId'] ?? ''),
            sourcePortKey: (string) ($data['sourcePortKey'] ?? ''),
            targetNodeId: (string) ($data['targetNodeId'] ?? ''),
            targetPortKey: (string) ($data['targetPortKey'] ?? ''),
            reason: (string) ($data['reason'] ?? ''),
        );
    }
}
