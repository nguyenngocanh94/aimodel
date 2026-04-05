<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\RunCompleted;
use App\Http\Resources\ExecutionRunResource;
use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RunController extends Controller
{
    public function store(Request $request, Workflow $workflow): JsonResponse
    {
        $validated = $request->validate([
            'trigger' => ['required', 'string', Rule::in(['runWorkflow', 'runNode', 'runFromHere', 'runUpToHere'])],
            'targetNodeId' => 'nullable|string',
        ]);

        $document = $workflow->document;

        // Compute document hash
        $documentHash = hash('sha256', json_encode($document, JSON_THROW_ON_ERROR));

        // Compute per-node config hashes
        $nodeConfigHashes = [];
        foreach ($document['nodes'] ?? [] as $node) {
            $nodeConfigHashes[$node['id']] = hash('sha256', json_encode($node['config'] ?? [], JSON_THROW_ON_ERROR));
        }

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => $validated['trigger'],
            'target_node_id' => $validated['targetNodeId'] ?? null,
            'status' => 'pending',
            'document_snapshot' => $document,
            'document_hash' => $documentHash,
            'node_config_hashes' => $nodeConfigHashes,
        ]);

        RunWorkflowJob::dispatch($run->id);

        return (new ExecutionRunResource($run))
            ->response()
            ->setStatusCode(202);
    }

    public function show(ExecutionRun $run): ExecutionRunResource
    {
        $run->load('nodeRunRecords');

        return new ExecutionRunResource($run);
    }

    public function cancel(ExecutionRun $run): JsonResponse
    {
        if (!in_array($run->status, ['running', 'awaitingReview', 'pending'], true)) {
            return response()->json([
                'error' => 'Run cannot be cancelled in its current status',
                'status' => $run->status,
            ], 422);
        }

        $run->update(['status' => 'cancelled']);

        // Mark remaining pending/running/awaitingReview node records as cancelled
        NodeRunRecord::where('run_id', $run->id)
            ->whereIn('status', ['pending', 'running', 'awaitingReview'])
            ->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

        RunCompleted::dispatch(
            $run->id,
            'cancelled',
            'userCancelled',
            now()->toIso8601String(),
        );

        $run->update(['completed_at' => now(), 'termination_reason' => 'userCancelled']);

        $run->load('nodeRunRecords');

        return (new ExecutionRunResource($run))
            ->response()
            ->setStatusCode(200);
    }

    public function review(Request $request, ExecutionRun $run): JsonResponse
    {
        $validated = $request->validate([
            'nodeId' => ['required', 'string'],
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'notes' => ['nullable', 'string'],
        ]);

        if ($run->status !== 'awaitingReview') {
            return response()->json([
                'error' => 'Run is not awaiting review',
                'status' => $run->status,
            ], 422);
        }

        $record = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', $validated['nodeId'])
            ->where('status', 'awaitingReview')
            ->first();

        if ($record === null) {
            return response()->json([
                'error' => 'No awaiting review record found for this node',
            ], 422);
        }

        $record->update([
            'output_payloads' => [
                'decision' => $validated['decision'],
                'notes' => $validated['notes'] ?? null,
                'reviewedAt' => now()->toIso8601String(),
            ],
            'status' => $validated['decision'] === 'approve' ? 'success' : 'error',
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Review submitted',
            'nodeId' => $validated['nodeId'],
            'decision' => $validated['decision'],
        ]);
    }
}
