/**
 * PersistenceProvider - AiModel-e0x.3
 * Opens the workflow repository and exposes it via React context.
 * Per plan section 7.3.2
 */

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import {
  openWorkflowRepository,
  type WorkflowRepository,
  type PersistenceMode,
} from '@/features/workflows/data/workflow-repository'

export interface PersistenceState {
  readonly repository: WorkflowRepository | null
  readonly mode: PersistenceMode | null
  readonly reason?: string
  readonly isReady: boolean
}

const initialState: PersistenceState = {
  repository: null,
  mode: null,
  isReady: false,
}

const PersistenceContext = createContext<PersistenceState>(initialState)

export function usePersistence(): PersistenceState {
  return useContext(PersistenceContext)
}

interface PersistenceProviderProps {
  readonly children: ReactNode
  /** Inject a repository for testing. Skips openWorkflowRepository when provided. */
  readonly testRepository?: WorkflowRepository
}

export function PersistenceProvider({ children, testRepository }: PersistenceProviderProps) {
  const [state, setState] = useState<PersistenceState>(() => {
    if (testRepository) {
      return {
        repository: testRepository,
        mode: testRepository.mode,
        isReady: true,
      }
    }
    return initialState
  })

  useEffect(() => {
    if (testRepository) return

    let cancelled = false
    openWorkflowRepository()
      .then((result) => {
        if (cancelled) return
        setState({
          repository: result.repository,
          mode: result.mode,
          reason: result.reason,
          isReady: true,
        })
      })
      .catch((error) => {
        if (cancelled) return
        setState({
          repository: null,
          mode: 'unavailable',
          reason: error instanceof Error ? error.message : 'Unknown error',
          isReady: true,
        })
      })
    return () => {
      cancelled = true
    }
  }, [testRepository])

  return (
    <PersistenceContext.Provider value={state}>
      {children}
    </PersistenceContext.Provider>
  )
}
