<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\RunMemoryEntry;
use DateTimeInterface;

final class DatabaseRunMemoryStore implements RunMemoryStore
{
    public function get(string $scope, string $key): ?array
    {
        $entry = RunMemoryEntry::query()
            ->active()
            ->where('scope', $scope)
            ->where('key', $key)
            ->first();

        return $entry?->value;
    }

    public function put(
        string $scope,
        string $key,
        array $value,
        ?array $meta = null,
        ?DateTimeInterface $expiresAt = null,
    ): void {
        RunMemoryEntry::query()->updateOrCreate(
            ['scope' => $scope, 'key' => $key],
            [
                'value' => $value,
                'meta' => $meta,
                'expires_at' => $expiresAt,
            ],
        );
    }

    public function forget(string $scope, string $key): void
    {
        RunMemoryEntry::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->delete();
    }

    public function list(string $scope): array
    {
        /** @var array<string, array<string, mixed>> $entries */
        $entries = RunMemoryEntry::query()
            ->active()
            ->where('scope', $scope)
            ->get(['key', 'value'])
            ->mapWithKeys(fn (RunMemoryEntry $e): array => [$e->key => $e->value])
            ->all();

        return $entries;
    }
}
