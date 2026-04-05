import { useCallback } from 'react'
import { useParams } from '@tanstack/react-router'
import { Play, Loader2, History } from 'lucide-react'
import { toast } from 'sonner'

import { AppHeader } from '@/app/layout/app-header'
import { Button } from '@/shared/ui/button'
import { useWorkflowRuns, useWorkflow } from '@/shared/api/queries'
import { useTriggerRun, useCancelRun } from '@/shared/api/mutations'
import { RunListItem } from '@/features/run-history/components/run-list-item'
import type { ExecutionRun } from '@/shared/api/schemas'

/**
 * RunHistoryPage - Shows run history for a workflow
 *
 * Route: /workflows/$workflowId/runs
 */
export function RunHistoryPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId/runs' })
  const { data: workflowData } = useWorkflow(workflowId)
  const { data: runsData, isLoading, isError, error } = useWorkflowRuns(workflowId)
  const triggerRun = useTriggerRun(workflowId)

  const workflowName =
    (workflowData as { data?: { name?: string } })?.data?.name ??
    `Workflow ${workflowId.slice(0, 8)}`

  const runs = ((runsData as { data?: ExecutionRun[] })?.data ?? []) as ExecutionRun[]

  const handleTriggerRun = useCallback(async () => {
    try {
      await triggerRun.mutateAsync({ trigger: 'runWorkflow' })
      toast.success('Workflow run started')
    } catch {
      toast.error('Failed to start workflow run')
    }
  }, [triggerRun])

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader workflowId={workflowId} workflowName={workflowName} />

      <div className="flex-1 overflow-auto">
        {/* Toolbar */}
        <div className="flex items-center justify-between border-b px-6 py-3">
          <div>
            <h2 className="text-lg font-semibold text-foreground">Run History</h2>
            {!isLoading && !isError && (
              <p className="text-xs text-muted-foreground">
                {runs.length} {runs.length === 1 ? 'run' : 'runs'}
              </p>
            )}
          </div>
          <Button
            onClick={handleTriggerRun}
            disabled={triggerRun.isPending}
            size="sm"
            data-testid="trigger-run-btn"
          >
            {triggerRun.isPending ? (
              <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
            ) : (
              <Play className="mr-1.5 h-4 w-4" />
            )}
            Run Workflow
          </Button>
        </div>

        {/* Loading state */}
        {isLoading && (
          <div className="flex flex-col items-center justify-center gap-3 py-20">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            <p className="text-sm text-muted-foreground">Loading runs...</p>
          </div>
        )}

        {/* Error state */}
        {isError && (
          <div className="flex flex-col items-center justify-center gap-3 py-20 px-6">
            <p className="text-sm text-destructive">
              {error instanceof Error ? error.message : 'Failed to load runs'}
            </p>
          </div>
        )}

        {/* Empty state */}
        {!isLoading && !isError && runs.length === 0 && (
          <div
            className="flex flex-col items-center justify-center gap-4 py-20"
            data-testid="empty-state"
          >
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
              <History className="h-6 w-6 text-muted-foreground" />
            </div>
            <div className="text-center">
              <h3 className="text-base font-semibold text-foreground">No runs yet</h3>
              <p className="mt-1 text-sm text-muted-foreground">
                Start your first workflow run to see results here.
              </p>
            </div>
            <Button
              onClick={handleTriggerRun}
              disabled={triggerRun.isPending}
              data-testid="empty-trigger-run-btn"
            >
              {triggerRun.isPending ? (
                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
              ) : (
                <Play className="mr-1.5 h-4 w-4" />
              )}
              Run Workflow
            </Button>
          </div>
        )}

        {/* Run list */}
        {!isLoading && runs.length > 0 && (
          <div className="space-y-2 p-6" data-testid="run-list">
            {runs.map((run) => (
              <CancelableRunItem key={run.id} run={run} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

/**
 * Wrapper that provides per-run cancel functionality via the useCancelRun hook.
 * Hooks must be called at the component level, so each run item gets its own instance.
 */
function CancelableRunItem({ run }: { readonly run: ExecutionRun }) {
  const cancelRun = useCancelRun(run.id)

  const handleCancel = useCallback(async () => {
    try {
      await cancelRun.mutateAsync()
      toast.success('Run cancelled')
    } catch {
      toast.error('Failed to cancel run')
    }
  }, [cancelRun])

  return (
    <RunListItem
      run={run}
      isCancelling={cancelRun.isPending}
      onCancel={handleCancel}
    />
  )
}
