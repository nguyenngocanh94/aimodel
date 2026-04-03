import {
  Download,
  FileJson,
  Redo2,
  Settings,
  Sparkles,
  Undo2,
  Upload,
} from 'lucide-react'

import { Button } from '@/shared/ui/button'

export interface AppHeaderProps {
  readonly workflowName?: string
  readonly saveLabel?: string
}

export function AppHeader({
  workflowName = 'Untitled workflow',
  saveLabel = 'Saved',
}: AppHeaderProps) {
  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-card px-3">
      <div className="min-w-0 flex-1">
        <h1 className="truncate text-sm font-semibold leading-none">{workflowName}</h1>
        <p className="mt-1 text-xs text-muted-foreground">{saveLabel}</p>
      </div>
      <div className="flex shrink-0 items-center gap-1">
        <Button type="button" variant="outline" size="sm" disabled aria-label="Undo">
          <Undo2 className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled aria-label="Redo">
          <Redo2 className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled aria-label="Import workflow">
          <Upload className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled aria-label="Export workflow" data-testid="workflow-export-btn">
          <Download className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled aria-label="Templates">
          <Sparkles className="h-4 w-4" />
        </Button>
        <Button type="button" variant="outline" size="sm" disabled aria-label="Settings">
          <Settings className="h-4 w-4" />
        </Button>
        <Button type="button" variant="ghost" size="sm" disabled aria-label="Document JSON">
          <FileJson className="h-4 w-4" />
        </Button>
      </div>
    </header>
  )
}
