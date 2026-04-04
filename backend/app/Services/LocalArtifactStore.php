<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Artifact;
use Illuminate\Support\Facades\Storage;

class LocalArtifactStore implements ArtifactStoreContract
{
    public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact
    {
        $path = "artifacts/{$runId}/{$nodeId}/{$name}";

        Storage::disk('local')->put($path, $contents);

        return Artifact::create([
            'run_id' => $runId,
            'node_id' => $nodeId,
            'name' => $name,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'disk' => 'local',
            'path' => $path,
            'created_at' => now(),
        ]);
    }

    public function url(Artifact $artifact): string
    {
        return url("/api/artifacts/{$artifact->id}");
    }

    public function get(Artifact $artifact): string
    {
        return Storage::disk('local')->get($artifact->path);
    }

    public function delete(Artifact $artifact): void
    {
        Storage::disk('local')->delete($artifact->path);
        $artifact->delete();
    }

    public function deleteForRun(string $runId): void
    {
        $artifacts = Artifact::where('run_id', $runId)->get();

        foreach ($artifacts as $artifact) {
            Storage::disk('local')->delete($artifact->path);
        }

        Artifact::where('run_id', $runId)->delete();

        // Clean up empty directory
        Storage::disk('local')->deleteDirectory("artifacts/{$runId}");
    }
}
