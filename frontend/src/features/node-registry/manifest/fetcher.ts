/**
 * fetchNodeManifest — fetches GET /api/nodes/manifest from the backend.
 *
 * The Vite dev-server proxy already forwards /api → http://localhost:8000
 * (configured in vite.config.ts, no changes needed).
 *
 * Error taxonomy:
 *   'network'   fetch() itself rejected (offline, DNS, CORS)
 *   'http'      non-2xx HTTP status received
 *   'malformed' JSON parse failed OR shape validation failed
 */

import type { ManifestResponse } from './types';

export type ManifestFetchErrorKind = 'network' | 'http' | 'malformed';

export class ManifestFetchError extends Error {
  readonly kind: ManifestFetchErrorKind;
  readonly statusCode?: number;
  readonly cause?: unknown;

  constructor(kind: 'network', cause: unknown);
  constructor(kind: 'malformed', cause: unknown);
  constructor(kind: 'http', statusCode: number);
  constructor(kind: ManifestFetchErrorKind, causeOrStatus: unknown) {
    const msg =
      kind === 'http'
        ? `ManifestFetchError: HTTP ${String(causeOrStatus)}`
        : kind === 'network'
          ? 'ManifestFetchError: network error'
          : 'ManifestFetchError: malformed response';
    super(msg);
    this.name = 'ManifestFetchError';
    this.kind = kind;
    if (kind === 'http') {
      this.statusCode = causeOrStatus as number;
    } else {
      this.cause = causeOrStatus;
    }
  }
}

/**
 * Minimal runtime guard — trust the backend contract, guard against
 * non-2xx / malformed JSON and obviously broken shapes.
 */
function validateManifestShape(data: unknown): asserts data is ManifestResponse {
  if (typeof data !== 'object' || data === null) {
    throw new ManifestFetchError('malformed', new Error('Response is not an object'));
  }
  const obj = data as Record<string, unknown>;
  if (typeof obj['version'] !== 'string' || obj['version'].length === 0) {
    throw new ManifestFetchError(
      'malformed',
      new Error(`Expected non-empty string "version", got ${JSON.stringify(obj['version'])}`),
    );
  }
  if (typeof obj['nodes'] !== 'object' || obj['nodes'] === null || Array.isArray(obj['nodes'])) {
    throw new ManifestFetchError(
      'malformed',
      new Error(`Expected object "nodes", got ${JSON.stringify(obj['nodes'])}`),
    );
  }
}

export async function fetchNodeManifest(): Promise<ManifestResponse> {
  let response: Response;
  try {
    response = await fetch('/api/nodes/manifest', {
      headers: { Accept: 'application/json' },
    });
  } catch (err) {
    throw new ManifestFetchError('network', err);
  }

  if (!response.ok) {
    throw new ManifestFetchError('http', response.status);
  }

  let data: unknown;
  try {
    data = await response.json();
  } catch (err) {
    throw new ManifestFetchError('malformed', err);
  }

  validateManifestShape(data);
  return data;
}
