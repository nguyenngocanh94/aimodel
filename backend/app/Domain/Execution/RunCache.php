<?php

declare(strict_types=1);

namespace App\Domain\Execution;

use App\Models\RunCacheEntry;

final class RunCache
{
    /**
     * Build a deterministic cache key for a node execution.
     *
     * The key is a SHA-256 hash of:
     *   "nodeType:templateVersion:schemaVersion:configHash:inputsHash"
     */
    public function buildKey(
        string $nodeType,
        string $templateVersion,
        int $schemaVersion,
        array $config,
        array $inputs,
    ): string {
        $configHash = PayloadHasher::hashConfig($config);
        $inputsHash = PayloadHasher::hashInputs($inputs);

        $composite = implode(':', [
            $nodeType,
            $templateVersion,
            (string) $schemaVersion,
            $configHash,
            $inputsHash,
        ]);

        return hash('sha256', $composite);
    }

    /**
     * Retrieve cached output payloads for the given key.
     *
     * Returns the output_payloads array on hit, or null on miss.
     * Updates last_accessed_at on hit for LRU-style eviction support.
     */
    public function get(string $key): ?array
    {
        $entry = RunCacheEntry::where('cache_key', $key)->first();

        if ($entry === null) {
            return null;
        }

        $entry->update(['last_accessed_at' => now()]);

        return $entry->output_payloads;
    }

    /**
     * Store (or update) cached output payloads for the given key.
     */
    public function put(
        string $key,
        string $nodeType,
        string $templateVersion,
        array $outputPayloads,
    ): void {
        RunCacheEntry::updateOrCreate(
            ['cache_key' => $key],
            [
                'node_type' => $nodeType,
                'template_version' => $templateVersion,
                'output_payloads' => $outputPayloads,
                'created_at' => now(),
                'last_accessed_at' => now(),
            ],
        );
    }
}
