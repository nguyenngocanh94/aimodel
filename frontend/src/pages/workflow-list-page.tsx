import { useNavigate } from '@tanstack/react-router'

/**
 * WorkflowListPage - Placeholder for workflow list screen
 * 
 * Per plan section 9.1:
 * - Lists all workflows in a table/grid
 * - Shows name, created date, last run status
 * - Actions: open, duplicate, delete
 * - Button to create new workflow
 * 
 * TODO: AiModel-609 - Implement full workflow list screen
 */
export function WorkflowListPage() {
  const navigate = useNavigate()

  return (
    <div className="flex h-screen w-screen flex-col items-center justify-center gap-4 bg-background p-6">
      <h1 className="text-2xl font-semibold text-foreground">Workflows</h1>
      <p className="text-muted-foreground">Workflow list screen (placeholder)</p>
      
      <div className="flex gap-2">
        <button
          onClick={() => navigate({ to: '/workflows/demo-1' })}
          className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
        >
          Open Demo Workflow
        </button>
        
        <button
          onClick={() => navigate({ to: '/workflows/demo-1/runs' })}
          className="rounded-md bg-secondary px-4 py-2 text-sm text-secondary-foreground hover:bg-secondary/90"
        >
          View Runs
        </button>
      </div>
    </div>
  )
}
