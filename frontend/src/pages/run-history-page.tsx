import { useParams } from '@tanstack/react-router'
import { Play } from 'lucide-react'
import { toast } from 'sonner'
import { AppHeader } from '@/app/layout/app-header'
import { Button } from '@/shared/ui/button'
import { useWorkflowRuns } from '@/shared/api/queries'
import { useTriggerRun, useCancelRun } from '@/shared/api/mutations'
import { RunListItem } from '@/features/run-history/components/run-list-item'

interface RunData {
  readonly id: string
  readonly status: string
  readonly trigger: string
  readonly targetNodeId?: string
  readonly startedAt?: string
  readonly completedAt?: string
  readonly summary?: {
    readonly successCount?: number
    readonly errorCount?: number
    readonly skippedCount?: number
    readonly totalCount?: number
  }
}

export function RunHistoryPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId/runs' })
  const { data, isLoading, error } = useWorkflowRuns(workflowId)
  const triggerRun = useTriggerRun(workflowId)

  const runs = ((data as { data?: RunData[] })?.data ?? []) as RunData[]

  const handleTriggerRun = async () => {
    try {
      await triggerRun.mutateAsync({ trigger: 'runWorkflow' })
      toast.success('Run triggered')
    } catch {
      toast.error('Failed to trigger run')
    }
  }

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader workflowId={workflowId} workflowName={`Workflow ${workflowId.slice(0, 8)}`} />

      <div className="flex items-center justify-between border-b px-6 py-3">
        <h2 className="text-sm font-semibold">Run History</h2>
        <Button size="sm" onClick={handleTriggerRun} disabled={triggerRun.isPending}>
          <Play className="mr-1 h-3.5 w-3.5" />
          Run Workflow
        </Button>
      </div>

      <div className="flex-1 overflow-auto p-6">
        {isLoading && (
          <div className="flex items-center justify-center py-20 text-sm text-muted-foreground">
            Loading runs...
          </div>
        )}

        {error && (
          <div className="flex items-center justify-center py-20 text-sm text-destructive">
            Failed to load runs
          </div>
        )}

        {!isLoading && !error && runs.length === 0 && (
          <div className="flex flex-col items-center justify-center gap-4 py-20">
            <h3 className="text-lg font-semibold">No runs yet</h3>
            <p className="text-sm text-muted-foreground">Run this workflow to see execution history.</p>
            <Button onClick={handleTriggerRun} disabled={triggerRun.isPending}>
              <Play className="mr-1 h-4 w-4" />
              Run Workflow
            </Button>
          </div>
        )}

        {runs.length > 0 && (
          <div className="space-y-2">
            {runs.map((run) => (
              <CancelableRunItem key={run.id} run={run} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

function CancelableRunItem({ run }: { readonly run: RunData }) {
  const cancelRun = useCancelRun(run.id)

  const handleCancel = async () => {
    try {
      await cancelRun.mutateAsync()
      toast.info('Run cancelled')
    } catch {
      toast.error('Failed to cancel run')
    }
  }

  return (
    <RunListItem
      id={run.id}
      status={run.status}
      trigger={run.trigger}
      targetNodeId={run.targetNodeId}
      startedAt={run.startedAt}
      completedAt={run.completedAt}
      summary={run.summary}
      onCancel={handleCancel}
    />
  )
}
