import {
  Download,
  Undo2,
  Redo2,
  Settings,
  PanelLeft,
} from 'lucide-react'

import { Button } from '@/shared/ui/button'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

export interface AppHeaderProps {
  readonly workflowName?: string
  readonly saveLabel?: string
}

export function AppHeader({
  workflowName = 'Untitled workflow',
  saveLabel = 'Saved',
}: AppHeaderProps) {
  const isLibraryVisible = useWorkflowStore((s) => s.libraryUi.isVisible)
  const toggleLibraryVisible = useWorkflowStore((s) => s.toggleLibraryVisible)

  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-card px-4">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <h1 className="truncate text-sm font-semibold leading-none">{workflowName}</h1>
          <span className="text-xs text-muted-foreground">{saveLabel}</span>
        </div>
      </div>
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
        <div className="mx-1 h-4 w-px bg-border" />
        <Button type="button" variant="ghost" size="icon" disabled aria-label="Export workflow" data-testid="workflow-export-btn">
          <Download className="h-4 w-4" />
        </Button>
        <Button type="button" variant="ghost" size="icon" disabled aria-label="Settings">
          <Settings className="h-4 w-4" />
        </Button>
      </div>
    </header>
  )
}
