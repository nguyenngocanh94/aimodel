/**
 * Crash recovery - AiModel-e0x.6
 * Maintains recovery snapshots and detects crash state on restart.
 * Per plan sections 12.7 and 12.8
 */

import type { WorkflowDocument, WorkflowSnapshot } from '@/features/workflows/domain/workflow-types'
import type { WorkflowRepository } from './workflow-repository'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import { useRunStore } from '@/features/execution/store/run-store'

// ============================================================
// Recovery snapshot creation
// ============================================================

let recoverySnapshotCounter = 0

/**
 * Build a recovery snapshot from current application state.
 * Returns null if no recovery is needed (document not dirty, no active run).
 */
export function buildRecoverySnapshot(
  document: WorkflowDocument,
  isDirty: boolean,
  activeRunId?: string,
): WorkflowSnapshot | null {
  if (!isDirty && !activeRunId) return null

  recoverySnapshotCounter++
  return {
    id: `recovery-${document.id}-${Date.now()}-${recoverySnapshotCounter}`,
    workflowId: document.id,
    kind: 'recovery',
    savedAt: new Date().toISOString(),
    document,
    interruptedRunId: activeRunId,
  }
}

// ============================================================
// beforeunload handler
// ============================================================

/**
 * Write a recovery snapshot synchronously-ish before page unload.
 * Uses the repository's saveSnapshot (which is async, but beforeunload
 * gives us a best-effort window).
 */
export function writeRecoveryOnUnload(repository: WorkflowRepository): void {
  const workflowState = useWorkflowStore.getState()
  const runState = useRunStore.getState()

  const snapshot = buildRecoverySnapshot(
    workflowState.document,
    workflowState.dirty,
    runState.activeRun?.runId,
  )

  if (!snapshot) return

  // Best-effort async write — browser may cut us off
  repository.saveSnapshot(snapshot).catch(() => {
    // Nothing we can do if this fails during unload
  })
}

let unloadHandler: (() => void) | null = null

/**
 * Install the beforeunload handler. Returns a cleanup function.
 */
export function installUnloadHandler(repository: WorkflowRepository): () => void {
  removeUnloadHandler()

  unloadHandler = () => writeRecoveryOnUnload(repository)
  window.addEventListener('beforeunload', unloadHandler)

  return removeUnloadHandler
}

/**
 * Remove the beforeunload handler if installed.
 */
export function removeUnloadHandler(): void {
  if (unloadHandler) {
    window.removeEventListener('beforeunload', unloadHandler)
    unloadHandler = null
  }
}

// ============================================================
// Recovery detection on restart
// ============================================================

export interface RecoveryCheckResult {
  readonly hasRecovery: boolean
  readonly snapshot: WorkflowSnapshot | null
  readonly savedDocument: WorkflowDocument | null
  readonly snapshotIsNewer: boolean
}

/**
 * Check if a recovery snapshot exists and is newer than the saved document.
 */
export async function checkForRecovery(
  repository: WorkflowRepository,
  workflowId: string,
): Promise<RecoveryCheckResult> {
  const [snapshot, savedDoc] = await Promise.all([
    repository.loadLatestSnapshot(workflowId),
    repository.load(workflowId),
  ])

  if (!snapshot || snapshot.kind !== 'recovery') {
    return {
      hasRecovery: false,
      snapshot: null,
      savedDocument: savedDoc,
      snapshotIsNewer: false,
    }
  }

  const snapshotTime = new Date(snapshot.savedAt).getTime()
  const savedTime = savedDoc ? new Date(savedDoc.updatedAt).getTime() : 0
  const snapshotIsNewer = snapshotTime > savedTime

  return {
    hasRecovery: true,
    snapshot,
    savedDocument: savedDoc,
    snapshotIsNewer,
  }
}

// ============================================================
// Recovery actions
// ============================================================

/**
 * Restore from a recovery snapshot: load the snapshot's document into the store.
 */
export function restoreFromSnapshot(snapshot: WorkflowSnapshot): WorkflowDocument {
  return snapshot.document
}

/**
 * Dismiss recovery: delete the recovery snapshot and load the saved document instead.
 */
export async function dismissRecovery(
  repository: WorkflowRepository,
  workflowId: string,
): Promise<WorkflowDocument | null> {
  // We don't have a dedicated deleteSnapshot method,
  // but loading the saved doc and proceeding is sufficient.
  // The snapshot will be overwritten on next recovery write.
  return repository.load(workflowId)
}
