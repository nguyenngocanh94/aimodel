/**
 * CanvasEmptyState - AiModel-537.2
 * Shows template suggestions, quick-add hint, and keyboard shortcuts
 * when the canvas has no nodes.
 * Per plan section 6.7
 */

import { Workflow, Plus, Keyboard, Sparkles, Film, Megaphone, GraduationCap } from 'lucide-react'
import { Button } from '@/shared/ui/button'
import { builtInTemplates, type WorkflowTemplate } from '@/features/templates/built-in-templates'
import { SHORTCUT_DEFINITIONS } from '@/features/canvas/hooks/use-canvas-shortcuts'

interface CanvasEmptyStateProps {
  readonly onSelectTemplate?: (template: WorkflowTemplate) => void
  readonly onAddNode?: () => void
}

/** Template ID → icon and color mapping per design system */
const templateIcons: Record<string, { icon: typeof Film; color: string }> = {
  'marketing-clip': { icon: Film, color: 'text-node-video' },        // amber
  'social-ad': { icon: Megaphone, color: 'text-node-visuals' },     // violet
  'educational-explainer': { icon: GraduationCap, color: 'text-cyan-400' }, // cyan
}

export function CanvasEmptyState({ onSelectTemplate, onAddNode }: CanvasEmptyStateProps) {
  return (
    <div
      className="flex h-full w-full items-center justify-center p-8"
      data-testid="canvas-empty-state"
    >
      <div className="w-full max-w-lg space-y-8 text-center">
        {/* Header */}
        <div className="space-y-3">
          <Workflow className="h-12 w-12 text-muted-foreground mx-auto" />
          <h2 className="text-xl font-semibold text-foreground">
            Create your first workflow
          </h2>
          <p className="text-sm text-muted-foreground max-w-sm mx-auto">
            Build an AI video pipeline by starting from a template or adding nodes manually.
          </p>
        </div>

        {/* Template suggestions */}
        <div className="space-y-3">
          <div className="flex items-center justify-center gap-1.5 text-xs font-medium text-muted-foreground uppercase tracking-wide">
            <Sparkles className="h-3.5 w-3.5" />
            Start from template
          </div>
          <div className="grid gap-3">
            {builtInTemplates.map((template) => {
              const iconConfig = templateIcons[template.id]
              const Icon = iconConfig?.icon ?? Sparkles
              const iconColor = iconConfig?.color ?? 'text-muted-foreground'
              
              return (
                <button
                  key={template.id}
                  className="flex items-start gap-4 rounded-lg border border-border p-4 text-left hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 transition-colors"
                  onClick={() => onSelectTemplate?.(template)}
                  aria-label={`Start from template: ${template.name}`}
                  data-testid={`template-${template.id}`}
                >
                  <div className={`shrink-0 ${iconColor}`}>
                    <Icon className="h-6 w-6" aria-hidden="true" />
                  </div>
                  <div className="flex-1 min-w-0 text-left">
                    <div className="text-sm font-medium text-foreground">{template.name}</div>
                    <div className="text-xs text-muted-foreground line-clamp-1 mt-0.5">
                      {template.description}
                    </div>
                    <div className="flex flex-wrap gap-1.5 mt-2">
                      {template.tags.map((tag) => (
                        <span
                          key={tag}
                          className="text-[10px] bg-muted rounded px-1.5 py-0.5 text-muted-foreground"
                        >
                          {tag}
                        </span>
                      ))}
                    </div>
                  </div>
                </button>
              )
            })}
          </div>
        </div>

        {/* Or add first node */}
        <div className="flex items-center gap-4">
          <div className="flex-1 h-px bg-border" />
          <span className="text-xs text-muted-foreground">or</span>
          <div className="flex-1 h-px bg-border" />
        </div>

        <Button
          variant="outline"
          size="lg"
          className="gap-2"
          onClick={onAddNode}
          data-testid="canvas-empty-cta"
        >
          <Plus className="h-4 w-4" />
          Add first node
        </Button>

        {/* Keyboard shortcuts hint */}
        <div className="space-y-2 pt-4 border-t border-border/50">
          <div className="flex items-center justify-center gap-1.5 text-xs font-medium text-muted-foreground uppercase tracking-wide">
            <Keyboard className="h-3.5 w-3.5" />
            Keyboard shortcuts
          </div>
          <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-muted-foreground max-w-sm mx-auto">
            {SHORTCUT_DEFINITIONS.slice(0, 6).map(({ key, action }) => (
              <div key={key} className="flex justify-between gap-3">
                <span>{action}</span>
                <kbd className="font-mono text-[10px] bg-muted rounded px-1.5 py-0.5">{key}</kbd>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}
