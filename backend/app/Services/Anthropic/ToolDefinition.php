<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

final readonly class ToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
    ) {}

    /**
     * Serialize to the shape Anthropic's Messages API expects.
     *
     * @return array{name: string, description: string, input_schema: array}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
        ];
    }
}
