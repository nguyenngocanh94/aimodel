<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunStreamController extends Controller
{
    public function stream(ExecutionRun $run): StreamedResponse
    {
        return new StreamedResponse(function () use ($run): void {
            // Send catchup event with current run state and all node records
            $this->sendSseEvent('run.catchup', $this->buildCatchupPayload($run));

            // In testing environment, don't block on Redis subscription
            if (app()->environment('testing')) {
                return;
            }

            // Subscribe to Redis pub/sub channel for this run
            $channel = "run.{$run->id}";
            Redis::subscribe([$channel], function (string $message): void {
                // Parse the Redis message and forward as SSE event
                $data = json_decode($message, true);
                if ($data !== null && isset($data['event'], $data['data'])) {
                    $this->sendSseEvent($data['event'], $data['data']);
                }
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Build the catchup payload with current run state and all node records
     *
     * @return array<string, mixed>
     */
    private function buildCatchupPayload(ExecutionRun $run): array
    {
        $nodeRecords = NodeRunRecord::where('run_id', $run->id)
            ->get()
            ->map(fn (NodeRunRecord $record): array => [
                'nodeId' => $record->node_id,
                'status' => $record->status,
                'skipReason' => $record->skip_reason,
                'inputPayloads' => $record->input_payloads,
                'outputPayloads' => $record->output_payloads,
                'errorMessage' => $record->error_message,
                'usedCache' => $record->used_cache,
                'durationMs' => $record->duration_ms,
                'startedAt' => $record->started_at?->toIso8601String(),
                'completedAt' => $record->completed_at?->toIso8601String(),
            ])
            ->toArray();

        return [
            'run' => [
                'id' => $run->id,
                'workflowId' => $run->workflow_id,
                'trigger' => $run->trigger,
                'targetNodeId' => $run->target_node_id,
                'plannedNodeIds' => $run->planned_node_ids,
                'status' => $run->status,
                'documentSnapshot' => $run->document_snapshot,
                'documentHash' => $run->document_hash,
                'nodeConfigHashes' => $run->node_config_hashes,
                'startedAt' => $run->started_at?->toIso8601String(),
                'completedAt' => $run->completed_at?->toIso8601String(),
                'terminationReason' => $run->termination_reason,
            ],
            'nodeRunRecords' => $nodeRecords,
        ];
    }

    /**
     * Send an SSE event to the client
     *
     * @param array<string, mixed> $data
     */
    private function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_THROW_ON_ERROR) . "\n\n";

        // Flush the output buffer to send data immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
