<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\Workflow;
use App\Services\Anthropic\ToolDefinition;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\AgentTool;

final class ListWorkflowsTool implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_workflows',
            description: 'List all workflows the agent can trigger. Call once per conversation before choosing a workflow.',
            inputSchema: [
                'type' => 'object',
                'properties' => (object) [],
                'required' => [],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AgentContext $ctx): array
    {
        $workflows = Workflow::triggerable()
            ->get(['slug', 'name', 'nl_description', 'param_schema'])
            ->toArray();

        return ['workflows' => $workflows];
    }
}
