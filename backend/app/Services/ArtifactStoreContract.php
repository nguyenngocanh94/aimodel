<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Artifact;

interface ArtifactStoreContract
{
    public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact;

    public function url(Artifact $artifact): string;

    public function get(Artifact $artifact): string;

    public function delete(Artifact $artifact): void;

    public function deleteForRun(string $runId): void;
}
