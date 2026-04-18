<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class GetRunStatusTool implements Tool
{
    public function description(): string
    {
        return 'Fetch current status + current node + any pending interaction for a run id.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'runId' => $schema->string()->description('The UUID of the execution run')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $runId = (string) $request->string('runId', '');

        // Guard against non-UUID strings that would cause a DB type error
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $runId)) {
            return json_encode(['error' => 'run_not_found'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $run = ExecutionRun::find($runId);

        if ($run === null) {
            return json_encode(['error' => 'run_not_found'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
                'nodeId'   => $pending->node_id,
                'channel'  => $pending->channel,
                'status'   => $pending->status,
                'proposal' => $pending->proposal_payload,
            ];
        }

        return json_encode([
            'runId'             => $run->id,
            'status'            => $run->status,
            'startedAt'         => $run->started_at?->toIso8601String(),
            'completedAt'       => $run->completed_at?->toIso8601String(),
            'currentNodeId'     => $currentNodeRecord?->node_id,
            'pending'           => $pendingSummary,
            'terminationReason' => $run->termination_reason,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
