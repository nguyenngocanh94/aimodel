import { ReactFlowProvider } from '@xyflow/react'
import { type ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { BootProvider } from './boot/boot-provider'

/**
 * QueryClient instance for TanStack Query
 * Configured per plan section 8.2
 */
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutes
      retry: 2,
      refetchOnWindowFocus: false,
    },
  },
})

interface AppProvidersProps {
  children: ReactNode
}

/**
 * AppProviders - Composes all top-level providers for the application.
 * Per plan section 7.3.2 and 8.2:
 *   QueryClientProvider → BootProvider → ReactFlowProvider
 */
export function AppProviders({ children }: AppProvidersProps) {
  return (
    <QueryClientProvider client={queryClient}>
      <BootProvider>
        <ReactFlowProvider>{children}</ReactFlowProvider>
      </BootProvider>
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}
