<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

/**
 * Parsed result of a single Anthropic Messages API round-trip.
 *
 * @phpstan-type ToolCall array{id: string, name: string, input: array}
 */
final readonly class ToolUseResult
{
    /**
     * @param  string                $stopReason          'end_turn' | 'tool_use' | 'max_tokens'
     * @param  array<int, array{id: string, name: string, input: array}>  $toolCalls
     * @param  array<int, string>    $textBlocks
     * @param  array<int, mixed>     $rawAssistantMessage Full content block list from the assistant
     *                                                    response; callers append this verbatim when
     *                                                    building the next turn's messages array.
     */
    public function __construct(
        public string $stopReason,
        public array $toolCalls,
        public array $textBlocks,
        public array $rawAssistantMessage,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
