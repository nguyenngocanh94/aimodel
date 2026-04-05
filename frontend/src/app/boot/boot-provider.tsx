/**
 * BootProvider - AiModel-e0x.3
 * Simplified boot state: no longer manages Dexie/IndexedDB persistence.
 * The backend now handles all persistence.
 */

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

// ============================================================
// Boot state
// ============================================================

export type BootState =
  | { status: 'booting' }
  | { status: 'ready'; initialWorkflowId?: string }
  | { status: 'degraded'; reason: string }
  | { status: 'fatal'; message: string }

const BootContext = createContext<BootState>({ status: 'booting' })

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
    // Ignore — localStorage may be unavailable
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
// BootProvider component
// ============================================================

interface BootProviderProps {
  readonly children: ReactNode
}

export function BootProvider({ children }: BootProviderProps) {
  const [bootState, setBootState] = useState<BootState>({ status: 'booting' })

  useEffect(() => {
    try {
      // Boot is now instant — no persistence layer to initialise.
      // The workflow store can be hydrated later via backend API calls.
      const lastId = getLastOpenedWorkflowId()
      setBootState({ status: 'ready', initialWorkflowId: lastId ?? undefined })
    } catch (error) {
      setBootState({
        status: 'fatal',
        message: error instanceof Error ? error.message : 'Boot failed',
      })
    }
  }, [])

  return (
    <BootContext.Provider value={bootState}>
      {children}
    </BootContext.Provider>
  )
}
