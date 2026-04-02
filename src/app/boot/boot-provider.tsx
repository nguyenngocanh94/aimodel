/**
 * BootProvider - AiModel-e0x.3
 * Runs the boot state machine: checkingPersistence → checkingRecovery → ready|degraded|fatal.
 * Hydrates workflowStore with the last-opened document.
 * Per plan sections 7.3.3 and 7.3.4
 */

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { usePersistence, type PersistenceState } from './persistence-provider'
import type { WorkflowRepository, PersistenceMode } from '@/features/workflows/data/workflow-repository'
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

// ============================================================
// Boot state
// ============================================================

export type BootState =
  | { status: 'checkingPersistence' }
  | { status: 'checkingRecovery'; repository: WorkflowRepository }
  | { status: 'ready'; repository: WorkflowRepository; initialWorkflowId?: string }
  | { status: 'degraded'; repository: WorkflowRepository; reason: string }
  | { status: 'fatal'; message: string }

const BootContext = createContext<BootState>({ status: 'checkingPersistence' })

export function useBootState(): BootState {
  return useContext(BootContext)
}

// ============================================================
// localStorage helpers for last-opened workflow
// ============================================================

const LAST_WORKFLOW_KEY = 'aimodel:lastOpenedWorkflowId'

export function getLastOpenedWorkflowId(): string | null {
  try {
    return localStorage.getItem(LAST_WORKFLOW_KEY)
  } catch {
    return null
  }
}

export function setLastOpenedWorkflowId(id: string): void {
  try {
    localStorage.setItem(LAST_WORKFLOW_KEY, id)
  } catch {
    // Ignore — localStorage may be unavailable in degraded mode
  }
}

export function clearLastOpenedWorkflowId(): void {
  try {
    localStorage.removeItem(LAST_WORKFLOW_KEY)
  } catch {
    // Ignore
  }
}

// ============================================================
// Boot sequence (pure async logic, testable)
// ============================================================

export interface BootSequenceArgs {
  readonly repository: WorkflowRepository
  readonly mode: PersistenceMode
  readonly reason?: string
  readonly hydrateDocument: (doc: WorkflowDocument) => void
  readonly getLastWorkflowId: () => string | null
  readonly onCheckingRecovery?: () => void
}

/**
 * Run the boot sequence. Returns the final boot state.
 * This is extracted as a pure async function for testability.
 */
export async function runBootSequence(args: BootSequenceArgs): Promise<BootState> {
  const { repository, mode, reason, hydrateDocument, getLastWorkflowId, onCheckingRecovery } = args
  const isDegraded = mode === 'memory-fallback'

  onCheckingRecovery?.()

  try {
    const lastId = getLastWorkflowId()

    if (lastId) {
      const doc = await repository.load(lastId)
      if (doc) {
        hydrateDocument(doc)
        if (isDegraded) {
          return { status: 'degraded', repository, reason: reason ?? 'Using in-memory storage' }
        }
        return { status: 'ready', repository, initialWorkflowId: lastId }
      }
    }

    // No last workflow or not found → empty state
    if (isDegraded) {
      return { status: 'degraded', repository, reason: reason ?? 'Using in-memory storage' }
    }
    return { status: 'ready', repository }
  } catch (error) {
    return {
      status: 'fatal',
      message: error instanceof Error ? error.message : 'Boot failed',
    }
  }
}

// ============================================================
// BootProvider component
// ============================================================

interface BootProviderProps {
  readonly children: ReactNode
}

export function BootProvider({ children }: BootProviderProps) {
  const persistence = usePersistence()
  const [bootState, setBootState] = useState<BootState>({ status: 'checkingPersistence' })

  useEffect(() => {
    if (!persistence.isReady) return

    const repository = persistence.repository
    if (!repository) {
      setBootState({
        status: 'fatal',
        message: persistence.reason ?? 'Persistence unavailable',
      })
      return
    }

    runBootSequence({
      repository,
      mode: persistence.mode!,
      reason: persistence.reason,
      hydrateDocument: (doc) => useWorkflowStore.getState().loadDocument(doc),
      getLastWorkflowId: getLastOpenedWorkflowId,
      onCheckingRecovery: () =>
        setBootState({ status: 'checkingRecovery', repository }),
    }).then(setBootState)
  }, [persistence.isReady, persistence.repository, persistence.mode, persistence.reason])

  return (
    <BootContext.Provider value={bootState}>
      {children}
    </BootContext.Provider>
  )
}
