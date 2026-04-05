/**
 * Run store - AiModel-ecs.1
 * Separate Zustand store for execution state (plan sections 7.5-7.6).
 * Never participates in undo/redo history.
 */

import { create } from 'zustand';

import type {
  ExecutionRun,
  NodeRunRecord,
  EdgePayloadSnapshot,
  PortPayload,
} from '@/features/workflows/domain/workflow-types';

// ============================================================
// Run toolbar state
// ============================================================

export type RunToolbarAction =
  | 'runWorkflow'
  | 'runNode'
  | 'runFromHere'
  | 'runUpToHere';

export interface RunToolbarState {
  readonly lastAction: RunToolbarAction | null;
  readonly lastTargetNodeId: string | null;
}

// ============================================================
// Store shape
// ============================================================

export interface RunStoreState {
  /** The currently executing run, if any. */
  readonly activeRun: ExecutionRun | null;
  /** Recent completed runs (most recent first). */
  readonly recentRuns: readonly ExecutionRun[];
  /** Per-node run records for the active run, keyed by nodeId. */
  readonly nodeRunRecords: Readonly<Record<string, NodeRunRecord>>;
  /** Edge payload snapshots for the active run, keyed by edgeId. */
  readonly edgePayloadSnapshots: Readonly<Record<string, EdgePayloadSnapshot>>;
  /** AbortController for the active run — not serializable, excluded from persistence. */
  readonly abortController: AbortController | null;
  /** Run toolbar state. */
  readonly toolbar: RunToolbarState;
  /** Last execution scope for partial reruns. */
  readonly lastExecutionScope: {
    readonly trigger: ExecutionRun['trigger'];
    readonly targetNodeId?: string;
    readonly plannedNodeIds: readonly string[];
  } | null;
}

export interface RunStoreActions {
  /** Start a new run. Sets the active run and initializes node records. */
  readonly startRun: (run: ExecutionRun, abortController: AbortController) => void;

  /** Mark a node as running. */
  readonly markNodeRunning: (nodeId: string) => void;

  /** Record a node that completed successfully. */
  readonly writeSucceededNode: (
    nodeId: string,
    outputPayloads: Readonly<Record<string, PortPayload>>,
    durationMs: number,
  ) => void;

  /** Record a node that errored. */
  readonly writeErroredNode: (
    nodeId: string,
    errorMessage: string,
    durationMs: number,
  ) => void;

  /** Record a node that was skipped. */
  readonly writeSkippedNode: (
    nodeId: string,
    skipReason: NonNullable<NodeRunRecord['skipReason']>,
    blockedByNodeIds?: readonly string[],
  ) => void;

  /** Record a node that was cancelled. */
  readonly writeCancelledNode: (nodeId: string) => void;

  /** Mark all pending nodes as cancelled (on run abort). */
  readonly markPendingNodesCancelled: () => void;

  /** Complete the active run with a final status and reason. */
  readonly completeRun: (
    status: ExecutionRun['status'],
    terminationReason: ExecutionRun['terminationReason'],
  ) => void;

  /** Complete the active run by deriving status from node states. */
  readonly completeRunFromNodeStates: () => void;

  /** Write an edge payload snapshot. */
  readonly writeEdgePayloadSnapshot: (snapshot: EdgePayloadSnapshot) => void;

  /** Set toolbar state. */
  readonly setToolbar: (partial: Partial<RunToolbarState>) => void;

  /** Set last execution scope. */
  readonly setLastExecutionScope: (scope: RunStoreState['lastExecutionScope']) => void;

  /** Clear the active run (e.g. after UI dismissal). */
  readonly clearActiveRun: () => void;

  /** Reset entire store (e.g. when switching workflows). */
  readonly resetRunStore: () => void;
}

export type RunStore = RunStoreState & RunStoreActions;

const MAX_RECENT_RUNS = 20;

const initialToolbar: RunToolbarState = {
  lastAction: null,
  lastTargetNodeId: null,
};

function now(): string {
  return new Date().toISOString();
}

