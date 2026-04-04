<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtifactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'runId' => $this->run_id,
            'nodeId' => $this->node_id,
            'name' => $this->name,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'url' => $this->path,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
