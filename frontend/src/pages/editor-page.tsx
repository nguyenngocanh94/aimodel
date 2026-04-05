import { useParams } from '@tanstack/react-router'
import { AppHeader } from '@/app/layout/app-header'
import { BootGate } from '@/app/boot/boot-gate'

/**
 * EditorPage - Wraps the existing AppShell with workflow context
 *
 * Route: /workflows/$workflowId
 *
 * TODO: AiModel-610 - Rewire canvas to backend API
 */
export function EditorPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId' })

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader
        workflowId={workflowId}
        workflowName={`Workflow ${workflowId.slice(0, 8)}`}
      />
      <div className="flex-1 overflow-hidden">
        <BootGate />
      </div>
    </div>
  )
}
