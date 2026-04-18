<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use App\Services\Anthropic\ToolDefinition;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\AgentTool;

final class GetRunStatusTool implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_run_status',
            description: 'Fetch current status + current node + any pending interaction for a run id.',
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

        // Find the current node — last running or awaitingHuman NodeRunRecord
        $currentNodeRecord = NodeRunRecord::where('run_id', $run->id)
            ->whereIn('status', ['running', 'awaitingReview', 'awaitingHuman'])
            ->orderByDesc('started_at')
            ->first();

        // Find pending interaction summary if any
        $pending = PendingInteraction::where('run_id', $run->id)
            ->waiting()
            ->first();

        $pendingSummary = null;
        if ($pending !== null) {
            $pendingSummary = [
                'nodeId' => $pending->node_id,
                'channel' => $pending->channel,
                'status' => $pending->status,
                'proposal' => $pending->proposal_payload,
            ];
        }

        return [
            'runId' => $run->id,
            'status' => $run->status,
            'startedAt' => $run->started_at?->toIso8601String(),
            'completedAt' => $run->completed_at?->toIso8601String(),
            'currentNodeId' => $currentNodeRecord?->node_id,
            'pending' => $pendingSummary,
            'terminationReason' => $run->termination_reason,
        ];
    }
}
