<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

readonly class GuidePort
{
    public function __construct(
        public string $key,
        public string $direction,
        public string $type,
        public bool $required,
    ) {
        if (!in_array($direction, ['input', 'output'], true)) {
            throw new \InvalidArgumentException("Direction must be 'input' or 'output', got: {$direction}");
        }
    }

    public static function input(string $key, string $type, bool $required = true): self
    {
        return new self($key, 'input', $type, $required);
    }

    public static function output(string $key, string $type): self
    {
        return new self($key, 'output', $type, false);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'direction' => $this->direction,
            'type' => $this->type,
            'required' => $this->required,
        ];
    }
}
