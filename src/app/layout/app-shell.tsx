import { useRef } from 'react'
import type { PanelImperativeHandle } from 'react-resizable-panels'
import { CanvasSurface } from '@/features/canvas/components/canvas-surface'
import { RunToolbar } from '@/features/canvas/components/run-toolbar'
import { InspectorPanel } from '@/features/inspector/components/inspector-panel'
import { NodeLibraryPanel } from '@/features/node-library/components/node-library-panel'
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from '@/shared/ui/resizable'

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

const DEFAULT_LEFT_SIZE = 22
const DEFAULT_RIGHT_SIZE = 25

export function AppShell() {
  const leftPanelRef = useRef<PanelImperativeHandle>(null)
  const rightPanelRef = useRef<PanelImperativeHandle>(null)

  return (
    <div className="flex h-screen min-h-0 flex-col bg-background">
      <DegradedModeBanner />
      <AppHeader />
      <main className="min-h-0 flex-1" aria-label="Workflow editor">
        <ResizablePanelGroup orientation="horizontal" className="h-full">
          {/* Left panel: Node library (280px default ≈ 22%) */}
          <ResizablePanel
            panelRef={leftPanelRef}
            id="node-library"
            defaultSize={DEFAULT_LEFT_SIZE}
            minSize={14}
            maxSize={36}
            collapsible
            className="min-w-[12rem]"
          >
            <nav aria-label="Node library">
              <NodeLibraryPanel />
            </nav>
          </ResizablePanel>
          <ResizableHandle
            withHandle
            aria-label="Resize node library"
            data-testid="panel-resize-left"
            onDoubleClick={() => leftPanelRef.current?.resize(DEFAULT_LEFT_SIZE)}
          />

          {/* Center panel: Canvas + Run Toolbar */}
          <ResizablePanel id="canvas" defaultSize={53} minSize={35} className="min-w-0">
            <div className="flex h-full min-h-0 flex-col">
              <RunToolbar />
              <div className="min-h-0 flex-1">
                <CanvasSurface />
              </div>
            </div>
          </ResizablePanel>
          <ResizableHandle
            withHandle
            aria-label="Resize inspector"
            data-testid="panel-resize-right"
            onDoubleClick={() => rightPanelRef.current?.resize(DEFAULT_RIGHT_SIZE)}
          />

          {/* Right panel: Inspector (400px default ≈ 25%) */}
          <ResizablePanel
            panelRef={rightPanelRef}
            id="inspector"
            defaultSize={DEFAULT_RIGHT_SIZE}
            minSize={16}
            maxSize={40}
            collapsible
            className="min-w-[14rem]"
          >
            <section aria-label="Inspector">
              <InspectorPanel />
            </section>
          </ResizablePanel>
        </ResizablePanelGroup>
      </main>
      <AppStatusBar />
    </div>
  )
}
