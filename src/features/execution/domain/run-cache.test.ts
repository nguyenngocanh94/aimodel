import { describe, it, expect, beforeEach } from 'vitest';
import {
  RunCache,
  stableHash,
  buildCacheKey,
  normalizeInputsForHash,
  type CacheKeyParts,
} from './run-cache';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

function makeParts(overrides: Partial<CacheKeyParts> = {}): CacheKeyParts {
  return {
    nodeType: 'scriptWriter',
    templateVersion: '1.0.0',
    schemaVersion: 1,
    configHash: 'cfg-abc',
    inputHash: 'inp-xyz',
    ...overrides,
  };
}

function makePayload(value: unknown = 'test'): PortPayload {
  return {
    value,
    status: 'success',
    schemaType: 'text',
    producedAt: new Date().toISOString(),
    sourceNodeId: 'node-1',
  };
}

describe('stableHash', () => {
  it('should produce same hash for equivalent objects with different key order', () => {
    const a = stableHash({ b: 2, a: 1 });
    const b = stableHash({ a: 1, b: 2 });
    expect(a).toBe(b);
  });

  it('should produce different hashes for different values', () => {
    expect(stableHash({ a: 1 })).not.toBe(stableHash({ a: 2 }));
  });

  it('should handle nested objects', () => {
    const a = stableHash({ x: { b: 2, a: 1 } });
    const b = stableHash({ x: { a: 1, b: 2 } });
    expect(a).toBe(b);
  });

  it('should handle arrays', () => {
    expect(stableHash([1, 2, 3])).toBe(stableHash([1, 2, 3]));
    expect(stableHash([1, 2, 3])).not.toBe(stableHash([3, 2, 1]));
  });
});

describe('buildCacheKey', () => {
  it('should produce a composite key string', () => {
    const key = buildCacheKey(makeParts());
    expect(key).toBe('scriptWriter:1.0.0:1:cfg-abc:inp-xyz');
  });

  it('should be different for different parts', () => {
    const a = buildCacheKey(makeParts());
    const b = buildCacheKey(makeParts({ configHash: 'cfg-different' }));
    expect(a).not.toBe(b);
  });
});

describe('normalizeInputsForHash', () => {
  it('should strip transient fields', () => {
    const inputs = { prompt: makePayload('hello') };
    const normalized = normalizeInputsForHash(inputs) as Record<string, unknown>;
    const entry = normalized['prompt'] as Record<string, unknown>;
    expect(entry).toEqual({
      value: 'hello',
      schemaType: 'text',
      status: 'success',
    });
    expect(entry).not.toHaveProperty('producedAt');
    expect(entry).not.toHaveProperty('sourceNodeId');
  });
});

describe('RunCache', () => {
  let cache: RunCache;

  beforeEach(() => {
    cache = new RunCache(5);
  });

  it('should return null for cache miss', () => {
    expect(cache.getReusableEntry(makeParts())).toBeNull();
  });

  it('should store and retrieve entries', () => {
    const parts = makeParts();
    const outputs = { output: makePayload('result') };
    cache.put(parts, outputs);

    const entry = cache.getReusableEntry(parts);
    expect(entry).not.toBeNull();
    expect(entry!.outputPayloads.output.value).toBe('result');
    expect(entry!.nodeType).toBe('scriptWriter');
  });

  it('should miss when config hash differs', () => {
    cache.put(makeParts(), { output: makePayload() });
    expect(cache.getReusableEntry(makeParts({ configHash: 'different' }))).toBeNull();
  });

  it('should miss when input hash differs', () => {
    cache.put(makeParts(), { output: makePayload() });
    expect(cache.getReusableEntry(makeParts({ inputHash: 'different' }))).toBeNull();
  });

  it('should miss when template version differs', () => {
    cache.put(makeParts(), { output: makePayload() });
    expect(cache.getReusableEntry(makeParts({ templateVersion: '2.0.0' }))).toBeNull();
  });

  it('should invalidate specific entry', () => {
    const parts = makeParts();
    cache.put(parts, { output: makePayload() });
    expect(cache.size).toBe(1);

    const removed = cache.invalidate(parts);
    expect(removed).toBe(true);
    expect(cache.size).toBe(0);
    expect(cache.getReusableEntry(parts)).toBeNull();
  });

  it('should invalidate by node type', () => {
    cache.put(makeParts({ nodeType: 'scriptWriter', configHash: 'a' }), { output: makePayload() });
    cache.put(makeParts({ nodeType: 'scriptWriter', configHash: 'b' }), { output: makePayload() });
    cache.put(makeParts({ nodeType: 'sceneSplitter', configHash: 'c' }), { output: makePayload() });
    expect(cache.size).toBe(3);

    const count = cache.invalidateByNodeType('scriptWriter');
    expect(count).toBe(2);
    expect(cache.size).toBe(1);
  });

  it('should evict entries when over capacity', () => {
    // Fill cache to capacity (5)
    for (let i = 0; i < 5; i++) {
      cache.put(makeParts({ configHash: `cfg-${i}` }), { output: makePayload(`val-${i}`) });
    }
    expect(cache.size).toBe(5);

    // Add one more — should evict one to stay at capacity
    cache.put(makeParts({ configHash: 'cfg-5' }), { output: makePayload('val-5') });
    expect(cache.size).toBe(5);

    // The newest entry should exist
    expect(cache.getReusableEntry(makeParts({ configHash: 'cfg-5' }))).not.toBeNull();
  });

  it('should clear all entries', () => {
    cache.put(makeParts({ configHash: 'a' }), { output: makePayload() });
    cache.put(makeParts({ configHash: 'b' }), { output: makePayload() });
    cache.clear();
    expect(cache.size).toBe(0);
  });
});
