<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ExecutionRunResource;
use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
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
}
