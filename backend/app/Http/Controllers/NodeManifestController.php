<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Http\JsonResponse;

final class NodeManifestController extends Controller
{
    /**
     * Per-process cache of the full manifest.
     * Derived from static template metadata; no per-request state.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $cached = null;

    public function __construct(
        private NodeManifestBuilder $builder,
        private NodeTemplateRegistry $registry,
    ) {}

    /**
     * GET /api/nodes/manifest
     *
     * Returns the full node manifest: version hash + all template manifests.
     * Cached per process (templates are registered once at boot).
     * Cache-Control: public, max-age=300 for downstream HTTP caches.
     */
    public function show(): JsonResponse
    {
        if (self::$cached === null) {
            self::$cached = $this->builder->buildAll($this->registry);
        }

        return response()
            ->json(self::$cached)
            ->header('Cache-Control', 'public, max-age=300');
    }
}
