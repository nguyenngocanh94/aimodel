<?php

declare(strict_types=1);

namespace App\Domain;

readonly class PortDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $direction,
        public DataType $dataType,
        public bool $required,
        public bool $multiple,
        public ?string $description = null,
    ) {
        if (!in_array($direction, ['input', 'output'], true)) {
            throw new \InvalidArgumentException("Direction must be 'input' or 'output', got: {$direction}");
        }
    }

    public static function input(
        string $key,
        string $label,
        DataType $dataType,
        bool $required = true,
        bool $multiple = false,
        ?string $description = null,
    ): self {
        return new self($key, $label, 'input', $dataType, $required, $multiple, $description);
    }

    public static function output(
        string $key,
        string $label,
        DataType $dataType,
        bool $multiple = false,
        ?string $description = null,
    ): self {
        return new self($key, $label, 'output', $dataType, false, $multiple, $description);
    }

    public function isInput(): bool
    {
        return $this->direction === 'input';
    }

    public function isOutput(): bool
    {
        return $this->direction === 'output';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'direction' => $this->direction,
            'dataType' => $this->dataType->value,
            'required' => $this->required,
            'multiple' => $this->multiple,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            label: $data['label'],
            direction: $data['direction'],
            dataType: DataType::from($data['dataType']),
            required: $data['required'] ?? true,
            multiple: $data['multiple'] ?? false,
            description: $data['description'] ?? null,
        );
    }
}
