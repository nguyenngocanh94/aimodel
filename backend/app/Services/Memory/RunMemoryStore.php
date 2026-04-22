<?php

declare(strict_types=1);

namespace App\Services\Memory;

use DateTimeInterface;

/**
 * Cross-run, cross-node key-value memory store for workflow executions.
 *
 * Scope conventions (by convention only, not enforced):
 *  - "workflow:{slug}"                     — per-workflow memory
 *  - "workflow:{slug}:user:{tgChatId}"     — per-workflow, per-user
 *  - "node:{nodeType}"                     — per-node-type
 *
 * Uniqueness is (scope, key). Values are JSON-serializable associative arrays.
 */
interface RunMemoryStore
{
    /**
     * Fetch a value by scope and key. Returns null when missing or expired.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $scope, string $key): ?array;

    /**
     * Upsert a value. `meta` is optional provenance (e.g., source_run_id).
     * `expiresAt` null means never expires.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>|null  $meta
     */
    public function put(
        string $scope,
        string $key,
        array $value,
        ?array $meta = null,
        ?DateTimeInterface $expiresAt = null,
    ): void;

    /**
     * Remove a specific key from a scope. No-op if missing.
     */
    public function forget(string $scope, string $key): void;

    /**
     * List all active (non-expired) entries in a scope, keyed by key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function list(string $scope): array;
}
