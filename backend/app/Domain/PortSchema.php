<?php

declare(strict_types=1);

namespace App\Domain;

readonly class PortSchema
{
    /**
     * @param PortDefinition[] $inputs
     * @param PortDefinition[] $outputs
     */
    public function __construct(
        public array $inputs = [],
        public array $outputs = [],
    ) {
        foreach ($inputs as $input) {
            if (!$input instanceof PortDefinition) {
                throw new \InvalidArgumentException('All inputs must be PortDefinition instances');
            }
        }
        foreach ($outputs as $output) {
            if (!$output instanceof PortDefinition) {
                throw new \InvalidArgumentException('All outputs must be PortDefinition instances');
            }
        }
    }

    public function getInput(string $key): ?PortDefinition
    {
        foreach ($this->inputs as $input) {
            if ($input->key === $key) {
                return $input;
            }
        }
        return null;
    }

    public function getOutput(string $key): ?PortDefinition
    {
        foreach ($this->outputs as $output) {
            if ($output->key === $key) {
                return $output;
            }
        }
        return null;
    }

    public function hasInput(string $key): bool
    {
        return $this->getInput($key) !== null;
    }

    public function hasOutput(string $key): bool
    {
        return $this->getOutput($key) !== null;
    }

    public function toArray(): array
    {
        return [
            'inputs' => array_map(fn (PortDefinition $p) => $p->toArray(), $this->inputs),
            'outputs' => array_map(fn (PortDefinition $p) => $p->toArray(), $this->outputs),
        ];
    }

    public static function fromArray(array $data): self
    {
        $inputs = array_map(
            fn (array $p) => PortDefinition::fromArray($p),
            $data['inputs'] ?? []
        );
        $outputs = array_map(
            fn (array $p) => PortDefinition::fromArray($p),
            $data['outputs'] ?? []
        );

        return new self($inputs, $outputs);
    }
}
