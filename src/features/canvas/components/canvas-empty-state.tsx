/**
 * CanvasEmptyState - AiModel-537.2
 * Shows template suggestions, quick-add hint, and keyboard shortcuts
 * when the canvas has no nodes.
 * Per plan section 6.7
 */

import { Workflow, Plus, Keyboard, Sparkles } from 'lucide-react'
import { Button } from '@/shared/ui/button'
import { builtInTemplates, type WorkflowTemplate } from '@/features/templates/built-in-templates'
import { SHORTCUT_DEFINITIONS } from '@/features/canvas/hooks/use-canvas-shortcuts'

interface CanvasEmptyStateProps {
  readonly onSelectTemplate?: (template: WorkflowTemplate) => void
  readonly onAddNode?: () => void
}

export function CanvasEmptyState({ onSelectTemplate, onAddNode }: CanvasEmptyStateProps) {
  return (
    <div
      className="flex h-full w-full items-center justify-center"
      data-testid="canvas-empty-state"
    >
      <div className="max-w-md space-y-6 text-center px-6">
        {/* Header */}
        <div className="space-y-2">
          <Workflow className="h-10 w-10 text-muted-foreground mx-auto" />
          <h2 className="text-lg font-semibold text-foreground">
            Create your first workflow
          </h2>
          <p className="text-sm text-muted-foreground">
            Build an AI video pipeline by starting from a template or adding nodes manually.
          </p>
        </div>

        {/* Template suggestions */}
        <div className="space-y-2">
          <div className="flex items-center justify-center gap-1 text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            <Sparkles className="h-3 w-3" />
            Start from template
          </div>
          <div className="grid gap-2">
            {builtInTemplates.map((template) => (
              <button
                key={template.id}
                className="flex items-start gap-3 rounded-lg border p-3 text-left hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 transition-colors"
                onClick={() => onSelectTemplate?.(template)}
                aria-label={`Start from template: ${template.name}`}
                data-testid={`template-${template.id}`}
              >
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium text-foreground">{template.name}</div>
                  <div className="text-xs text-muted-foreground line-clamp-1">
                    {template.description}
                  </div>
                  <div className="flex flex-wrap gap-1 mt-1">
                    {template.tags.map((tag) => (
                      <span
                        key={tag}
                        className="text-[9px] bg-muted rounded px-1 py-0.5 text-muted-foreground"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Or add first node */}
        <div className="flex items-center gap-3">
          <div className="flex-1 h-px bg-border" />
          <span className="text-xs text-muted-foreground">or</span>
          <div className="flex-1 h-px bg-border" />
        </div>

        <Button
          variant="outline"
          className="gap-2"
          onClick={onAddNode}
          data-testid="canvas-empty-cta"
        >
          <Plus className="h-4 w-4" />
          Add first node
        </Button>

        {/* Keyboard shortcuts hint */}
        <div className="space-y-1.5 pt-2">
          <div className="flex items-center justify-center gap-1 text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            <Keyboard className="h-3 w-3" />
            Keyboard shortcuts
          </div>
          <div className="grid grid-cols-2 gap-x-4 gap-y-0.5 text-[10px] text-muted-foreground">
            {SHORTCUT_DEFINITIONS.slice(0, 8).map(({ key, action }) => (
              <div key={key} className="flex justify-between gap-2">
                <span>{action}</span>
                <kbd className="font-mono text-[9px] bg-muted rounded px-1">{key}</kbd>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
