<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * A single node inside a WorkflowPlan.
 *
 * The planner must provide a `reason` for every node so drift-eval (645.5)
 * and humans can critique the creative choice.
 */
final readonly class PlanNode
{
    /**
     * @param string $id Unique within the plan — identity for edge references.
     * @param string $type NodeTemplate `type` value (e.g. 'scriptWriter', 'humanGate').
     * @param array<string, mixed> $config Config dictionary validated against the template's configRules().
     * @param string $reason Why the planner chose this node in this position. Human-readable.
     * @param string|null $label Optional display label. When absent, the template's title is used.
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $config,
        public string $reason,
        public ?string $label = null,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('PlanNode.id must be non-empty');
        }
        if ($type === '') {
            throw new \InvalidArgumentException('PlanNode.type must be non-empty');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'config' => $this->config,
            'reason' => $this->reason,
            'label' => $this->label,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            config: (array) ($data['config'] ?? []),
            reason: (string) ($data['reason'] ?? ''),
            label: isset($data['label']) ? (string) $data['label'] : null,
        );
    }
}