export const useRunStore = create<RunStore>((set, get) => ({
  activeRun: null,
  recentRuns: [],
  nodeRunRecords: {},
  edgePayloadSnapshots: {},
  abortController: null,
  toolbar: initialToolbar,
  lastExecutionScope: null,

  startRun: (run, abortController) => {
    // Initialize node run records for all planned nodes
    const records: Record<string, NodeRunRecord> = {};
    for (const nodeId of run.plannedNodeIds) {
      records[nodeId] = {
        runId: run.id,
        nodeId,
        status: 'pending',
        inputPayloads: {},
        outputPayloads: {},
        usedCache: false,
      };
    }

    set({
      activeRun: { ...run, status: 'running', startedAt: now() },
      nodeRunRecords: records,
      edgePayloadSnapshots: {},
      abortController,
      lastExecutionScope: {
        trigger: run.trigger,
        targetNodeId: run.targetNodeId,
        plannedNodeIds: run.plannedNodeIds,
      },
      toolbar: {
        lastAction: run.trigger,
        lastTargetNodeId: run.targetNodeId ?? null,
      },
    });
  },

  markNodeRunning: (nodeId) => {
    set((state) => {
      const existing = state.nodeRunRecords[nodeId];
      if (!existing) return state;
      return {
        nodeRunRecords: {
          ...state.nodeRunRecords,
          [nodeId]: {
            ...existing,
            status: 'running',
            startedAt: now(),
          },
        },
      };
    });
  },

  writeSucceededNode: (nodeId, outputPayloads, durationMs) => {
    set((state) => {
      const existing = state.nodeRunRecords[nodeId];
      if (!existing) return state;
      return {
        nodeRunRecords: {
          ...state.nodeRunRecords,
          [nodeId]: {
            ...existing,
            status: 'success',
            completedAt: now(),
            durationMs,
            outputPayloads,
          },
        },
      };
    });
  },

  writeErroredNode: (nodeId, errorMessage, durationMs) => {
    set((state) => {
      const existing = state.nodeRunRecords[nodeId];
      if (!existing) return state;
      return {
        nodeRunRecords: {
          ...state.nodeRunRecords,
          [nodeId]: {
            ...existing,
            status: 'error',
            completedAt: now(),
            durationMs,
            errorMessage,
          },
        },
      };
    });
  },

  writeSkippedNode: (nodeId, skipReason, blockedByNodeIds) => {
    set((state) => {
      const existing = state.nodeRunRecords[nodeId];
      if (!existing) return state;
      return {
        nodeRunRecords: {
          ...state.nodeRunRecords,
          [nodeId]: {
            ...existing,
            status: 'skipped',
            completedAt: now(),
            skipReason,
            blockedByNodeIds,
          },
        },
      };
    });
  },

  writeCancelledNode: (nodeId) => {
    set((state) => {
      const existing = state.nodeRunRecords[nodeId];
      if (!existing) return state;
      return {
        nodeRunRecords: {
          ...state.nodeRunRecords,
          [nodeId]: {
            ...existing,
            status: 'cancelled',
            completedAt: now(),
          },
        },
      };
    });
  },

  markPendingNodesCancelled: () => {
    set((state) => {
      const updated = { ...state.nodeRunRecords };
      const cancelledAt = now();
      for (const [nodeId, record] of Object.entries(updated)) {
        if (record.status === 'pending') {
          updated[nodeId] = {
            ...record,
            status: 'cancelled',
            completedAt: cancelledAt,
          };
        }
      }
      return { nodeRunRecords: updated };
    });
  },

  completeRun: (status, terminationReason) => {
    set((state) => {
      if (!state.activeRun) return state;
      const completedRun: ExecutionRun = {
        ...state.activeRun,
        status,
        completedAt: now(),
        terminationReason,
      };
      return {
        activeRun: completedRun,
        recentRuns: [
          completedRun,
          ...state.recentRuns,
        ].slice(0, MAX_RECENT_RUNS),
        abortController: null,
      };
    });
  },

  completeRunFromNodeStates: () => {
    const state = get();
    if (!state.activeRun) return;

    const records = Object.values(state.nodeRunRecords);
    const hasError = records.some((r) => r.status === 'error');
    const hasCancelled = records.some((r) => r.status === 'cancelled');
    const hasAwaitingReview = records.some((r) => r.status === 'awaitingReview');

    let status: ExecutionRun['status'];
    let terminationReason: ExecutionRun['terminationReason'];

    if (hasError) {
      status = 'error';
      terminationReason = 'nodeError';
    } else if (hasCancelled) {
      status = 'cancelled';
      terminationReason = 'userCancelled';
    } else if (hasAwaitingReview) {
      status = 'awaitingReview';
      terminationReason = undefined;
    } else {
      status = 'success';
      terminationReason = 'completed';
    }

    state.completeRun(status, terminationReason);
  },

  writeEdgePayloadSnapshot: (snapshot) => {
    set((state) => ({
      edgePayloadSnapshots: {
        ...state.edgePayloadSnapshots,
        [snapshot.edgeId]: snapshot,
      },
    }));
  },

  setToolbar: (partial) => {
    set((state) => ({
      toolbar: { ...state.toolbar, ...partial },
    }));
  },

  setLastExecutionScope: (scope) => {
    set({ lastExecutionScope: scope });
  },

  clearActiveRun: () => {
    set({ activeRun: null, abortController: null });
  },

  resetRunStore: () => {
    set({
      activeRun: null,
      recentRuns: [],
      nodeRunRecords: {},
      edgePayloadSnapshots: {},
      abortController: null,
      toolbar: initialToolbar,
      lastExecutionScope: null,
    });
  },
}));
