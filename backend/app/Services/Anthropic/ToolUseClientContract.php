<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

/**
 * Provider-neutral contract for a tool-use LLM client.
 *
 * Implementations translate between the caller's Anthropic-shaped message
 * history (so session state is homogeneous) and whatever wire format the
 * underlying provider expects. The returned ToolUseResult always carries
 * an Anthropic-shaped rawAssistantMessage so the caller can append it
 * verbatim to the session.
 */
interface ToolUseClientContract
{
    /**
     * @param  array<int, array{role: string, content: mixed}>  $messages
     * @param  array<int, ToolDefinition>  $tools
     */
    public function send(array $messages, string $systemPrompt, array $tools): ToolUseResult;
}
