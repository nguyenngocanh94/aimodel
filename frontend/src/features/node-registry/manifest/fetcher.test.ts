/**
 * fetcher.test.ts — unit tests for fetchNodeManifest
 */

import { describe, it, expect, vi, afterEach } from 'vitest';
import { fetchNodeManifest, ManifestFetchError } from './fetcher';
import type { ManifestResponse } from './types';

const VALID_MANIFEST: ManifestResponse = {
  version: 'a'.repeat(64),
  nodes: {
    storyWriter: {
      type: 'storyWriter',
      version: '1.0.0',
      title: 'Story Writer',
      description: 'Writes stories',
      category: 'script',
      ports: { inputs: [], outputs: [] },
      configSchema: { type: 'object', properties: {}, additionalProperties: false, required: [] },
      defaultConfig: {},
      humanGateEnabled: false,
      executable: true,
    },
  },
};

function makeFetchMock(status: number, body: unknown, rejectWith?: Error) {
  return vi.fn().mockImplementation(() => {
    if (rejectWith) return Promise.reject(rejectWith);
    return Promise.resolve({
      ok: status >= 200 && status < 300,
      status,
      json: () =>
        typeof body === 'string' && body === '__INVALID_JSON__'
          ? Promise.reject(new SyntaxError('Unexpected token'))
          : Promise.resolve(body),
    });
  });
}

describe('fetchNodeManifest', () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
  });

  it('happy path — resolves with valid manifest', async () => {
    globalThis.fetch = makeFetchMock(200, VALID_MANIFEST);
    const result = await fetchNodeManifest();
    expect(result.version).toBe(VALID_MANIFEST.version);
    expect(result.nodes['storyWriter']?.type).toBe('storyWriter');
  });

  it('HTTP 404 — throws ManifestFetchError with kind=http and statusCode=404', async () => {
    globalThis.fetch = makeFetchMock(404, { error: 'Not found' });
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError &&
        err.kind === 'http' &&
        err.statusCode === 404,
    );
  });

  it('HTTP 500 — throws ManifestFetchError with kind=http and statusCode=500', async () => {
    globalThis.fetch = makeFetchMock(500, { error: 'Server error' });
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError &&
        err.kind === 'http' &&
        err.statusCode === 500,
    );
  });

  it('non-JSON body — throws ManifestFetchError with kind=malformed', async () => {
    globalThis.fetch = makeFetchMock(200, '__INVALID_JSON__');
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError && err.kind === 'malformed',
    );
  });

  it('malformed JSON (missing version) — throws ManifestFetchError with kind=malformed', async () => {
    globalThis.fetch = makeFetchMock(200, { nodes: {} }); // version missing
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError && err.kind === 'malformed',
    );
  });

  it('malformed JSON (nodes is array) — throws ManifestFetchError with kind=malformed', async () => {
    globalThis.fetch = makeFetchMock(200, { version: 'abc', nodes: [] });
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError && err.kind === 'malformed',
    );
  });

  it('network error (fetch rejects) — throws ManifestFetchError with kind=network', async () => {
    const networkErr = new TypeError('Failed to fetch');
    globalThis.fetch = makeFetchMock(0, null, networkErr);
    await expect(fetchNodeManifest()).rejects.toSatisfy(
      (err: unknown) =>
        err instanceof ManifestFetchError &&
        err.kind === 'network' &&
        err.cause === networkErr,
    );
  });
});
