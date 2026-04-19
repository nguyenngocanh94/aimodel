/**
 * ManifestProvider + useNodeManifest + useNodeManifestEntry
 *
 * Caching strategy:
 *   1. On mount, read localStorage key "aimodel:node-manifest".
 *      If valid (has version + nodes), set status=ready immediately.
 *   2. Fire a background fetch regardless.
 *   3. If the fetched version differs from cache, replace and re-render.
 *
 * This gives instant-first-paint with eventual freshness.
 */

import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { fetchNodeManifest } from './fetcher';
import type { ManifestResponse, NodeManifest } from './types';

// ────────────────────────────────────────────────────────────────
// Types
// ────────────────────────────────────────────────────────────────

export interface ManifestContextValue {
  readonly status: 'loading' | 'ready' | 'error';
  readonly manifest?: ManifestResponse;
  readonly error?: Error;
  readonly refetch: () => Promise<void>;
}

// ────────────────────────────────────────────────────────────────
// localStorage helpers
// ────────────────────────────────────────────────────────────────

const CACHE_KEY = 'aimodel:node-manifest';

interface CachedManifest {
  version: string;
  nodes: ManifestResponse['nodes'];
  fetchedAt: string;
}

function readCache(): ManifestResponse | undefined {
  try {
    const raw = localStorage.getItem(CACHE_KEY);
    if (!raw) return undefined;
    const parsed = JSON.parse(raw) as Partial<CachedManifest>;
    if (
      typeof parsed.version === 'string' &&
      parsed.version.length > 0 &&
      typeof parsed.nodes === 'object' &&
      parsed.nodes !== null
    ) {
      return { version: parsed.version, nodes: parsed.nodes };
    }
  } catch {
    // corrupt cache — ignore
  }
  return undefined;
}

function writeCache(manifest: ManifestResponse): void {
  try {
    const payload: CachedManifest = {
      version: manifest.version,
      nodes: manifest.nodes,
      fetchedAt: new Date().toISOString(),
    };
    localStorage.setItem(CACHE_KEY, JSON.stringify(payload));
  } catch {
    // storage quota exceeded or private mode — ignore
  }
}

// ────────────────────────────────────────────────────────────────
// Context
// ────────────────────────────────────────────────────────────────

export const ManifestContext = createContext<ManifestContextValue | null>(null);

// ────────────────────────────────────────────────────────────────
// Provider
// ────────────────────────────────────────────────────────────────

interface ManifestProviderProps {
  children: React.ReactNode;
}

export function ManifestProvider({ children }: ManifestProviderProps) {
  const cached = useRef<ManifestResponse | undefined>(readCache());

  const [state, setState] = useState<{
    status: 'loading' | 'ready' | 'error';
    manifest?: ManifestResponse;
    error?: Error;
  }>(() => {
    if (cached.current) {
      return { status: 'ready', manifest: cached.current };
    }
    return { status: 'loading' };
  });

  const doFetch = useCallback(async () => {
    try {
      const fresh = await fetchNodeManifest();
      writeCache(fresh);
      setState((prev) => {
        // Only update if version changed (or we were loading/error)
        if (prev.status === 'ready' && prev.manifest?.version === fresh.version) {
          return prev;
        }
        return { status: 'ready', manifest: fresh };
      });
    } catch (err) {
      setState((prev) => {
        // If we already have data from cache, keep 'ready' — don't degrade UX
        if (prev.status === 'ready' && prev.manifest !== undefined) {
          return prev;
        }
        return {
          status: 'error',
          error: err instanceof Error ? err : new Error(String(err)),
        };
      });
    }
  }, []);

  // On mount: fire background fetch (cache already painted if available)
  useEffect(() => {
    void doFetch();
  }, [doFetch]);

  const refetch = useCallback(async () => {
    setState((prev) => ({ ...prev, status: prev.manifest ? 'ready' : 'loading' }));
    await doFetch();
  }, [doFetch]);

  const value: ManifestContextValue = {
    status: state.status,
    manifest: state.manifest,
    error: state.error,
    refetch,
  };

  return (
    <ManifestContext.Provider value={value}>{children}</ManifestContext.Provider>
  );
}

// ────────────────────────────────────────────────────────────────
// Hooks
// ────────────────────────────────────────────────────────────────

/**
 * Returns the full manifest context (status, manifest map, error, refetch).
 * Must be used inside <ManifestProvider>.
 */
export function useNodeManifest(): ManifestContextValue {
  const ctx = useContext(ManifestContext);
  if (!ctx) {
    throw new Error('useNodeManifest must be used inside <ManifestProvider>');
  }
  return ctx;
}

/**
 * Convenience hook — returns the NodeManifest for a specific type,
 * or undefined if not yet ready or the type is unknown.
 */
export function useNodeManifestEntry(type: string): NodeManifest | undefined {
  const { manifest } = useNodeManifest();
  return manifest?.nodes[type];
}
