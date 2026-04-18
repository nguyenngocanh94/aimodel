<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListWorkflowsTool implements Tool
{
    public function description(): string
    {
        return 'List all workflows the agent can trigger. Call once per conversation before choosing a workflow.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $workflows = Workflow::triggerable()
            ->get(['slug', 'name', 'nl_description', 'param_schema'])
            ->toArray();

        return json_encode(['workflows' => $workflows], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
