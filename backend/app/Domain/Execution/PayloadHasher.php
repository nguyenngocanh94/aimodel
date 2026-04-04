<?php

declare(strict_types=1);

namespace App\Domain\Execution;

final class PayloadHasher
{
    /**
     * Volatile fields stripped from PortPayload inputs before hashing.
     * These change between runs but do not affect the semantic result.
     */
    private const VOLATILE_FIELDS = [
        'producedAt',
        'sourceNodeId',
        'sourcePortKey',
    ];

    /**
     * Hash a node config array deterministically.
     *
     * Recursively sorts keys so that insertion order never affects the hash.
     */
    public static function hashConfig(array $config): string
    {
        $normalised = self::recursiveKeySort($config);

        return hash('sha256', json_encode($normalised, JSON_THROW_ON_ERROR));
    }

    /**
     * Hash an array of PortPayload arrays deterministically.
     *
     * Strips volatile fields (producedAt, sourceNodeId, sourcePortKey) from
     * each payload and keeps only the semantically significant keys:
     * value, schemaType, status.
     */
    public static function hashInputs(array $inputs): string
    {
        $stripped = [];

        foreach ($inputs as $portKey => $payload) {
            $entry = is_array($payload) ? $payload : (array) $payload;

            foreach (self::VOLATILE_FIELDS as $field) {
                unset($entry[$field]);
            }

            $stripped[$portKey] = $entry;
        }

        $normalised = self::recursiveKeySort($stripped);

        return hash('sha256', json_encode($normalised, JSON_THROW_ON_ERROR));
    }

    /**
     * Recursively sort an array by keys (string keys only; numeric arrays
     * preserve order since position is semantically meaningful).
     */
    private static function recursiveKeySort(array $data): array
    {
        // Only sort associative arrays (string keys); leave lists in order.
        if (array_is_list($data)) {
            return array_map(
                static fn (mixed $v) => is_array($v) ? self::recursiveKeySort($v) : $v,
                $data,
            );
        }

        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::recursiveKeySort($value);
            }
        }

        return $data;
    }
}
