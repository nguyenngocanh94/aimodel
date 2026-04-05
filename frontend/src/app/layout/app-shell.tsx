import { CanvasSurface } from '@/features/canvas/components/canvas-surface'
import { RunToolbar } from '@/features/canvas/components/run-toolbar'
import { InspectorPanel } from '@/features/inspector/components/inspector-panel'
import { NodeLibraryPanel } from '@/features/node-library/components/node-library-panel'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

import { AppHeader } from './app-header'
import { DegradedModeBanner } from '@/app/boot/degraded-mode-banner'

function AppStatusBar() {
  return (
    <footer
      className="flex h-8 shrink-0 items-center gap-4 border-t bg-muted/40 px-3 text-xs text-muted-foreground"
      role="status"
      aria-label="Application status"
    >
      <span>Nothing selected</span>
      <span className="hidden sm:inline">Validation: —</span>
      <span className="hidden md:inline">Autosave: —</span>
      <span className="hidden lg:inline">Run: idle</span>
    </footer>
  )
}

export function AppShell() {
  const isLibraryVisible = useWorkflowStore((s) => s.libraryUi.isVisible)
  const hasSelectedNode = useWorkflowStore((s) => s.selectedNodeIds.length > 0)

  return (
    <div className="flex h-screen min-h-0 flex-col bg-background">
      <DegradedModeBanner />
      <AppHeader />
      <main className="flex flex-1 min-h-0 overflow-hidden" aria-label="Workflow editor">
        {/* Left sidebar: Node library - shown only via header toggle */}
        {isLibraryVisible && (
          <nav
            aria-label="Node library"
            className="w-[280px] min-w-[240px] max-w-[360px] flex-shrink-0 border-r border-border bg-card overflow-hidden animate-in slide-in-from-left duration-200"
          >
            <NodeLibraryPanel />
          </nav>
        )}

        {/* Center: Canvas + Run Toolbar - takes remaining space */}
        <div className="flex flex-col flex-1 min-w-0 overflow-hidden">
          <RunToolbar />
          <div className="flex-1 min-h-0 overflow-hidden">
            <CanvasSurface />
          </div>
        </div>

        {/* Right sidebar: Inspector - shown only when a node is selected */}
        {hasSelectedNode && (
          <section
            aria-label="Inspector"
            className="w-[320px] min-w-[280px] max-w-[400px] flex-shrink-0 border-l border-border bg-card overflow-hidden animate-in slide-in-from-right duration-200"
          >
            <InspectorPanel />
          </section>
        )}
      </main>
      <AppStatusBar />
    </div>
  )
}
