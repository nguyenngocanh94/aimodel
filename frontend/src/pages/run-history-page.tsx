import { useParams } from '@tanstack/react-router'
import { AppHeader } from '@/app/layout/app-header'

/**
 * RunHistoryPage - Shows run history for a workflow
 *
 * Route: /workflows/$workflowId/runs
 *
 * TODO: AiModel-612 - Build full run history list
 */
export function RunHistoryPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId/runs' })

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader
        workflowId={workflowId}
        workflowName={`Workflow ${workflowId.slice(0, 8)}`}
      />
      <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6">
        <h2 className="text-xl font-semibold text-foreground">Run History</h2>
        <p className="text-muted-foreground">Run history for workflow: {workflowId}</p>
        <p className="text-xs text-muted-foreground">(Placeholder — will be built in AiModel-612)</p>
      </div>
    </div>
  )
}
