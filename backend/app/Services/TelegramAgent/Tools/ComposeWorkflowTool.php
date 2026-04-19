<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Stub: when aimodel-645 (AI Planner) lands, replace handle() with a real planner call.
 */
final class ComposeWorkflowTool implements Tool
{
    public function description(): string
    {
        return 'Compose (propose) a new workflow when no catalog entry matches the user\'s brief. Input: a free-text `brief`. Returns either {available: true, proposal: {...}} or {available: false, reason: string}.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'brief' => $schema->string()
                ->description('The user\'s creative brief including product, audience, tone, platform, etc.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return json_encode([
            'available' => false,
            'reason'    => 'Workflow composition is not yet implemented. Planning epic aimodel-645.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
