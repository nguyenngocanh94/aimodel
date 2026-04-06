import {
  Undo2,
  Redo2,
  PanelLeft,
  ArrowLeft,
} from 'lucide-react'
import { Link, useMatchRoute } from '@tanstack/react-router'

import { Button } from '@/shared/ui/button'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

export interface AppHeaderProps {
  readonly workflowId?: string
  readonly workflowName?: string
  readonly saveLabel?: string
}

export function AppHeader({
  workflowId,
  workflowName = 'Untitled workflow',
  saveLabel = 'Saved',
}: AppHeaderProps) {
  const isLibraryVisible = useWorkflowStore((s) => s.libraryUi.isVisible)
  const toggleLibraryVisible = useWorkflowStore((s) => s.toggleLibraryVisible)
  const matchRoute = useMatchRoute()

  const isEditorActive = workflowId
    ? matchRoute({ to: '/workflows/$workflowId', params: { workflowId } })
    : false
  const isRunsActive = workflowId
    ? matchRoute({ to: '/workflows/$workflowId/runs', params: { workflowId } })
    : false

  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-card px-4">
      {/* Back to workflows */}
      <Link to="/workflows" className="flex items-center gap-1 text-muted-foreground hover:text-foreground transition-colors" data-testid="back-to-workflows">
        <ArrowLeft className="h-4 w-4" />
        <span className="text-xs hidden sm:inline">Workflows</span>
      </Link>

      <div className="mx-1 h-4 w-px bg-border" />

      {/* Workflow name */}
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <h1 className="truncate text-sm font-semibold leading-none">{workflowName}</h1>
          <span className="text-xs text-muted-foreground">{saveLabel}</span>
        </div>
      </div>

      {/* Navigation tabs */}
      {workflowId && (
        <nav className="flex items-center gap-1" data-testid="workflow-tabs">
          <Link
            to="/workflows/$workflowId"
            params={{ workflowId }}
            className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
              isEditorActive
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:text-foreground hover:bg-muted'
            }`}
            data-testid="tab-editor"
          >
            Editor
          </Link>
          <Link
            to="/workflows/$workflowId/runs"
            params={{ workflowId }}
            className={`px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
              isRunsActive
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:text-foreground hover:bg-muted'
            }`}
            data-testid="tab-runs"
          >
            Runs
          </Link>
        </nav>
      )}

      <div className="mx-1 h-4 w-px bg-border" />

      <div className="flex shrink-0 items-center gap-1">
        <Button
          type="button"
          variant={isLibraryVisible ? 'secondary' : 'ghost'}
          size="icon"
          onClick={toggleLibraryVisible}
          aria-label={isLibraryVisible ? 'Hide node library' : 'Show node library'}
          aria-pressed={isLibraryVisible}
          data-testid="toggle-node-library-btn"
        >
          <PanelLeft className="h-4 w-4" />
        </Button>
        <Button type="button" variant="ghost" size="icon" disabled aria-label="Undo">
          <Undo2 className="h-4 w-4" />
        </Button>
        <Button type="button" variant="ghost" size="icon" disabled aria-label="Redo">
          <Redo2 className="h-4 w-4" />
        </Button>
      </div>
    </header>
  )
}
