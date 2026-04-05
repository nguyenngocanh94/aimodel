import { useMutation, useQueryClient } from '@tanstack/react-query'
import { post, put, del } from './client'
import { queryKeys } from './queries'

// ============================================================
// Workflow Mutations
// ============================================================

/**
 * Create a new workflow
 */
export function useCreateWorkflow() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: { name: string; description?: string; document?: unknown }) =>
      post<{ data: unknown }>('/workflows', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.workflows.all })
    },
  })
}

/**
 * Save/update a workflow
 */
export function useSaveWorkflow(workflowId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: { name?: string; description?: string; document?: unknown }) =>
      put<{ data: unknown }>(`/workflows/${workflowId}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.workflows.detail(workflowId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.workflows.all })
    },
  })
}

/**
 * Delete a workflow
 */
export function useDeleteWorkflow() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (workflowId: string) =>
      del<void>(`/workflows/${workflowId}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.workflows.all })
    },
  })
}

// ============================================================
// Run Mutations
// ============================================================

/**
 * Trigger a workflow run
 */
export function useTriggerRun(workflowId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: {
      trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere'
      targetNodeId?: string
    }) => post<{ data: unknown }>(`/workflows/${workflowId}/runs`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.workflows.runs(workflowId) })
    },
  })
}

/**
 * Submit a review decision
 */
export function useSubmitReview(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (data: {
      nodeId: string
      decision: 'approve' | 'reject'
      notes?: string
    }) => post<{ message: string }>(`/runs/${runId}/review`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.runs.detail(runId) })
    },
  })
}

/**
 * Cancel a running run
 */
export function useCancelRun(runId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => post<{ data: unknown }>(`/runs/${runId}/cancel`, {}),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.runs.detail(runId) })
    },
  })
}
