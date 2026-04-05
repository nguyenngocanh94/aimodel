import { useParams } from '@tanstack/react-router'
import { BootGate } from '@/app/boot/boot-gate'

/**
 * EditorPage - Wraps the existing AppShell with workflow context
 * 
 * Per plan section 6.1:
 * - Shows workflow editor with canvas, node library, inspector
 * - Loads workflow data from backend API
 * - Displays workflow name in header
 * - Has tab navigation to runs page
 * 
 * Route: /workflows/$workflowId
 * 
 * TODO: AiModel-610 - Rewire canvas to backend API
 */
export function EditorPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId' })

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      {/* Workflow header with navigation */}
      <div className="flex items-center justify-between border-b px-4 py-2">
        <div className="flex items-center gap-4">
          <h1 className="text-sm font-medium text-foreground">
            Workflow: {workflowId}
          </h1>
          <span className="text-xs text-muted-foreground">(Editor)</span>
        </div>
        
        <div className="flex items-center gap-2">
          <a
            href={`/workflows/${workflowId}/runs`}
            className="text-xs text-muted-foreground hover:text-foreground"
          >
            View Runs →
          </a>
        </div>
      </div>
      
      {/* Main editor area - wrapped in BootGate for persistence/loading */}
      <div className="flex-1 overflow-hidden">
        <BootGate />
      </div>
    </div>
  )
}
