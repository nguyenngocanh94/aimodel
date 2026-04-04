<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\WorkflowResource;
use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Workflow::query()->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $query->where('name', 'ILIKE', '%' . $request->input('search') . '%');
        }

        if ($request->filled('tags')) {
            $tags = is_array($request->input('tags'))
                ? $request->input('tags')
                : explode(',', $request->input('tags'));

            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', trim($tag));
            }
        }

        return WorkflowResource::collection(
            $query->simplePaginate($request->input('per_page', 15))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document' => 'required|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        $workflow = Workflow::create($validated);

        return (new WorkflowResource($workflow))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workflow $workflow): WorkflowResource
    {
        return new WorkflowResource($workflow);
    }

    public function update(Request $request, Workflow $workflow): WorkflowResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'document' => 'sometimes|required|array',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        $workflow->update($validated);

        return new WorkflowResource($workflow);
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow->delete();

        return response()->json(null, 204);
    }
}
