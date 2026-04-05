import { ReactFlowProvider } from '@xyflow/react'
import { type ReactNode } from 'react'
import { PersistenceProvider } from './boot/persistence-provider'
import { BootProvider } from './boot/boot-provider'

interface AppProvidersProps {
  children: ReactNode
}

/**
 * AppProviders - Composes all top-level providers for the application.
 * Per plan section 7.3.2:
 *   PersistenceProvider → BootProvider → ReactFlowProvider
 */
export function AppProviders({ children }: AppProvidersProps) {
  return (
    <PersistenceProvider>
      <BootProvider>
        <ReactFlowProvider>{children}</ReactFlowProvider>
      </BootProvider>
    </PersistenceProvider>
  )
}
