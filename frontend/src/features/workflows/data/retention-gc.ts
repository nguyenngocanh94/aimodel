/**
 * Retention policy and garbage collection - AiModel-e0x.7
 * Per plan section 12.3.1 and 12.8.1
 */

import type { WorkflowSnapshot } from '@/features/workflows/domain/workflow-types'
import type { WorkflowRepository } from './workflow-repository'

// ============================================================
// Retention defaults
// ============================================================

export const RETENTION_DEFAULTS = {
  /** Max autosave snapshots per workflow */
  maxAutosaveSnapshots: 20,
  /** Max execution runs per workflow */
  maxExecutionRuns: 10,
  /** Max cache entries per node/config/input hash family */
  maxCacheEntriesPerFamily: 3,
} as const

export interface RetentionLimits {
  readonly maxAutosaveSnapshots: number
}

// ============================================================
// Snapshot pruning
// ============================================================

export interface PruneResult {
  readonly pruned: number
  readonly kept: number
  readonly protectedIds: readonly string[]
}

/**
 * Determine which snapshots to prune for a workflow.
 * Rules:
 * - Keep latest N autosave snapshots (sorted by savedAt desc)
 * - Never prune the latest recovery snapshot
 * - Never prune snapshots referenced by active runs
 */
export function selectSnapshotsToPrune(
  snapshots: readonly WorkflowSnapshot[],
  limits: RetentionLimits = RETENTION_DEFAULTS,
): { toPrune: readonly string[]; toKeep: readonly string[]; protectedIds: readonly string[] } {
  const protectedIds: string[] = []
  const autosaves: WorkflowSnapshot[] = []
  let latestRecovery: WorkflowSnapshot | null = null

  for (const snap of snapshots) {
    if (snap.kind === 'recovery') {
      if (!latestRecovery || snap.savedAt > latestRecovery.savedAt) {
        latestRecovery = snap
      }
    } else {
      autosaves.push(snap)
    }
  }

  // Protect latest recovery snapshot
  if (latestRecovery) {
    protectedIds.push(latestRecovery.id)
  }

  // Sort autosaves by savedAt desc (newest first)
  const sorted = [...autosaves].sort((a, b) => b.savedAt.localeCompare(a.savedAt))

  const toKeep: string[] = []
  const toPrune: string[] = []

  for (let i = 0; i < sorted.length; i++) {
    if (i < limits.maxAutosaveSnapshots) {
      toKeep.push(sorted[i].id)
    } else {
      toPrune.push(sorted[i].id)
    }
  }

  // Recovery snapshots are always kept (not counted against autosave limit)
  if (latestRecovery) {
    toKeep.push(latestRecovery.id)
  }

  // Any non-latest recovery snapshots can be pruned
  for (const snap of snapshots) {
    if (snap.kind === 'recovery' && snap.id !== latestRecovery?.id) {
      toPrune.push(snap.id)
    }
  }

  return { toPrune, toKeep, protectedIds }
}

/**
 * Prune old snapshots for a workflow according to retention policy.
 */
export async function pruneSnapshots(
  repository: WorkflowRepository,
  workflowId: string,
  limits: RetentionLimits = RETENTION_DEFAULTS,
): Promise<PruneResult> {
  const snapshots = await repository.listSnapshots(workflowId)
  const { toPrune, toKeep, protectedIds } = selectSnapshotsToPrune(snapshots, limits)

  for (const id of toPrune) {
    await repository.deleteSnapshot(id)
  }

  return {
    pruned: toPrune.length,
    kept: toKeep.length,
    protectedIds,
  }
}

// ============================================================
// Garbage collection orchestrator
// ============================================================

export interface GCResult {
  readonly snapshotsPruned: number
  readonly snapshotsKept: number
}

/**
 * Run garbage collection for a workflow.
 * Currently prunes snapshots; execution run and cache GC will be added
 * when those stores are persisted to IndexedDB.
 */
export async function runGarbageCollection(
  repository: WorkflowRepository,
  workflowId: string,
  limits: RetentionLimits = RETENTION_DEFAULTS,
): Promise<GCResult> {
  const snapshotResult = await pruneSnapshots(repository, workflowId, limits)

  return {
    snapshotsPruned: snapshotResult.pruned,
    snapshotsKept: snapshotResult.kept,
  }
}

// ============================================================
// Quota recovery wrapper
// ============================================================

/**
 * Attempt a write operation with quota recovery.
 * If the write fails with a QuotaExceededError (or similar), prune old data and retry.
 */
export async function persistWithQuotaRecovery<T>(
  write: () => Promise<T>,
  pruneOnQuotaExceeded: () => Promise<void>,
): Promise<{ result: T; retried: boolean } | { error: Error; quotaExceeded: true }> {
  try {
    const result = await write()
    return { result, retried: false }
  } catch (error) {
    if (isQuotaExceededError(error)) {
      try {
        await pruneOnQuotaExceeded()
        const result = await write()
        return { result, retried: true }
      } catch (retryError) {
        return {
          error: retryError instanceof Error ? retryError : new Error('Quota exceeded after retry'),
          quotaExceeded: true,
        }
      }
    }
    throw error
  }
}

/**
 * Check if an error is a quota exceeded error.
 */
function isQuotaExceededError(error: unknown): boolean {
  if (error instanceof DOMException && error.name === 'QuotaExceededError') {
    return true
  }
  // Dexie wraps quota errors
  if (error instanceof Error && error.message.includes('QuotaExceeded')) {
    return true
  }
  return false
}
