import { memo, useCallback } from 'react';
import { GripVertical } from 'lucide-react';
import { cn } from '@/shared/lib/utils';
import { Badge } from '@/shared/ui/badge';
import type { TemplateMetadata } from '@/features/node-registry/node-registry';

const categoryColors: Record<string, string> = {
  input: 'bg-blue-500',
  script: 'bg-amber-500',
  visuals: 'bg-purple-500',
  audio: 'bg-pink-500',
  video: 'bg-red-500',
  utility: 'bg-gray-500',
  output: 'bg-green-500',
};

interface NodeLibraryItemProps {
  readonly template: TemplateMetadata;
  readonly compact?: boolean;
}

/**
 * NodeLibraryItem - Draggable node item in the library panel
 *
 * Shows icon, title, description, and port summary.
 * Drag to canvas to create a new node.
 */
export const NodeLibraryItem = memo(function NodeLibraryItem({
  template,
  compact = false,
}: NodeLibraryItemProps) {
  const accentColor = categoryColors[template.category] ?? 'bg-gray-500';

  const onDragStart = useCallback(
    (event: React.DragEvent) => {
      const dragData = {
        type: 'node',
        templateType: template.type,
      };
      event.dataTransfer.setData('application/json', JSON.stringify(dragData));
      event.dataTransfer.effectAllowed = 'copy';
    },
    [template.type],
  );

  const inputCount = template.inputs.length;
  const outputCount = template.outputs.length;

  return (
    <div
      role="button"
      tabIndex={0}
      draggable
      onDragStart={onDragStart}
      aria-label={`${template.title} — ${template.description}. ${inputCount} inputs, ${outputCount} outputs. Drag to canvas to add.`}
      className={cn(
        'group flex cursor-grab items-start gap-2 rounded-md border bg-card p-2 transition-colors',
        'hover:border-primary/50 hover:bg-accent/50',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
        'active:cursor-grabbing',
      )}
      title={`Drag to canvas: ${template.title}`}
    >
      {/* Drag handle + category accent */}
      <div className="flex shrink-0 items-center gap-1 pt-0.5">
        <GripVertical className="h-3.5 w-3.5 text-muted-foreground/50 group-hover:text-muted-foreground" aria-hidden="true" />
        <div className={cn('h-4 w-1 rounded-full', accentColor)} aria-hidden="true" />
      </div>

      {/* Content */}
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-1">
          <span className="text-sm font-medium truncate">{template.title}</span>
          {template.executable && (
            <Badge variant="secondary" className="h-4 px-1 text-[10px] shrink-0">
              exec
            </Badge>
          )}
        </div>

        {!compact && (
          <>
            <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">
              {template.description}
            </p>
            <div className="flex items-center gap-2 mt-1 text-[10px] text-muted-foreground">
              <span>
                {inputCount} in / {outputCount} out
              </span>
            </div>
          </>
        )}
      </div>
    </div>
  );
});
