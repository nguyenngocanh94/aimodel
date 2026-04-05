/**
 * Run store selectors - AiModel-ecs.1
 */

import type { RunStoreState } from './run-store';

/** Select the active run or null. */
export const selectActiveRun = (state: RunStoreState) => state.activeRun;

/** Select whether a run is currently in progress. */
export const selectIsRunning = (state: RunStoreState) =>
  state.activeRun?.status === 'running';

/** Select recent completed runs. */
export const selectRecentRuns = (state: RunStoreState) => state.recentRuns;

/** Select all node run records for the active run. */
export const selectNodeRunRecords = (state: RunStoreState) =>
  state.nodeRunRecords;

/** Select a specific node's run record. */
export const selectNodeRunRecord = (nodeId: string) => (state: RunStoreState) =>
  state.nodeRunRecords[nodeId] ?? null;

/** Select edge payload snapshots. */
export const selectEdgePayloadSnapshots = (state: RunStoreState) =>
  state.edgePayloadSnapshots;

/** Select a specific edge's payload snapshot. */
export const selectEdgePayloadSnapshot =
  (edgeId: string) => (state: RunStoreState) =>
    state.edgePayloadSnapshots[edgeId] ?? null;

/** Select the toolbar state. */
export const selectToolbar = (state: RunStoreState) => state.toolbar;

/** Select the last execution scope. */
export const selectLastExecutionScope = (state: RunStoreState) =>
  state.lastExecutionScope;

/** Select whether the active run can be cancelled. */
export const selectCanCancel = (state: RunStoreState) =>
  state.activeRun?.status === 'running' && state.abortController !== null;

/** Count node records by status for the active run. */
export const selectNodeStatusCounts = (state: RunStoreState) => {
  const records = Object.values(state.nodeRunRecords);
  return {
    pending: records.filter((r) => r.status === 'pending').length,
    running: records.filter((r) => r.status === 'running').length,
    success: records.filter((r) => r.status === 'success').length,
    error: records.filter((r) => r.status === 'error').length,
    skipped: records.filter((r) => r.status === 'skipped').length,
    cancelled: records.filter((r) => r.status === 'cancelled').length,
    awaitingReview: records.filter((r) => r.status === 'awaitingReview').length,
    total: records.length,
  };
};

/** Select the elapsed time of the active run in ms, or null if no run. */
export const selectElapsedMs = (state: RunStoreState): number | null => {
  if (!state.activeRun) return null;
  const start = new Date(state.activeRun.startedAt).getTime();
  const end = state.activeRun.completedAt
    ? new Date(state.activeRun.completedAt).getTime()
    : Date.now();
  return end - start;
};
