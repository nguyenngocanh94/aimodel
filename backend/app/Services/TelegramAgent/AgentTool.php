<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Services\Anthropic\ToolDefinition;

interface AgentTool
{
    public function definition(): ToolDefinition;

    /**
     * Execute the tool and return a tool_result content payload.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AgentContext $ctx): array;
}
