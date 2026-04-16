<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

readonly class GuideKnob
{
    /**
     * @param string $name Knob identifier matching the config key
     * @param string $type Data type: enum, int, float, bool, string[]
     * @param list<string|int|float|bool>|null $options Valid values for enum types
     * @param string|int|float|bool $default Sensible default value
     * @param string $effect 1-sentence creative outcome description
     * @param array<string, string|int|float|bool> $vibeMapping Quick lookup: vibe_mode => recommended value
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?array $options,
        public string|int|float|bool $default,
        public string $effect,
        public array $vibeMapping = [],
    ) {}

    public function toArray(): array
    {
        $arr = [
            'name' => $this->name,
            'type' => $this->type,
            'default' => $this->default,
            'effect' => $this->effect,
        ];

        if ($this->options !== null) {
            $arr['options'] = $this->options;
        }

        if ($this->vibeMapping !== []) {
            $arr['vibe_mapping'] = $this->vibeMapping;
        }

        return $arr;
    }
}
