<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Artifact;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArtifactController extends Controller
{
    public function show(Artifact $artifact): StreamedResponse
    {
        $disk = Storage::disk($artifact->disk);

        if (!$disk->exists($artifact->path)) {
            abort(404, 'Artifact file not found');
        }

        return $disk->download($artifact->path, $artifact->name, [
            'Content-Type' => $artifact->mime_type,
        ]);
    }
}
