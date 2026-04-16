<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

/**
 * Represents a human's response to a HumanProposal.
 * Built by the webhook/channel handler and passed to handleResponse().
 */
readonly class HumanResponse
{
    /**
     * @param string $type Response type: pick, edit, prompt_back
     * @param int|null $selectedIndex Which option was picked (0-based)
     * @param string|null $editedContent Edited text content
     * @param string|null $feedback Free-text feedback for prompt-back
     * @param array<string, mixed> $raw Raw channel-specific data
     */
    public function __construct(
        public string $type,
        public ?int $selectedIndex = null,
        public ?string $editedContent = null,
        public ?string $feedback = null,
        public array $raw = [],
    ) {
        if (!in_array($type, ['pick', 'edit', 'prompt_back'], true)) {
            throw new \InvalidArgumentException("Response type must be pick, edit, or prompt_back, got: {$type}");
        }
    }

    public function isPick(): bool { return $this->type === 'pick'; }
    public function isEdit(): bool { return $this->type === 'edit'; }
    public function isPromptBack(): bool { return $this->type === 'prompt_back'; }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'selectedIndex' => $this->selectedIndex,
            'editedContent' => $this->editedContent,
            'feedback' => $this->feedback,
        ], fn ($v) => $v !== null);
    }

    public static function pick(int $index): self
    {
        return new self(type: 'pick', selectedIndex: $index);
    }

    public static function edit(string $content, ?int $fromIndex = null): self
    {
        return new self(type: 'edit', selectedIndex: $fromIndex, editedContent: $content);
    }

    public static function promptBack(string $feedback): self
    {
        return new self(type: 'prompt_back', feedback: $feedback);
    }
}
