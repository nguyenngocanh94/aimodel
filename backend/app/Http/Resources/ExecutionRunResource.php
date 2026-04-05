<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflowId' => $this->workflow_id,
            'mode' => $this->mode,
            'trigger' => $this->trigger,
            'targetNodeId' => $this->target_node_id,
            'plannedNodeIds' => $this->planned_node_ids,
            'status' => $this->status,
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'terminationReason' => $this->termination_reason,
            'nodeRunRecords' => NodeRunRecordResource::collection($this->whenLoaded('nodeRunRecords')),
            'summary' => $this->when($this->getAttribute('summary') !== null, $this->getAttribute('summary')),
        ];
    }
}
