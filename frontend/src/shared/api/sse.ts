/**
 * SSE Client — EventSource wrapper for run streaming
 *
 * Connects to `/api/runs/:runId/stream` and dispatches typed callbacks
 * for each SSE event. Auto-reconnects with exponential backoff on error.
 */

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

// ============================================================
// Types
// ============================================================

export interface CatchupData {
  run: unknown;
  nodeRunRecords: unknown[];
}

export interface RunStartedData {
  runId: string;
  status: string;
  plannedNodeIds: string[];
}

export interface NodeStatusData {
  runId: string;
  nodeId: string;
  status: string;
  outputPayloads?: unknown;
  durationMs?: number;
  errorMessage?: string;
  skipReason?: string;
  usedCache?: boolean;
}

/**
 * LP-C4: token-level streaming delta from a live text-gen node.
 *
 * One frame per chunk the LLM emits. `seq` is monotonically increasing per
 * (runId, nodeId) so consumers can order frames defensively; the SSE
 * controller preserves order but Redis pub/sub on a shared channel does
 * not guarantee it under concurrent writers.
 *
 * Consumers typically keep an in-memory buffer keyed by `(nodeId, messageId)`
 * and render `delta` appended to it. When a terminal `node.status` frame
 * with `status === 'success'` arrives for the node, flush the buffer —
 * the authoritative text is then on `outputPayloads`.
 */
export interface NodeTokenDeltaData {
  runId: string;
  nodeId: string;
  messageId: string;
  delta: string;
  seq: number;
}

export interface RunCompletedData {
  runId: string;
  status: string;
  terminationReason?: string;
  completedAt?: string;
}

export interface StreamCallbacks {
  onCatchup: (data: CatchupData) => void;
  onRunStarted: (data: RunStartedData) => void;
  onNodeStatus: (data: NodeStatusData) => void;
  /** LP-C4: optional — omit to ignore streaming tokens (feature-detect). */
  onNodeTokenDelta?: (data: NodeTokenDeltaData) => void;
  onRunCompleted: (data: RunCompletedData) => void;
  onError?: (error: Event) => void;
}

// ============================================================
// Constants
// ============================================================

const MAX_RETRIES = 3;
const INITIAL_BACKOFF_MS = 1_000;

// ============================================================
// Implementation
// ============================================================

/**
 * Open an SSE connection to a run's event stream.
 *
 * Returns a cleanup handle that closes the EventSource and cancels any
 * pending reconnection timer.
 *
 * @example
 * ```ts
 * const buffers = new Map<string, string>(); // key: `${nodeId}:${messageId}`
 * const { cleanup } = connectToRunStream(runId, {
 *   onCatchup:        (d) => store.applyCatchup(d),
 *   onRunStarted:     (d) => store.setPlannedNodes(d.plannedNodeIds),
 *   onNodeStatus:     (d) => {
 *     if (d.status === 'success') buffers.delete(d.nodeId); // flush
 *     store.updateNode(d);
 *   },
 *   onNodeTokenDelta: (d) => {
 *     const key = `${d.nodeId}:${d.messageId}`;
 *     buffers.set(key, (buffers.get(key) ?? '') + d.delta);
 *     store.setNodePreview(d.nodeId, buffers.get(key)!);
 *   },
 *   onRunCompleted:   (d) => store.finalise(d),
 * });
 *
 * // later — e.g. on unmount
 * cleanup();
 * ```
 */
export function connectToRunStream(
  runId: string,
  callbacks: StreamCallbacks,
): { cleanup: () => void } {
  let retryCount = 0;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let eventSource: EventSource | null = null;
  let disposed = false;

  const url = `${API_BASE_URL}/runs/${encodeURIComponent(runId)}/stream`;

  // ----------------------------------------------------------
  // Helpers
  // ----------------------------------------------------------

  function parseEvent<T>(raw: string): T {
    return JSON.parse(raw) as T;
  }

  function scheduleReconnect(): void {
    if (disposed || retryCount >= MAX_RETRIES) {
      return;
    }
    const delay = INITIAL_BACKOFF_MS * Math.pow(2, retryCount);
    retryCount += 1;
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connect();
    }, delay);
  }

  // ----------------------------------------------------------
  // Connection
  // ----------------------------------------------------------

  function connect(): void {
    if (disposed) return;

    eventSource = new EventSource(url);

    // --- Named event listeners ---

    eventSource.addEventListener('run.catchup', (e: MessageEvent) => {
      retryCount = 0; // successful data → reset backoff
      callbacks.onCatchup(parseEvent<CatchupData>(e.data));
    });

    eventSource.addEventListener('run.started', (e: MessageEvent) => {
      retryCount = 0;
      callbacks.onRunStarted(parseEvent<RunStartedData>(e.data));
    });

    eventSource.addEventListener('node.status', (e: MessageEvent) => {
      retryCount = 0;
      callbacks.onNodeStatus(parseEvent<NodeStatusData>(e.data));
    });

    // LP-C4: streaming tokens — opt-in via onNodeTokenDelta callback.
    eventSource.addEventListener('node.token.delta', (e: MessageEvent) => {
      retryCount = 0;
      if (callbacks.onNodeTokenDelta) {
        callbacks.onNodeTokenDelta(parseEvent<NodeTokenDeltaData>(e.data));
      }
    });

    eventSource.addEventListener('run.completed', (e: MessageEvent) => {
      retryCount = 0;
      callbacks.onRunCompleted(parseEvent<RunCompletedData>(e.data));
      // Terminal event — no further data expected; close cleanly.
      cleanup();
    });

    // --- Error handling & reconnect ---

    eventSource.onerror = (error: Event) => {
      callbacks.onError?.(error);

      // Close the broken source before attempting a new one.
      eventSource?.close();
      eventSource = null;

      scheduleReconnect();
    };
  }

  // ----------------------------------------------------------
  // Cleanup
  // ----------------------------------------------------------

  function cleanup(): void {
    disposed = true;

    if (reconnectTimer !== null) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
  }

  // Kick off the first connection immediately.
  connect();

  return { cleanup };
}
