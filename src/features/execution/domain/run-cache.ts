/**
 * RunCache - AiModel-ecs.4
 * Stores and retrieves mock execution outputs with LRU eviction.
 * Per plan section 11.7
 */

import type { PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Cache key computation
// ============================================================

export interface CacheKeyParts {
  readonly nodeType: string;
  readonly templateVersion: string;
  readonly schemaVersion: number;
  readonly configHash: string;
  readonly inputHash: string;
}

/**
 * Compute a stable hash string from an arbitrary value.
 * Uses JSON serialization with sorted keys for determinism.
 */
export function stableHash(value: unknown): string {
  const json = JSON.stringify(value, (_key, val) => {
    if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
      return Object.keys(val as Record<string, unknown>)
        .sort()
        .reduce<Record<string, unknown>>((sorted, k) => {
          sorted[k] = (val as Record<string, unknown>)[k];
          return sorted;
        }, {});
    }
    return val;
  });
  // Simple string hash — sufficient for in-memory cache keying
  let hash = 0;
  for (let i = 0; i < json.length; i++) {
    const char = json.charCodeAt(i);
    hash = ((hash << 5) - hash + char) | 0;
  }
  return hash.toString(36);
}

/** Build a composite cache key from the parts. */
export function buildCacheKey(parts: CacheKeyParts): string {
  return `${parts.nodeType}:${parts.templateVersion}:${parts.schemaVersion}:${parts.configHash}:${parts.inputHash}`;
}

/**
 * Normalize input payloads for hashing.
 * Strips transient fields (producedAt, sourceNodeId, sourcePortKey) that don't affect computation.
 */
export function normalizeInputsForHash(
  inputs: Readonly<Record<string, PortPayload>>,
): unknown {
  const normalized: Record<string, unknown> = {};
  for (const [key, payload] of Object.entries(inputs)) {
    normalized[key] = {
      value: payload.value,
      schemaType: payload.schemaType,
      status: payload.status,
    };
  }
  return normalized;
}

// ============================================================
// Cache entry
// ============================================================

export interface RunCacheEntry {
  readonly key: string;
  readonly nodeType: string;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
  readonly cachedAt: string;
  /** Last time this entry was accessed (for LRU). */
  lastAccessedAt: string;
}

// ============================================================
// RunCache
// ============================================================

const DEFAULT_MAX_ENTRIES = 200;

export class RunCache {
  private readonly entries = new Map<string, RunCacheEntry>();
  private readonly maxEntries: number;

  constructor(maxEntries = DEFAULT_MAX_ENTRIES) {
    this.maxEntries = maxEntries;
  }

  /** Check if a cached result exists for the given key parts. */
  getReusableEntry(parts: CacheKeyParts): RunCacheEntry | null {
    const key = buildCacheKey(parts);
    const entry = this.entries.get(key);
    if (!entry) return null;

    // Update LRU access time
    entry.lastAccessedAt = new Date().toISOString();
    return entry;
  }

  /** Store an execution result in the cache. */
  put(
    parts: CacheKeyParts,
    outputPayloads: Readonly<Record<string, PortPayload>>,
  ): void {
    const key = buildCacheKey(parts);
    const now = new Date().toISOString();

    this.entries.set(key, {
      key,
      nodeType: parts.nodeType,
      outputPayloads,
      cachedAt: now,
      lastAccessedAt: now,
    });

    this.evictIfNeeded();
  }

  /** Invalidate a specific cache entry. */
  invalidate(parts: CacheKeyParts): boolean {
    const key = buildCacheKey(parts);
    return this.entries.delete(key);
  }

  /** Invalidate all entries for a specific node type. */
  invalidateByNodeType(nodeType: string): number {
    let count = 0;
    for (const [key, entry] of this.entries) {
      if (entry.nodeType === nodeType) {
        this.entries.delete(key);
        count++;
      }
    }
    return count;
  }

  /** Clear all cache entries. */
  clear(): void {
    this.entries.clear();
  }

  /** Number of entries in the cache. */
  get size(): number {
    return this.entries.size;
  }

  /** Evict least-recently-used entries if over capacity. */
  private evictIfNeeded(): void {
    while (this.entries.size > this.maxEntries) {
      // Find the entry with the oldest lastAccessedAt
      let oldestKey: string | null = null;
      let oldestTime = Infinity;

      for (const [key, entry] of this.entries) {
        const time = new Date(entry.lastAccessedAt).getTime();
        if (time < oldestTime) {
          oldestTime = time;
          oldestKey = key;
        }
      }

      if (oldestKey) {
        this.entries.delete(oldestKey);
      } else {
        break;
      }
    }
  }
}

/** Singleton run cache instance for the app. */
export const runCache = new RunCache();
