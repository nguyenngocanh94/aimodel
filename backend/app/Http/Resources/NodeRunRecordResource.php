<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeRunRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'runId' => $this->run_id,
            'nodeId' => $this->node_id,
            'status' => $this->status,
            'skipReason' => $this->skip_reason,
            'inputPayloads' => $this->input_payloads,
            'outputPayloads' => $this->output_payloads,
            'errorMessage' => $this->error_message,
            'usedCache' => $this->used_cache,
            'durationMs' => $this->duration_ms,
        ];
    }
}
