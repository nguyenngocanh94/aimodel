import { useParams, useNavigate } from '@tanstack/react-router'

/**
 * RunHistoryPage - Shows run history for a workflow
 * 
 * Per plan section 9.2:
 * - Lists all runs for a workflow
 * - Shows status (success, error, running, awaitingReview)
 * - Shows trigger type, start time, duration
 * - Actions: view details, cancel (if running), retry
 * 
 * Route: /workflows/$workflowId/runs
 * 
 * TODO: AiModel-615 - Implement runs list and editor/runs tab navigation
 */
export function RunHistoryPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId/runs' })
  const navigate = useNavigate()

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      {/* Header with navigation */}
      <div className="flex items-center justify-between border-b px-4 py-2">
        <div className="flex items-center gap-4">
          <button
            onClick={() => navigate({ to: '/workflows' })}
            className="text-sm text-muted-foreground hover:text-foreground"
          >
            ← Workflows
          </button>
          <h1 className="text-sm font-medium text-foreground">
            {workflowId}
          </h1>
          <span className="text-xs text-muted-foreground">/ Run History</span>
        </div>
        
        <div className="flex items-center gap-2">
          <a
            href={`/workflows/${workflowId}`}
            className="text-xs text-muted-foreground hover:text-foreground"
          >
            ← Back to Editor
          </a>
        </div>
      </div>
      
      {/* Runs list placeholder */}
      <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6">
        <h2 className="text-xl font-semibold text-foreground">Run History</h2>
        <p className="text-muted-foreground">Run history for workflow: {workflowId}</p>
        <p className="text-xs text-muted-foreground">(Placeholder - AiModel-615)</p>
      </div>
    </div>
  )
}
