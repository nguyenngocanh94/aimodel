import { useQuery } from '@tanstack/react-query'
import { get } from './client'

// ============================================================
// Query Keys
// ============================================================

export const queryKeys = {
  workflows: {
    all: ['workflows'] as const,
    list: (params?: { search?: string; tags?: string[] }) =>
      ['workflows', 'list', params] as const,
    detail: (workflowId: string) => ['workflows', workflowId] as const,
    runs: (workflowId: string) => ['workflows', workflowId, 'runs'] as const,
  },
  runs: {
    detail: (runId: string) => ['runs', runId] as const,
  },
} as const

// ============================================================
// Query Hooks
// ============================================================

/**
 * Fetch paginated workflow list
 */
export function useWorkflows(params?: { search?: string; tags?: string[] }) {
  const searchParams = new URLSearchParams()
  if (params?.search) searchParams.set('search', params.search)
  if (params?.tags?.length) searchParams.set('tags', params.tags.join(','))

  const queryString = searchParams.toString()
  const endpoint = `/workflows${queryString ? `?${queryString}` : ''}`

  return useQuery({
    queryKey: queryKeys.workflows.list(params),
    queryFn: () => get<{ data: unknown[] }>(endpoint),
  })
}

/**
 * Fetch a single workflow with its document
 */
export function useWorkflow(workflowId: string) {
  return useQuery({
    queryKey: queryKeys.workflows.detail(workflowId),
    queryFn: () => get<{ data: unknown }>(`/workflows/${workflowId}`),
    enabled: !!workflowId,
  })
}

/**
 * Fetch paginated run list for a workflow
 */
export function useWorkflowRuns(workflowId: string) {
  return useQuery({
    queryKey: queryKeys.workflows.runs(workflowId),
    queryFn: () => get<{ data: unknown[] }>(`/workflows/${workflowId}/runs`),
    enabled: !!workflowId,
  })
}

/**
 * Fetch a single run with its nodeRunRecords
 */
export function useRun(runId: string) {
  return useQuery({
    queryKey: queryKeys.runs.detail(runId),
    queryFn: () => get<{ data: unknown }>(`/runs/${runId}`),
    enabled: !!runId,
  })
}
