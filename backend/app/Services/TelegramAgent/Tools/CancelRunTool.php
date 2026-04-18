<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Services\Anthropic\ToolDefinition;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\AgentTool;

final class CancelRunTool implements AgentTool
{
    /** Terminal statuses that cannot be cancelled. */
    private const TERMINAL_STATUSES = ['success', 'error', 'cancelled'];

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'cancel_run',
            description: 'Cancel a running or paused run by id.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'runId' => ['type' => 'string'],
                ],
                'required' => ['runId'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AgentContext $ctx): array
    {
        $runId = (string) ($input['runId'] ?? '');

        // Guard against non-UUID strings that would cause a DB type error
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $runId)) {
            return ['error' => 'run_not_found'];
        }

        $run = ExecutionRun::find($runId);

        if ($run === null) {
            return ['error' => 'run_not_found'];
        }

        if (in_array($run->status, self::TERMINAL_STATUSES, true)) {
            return ['error' => 'not_cancellable', 'status' => $run->status];
        }

        $run->update([
            'status' => 'cancelled',
            'termination_reason' => 'userCancelled',
            'completed_at' => now(),
        ]);

        return ['runId' => $run->id, 'status' => 'cancelled'];
    }
}
