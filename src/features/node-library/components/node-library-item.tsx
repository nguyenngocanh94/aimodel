import { memo, useCallback, useState } from 'react';
import {
  GripVertical,
  FileText,
  Pen,
  Scissors,
  Sparkles,
  Image,
  ImagePlus,
  Mic,
  Subtitles,
  Film,
  ShieldCheck,
  Download,
} from 'lucide-react';
import { cn } from '@/shared/lib/utils';
import type { TemplateMetadata } from '@/features/node-registry/node-registry';

/** Category → design system accent bar class */
const categoryAccentClasses: Record<string, string> = {
  input: 'bg-node-input',
  script: 'bg-node-script',
  visuals: 'bg-node-visuals',
  audio: 'bg-node-audio',
  video: 'bg-node-video',
  utility: 'bg-node-utility',
  output: 'bg-node-output',
};

/** Category → icon tint class */
const categoryIconClasses: Record<string, string> = {
  input: 'text-node-input',
  script: 'text-node-script',
  visuals: 'text-node-visuals',
  audio: 'text-node-audio',
  video: 'text-node-video',
  utility: 'text-node-utility',
  output: 'text-node-output',
};

/** Map template type → icon, with category fallbacks */
function getNodeIcon(type: string, category: string) {
  const iconMap: Record<string, typeof FileText> = {
    'user-prompt': FileText,
    'script-writer': Pen,
    'scene-splitter': Scissors,
    'prompt-refiner': Sparkles,
    'image-generator': Image,
    'image-asset-mapper': ImagePlus,
    'tts-voiceover-planner': Mic,
    'subtitle-formatter': Subtitles,
    'video-composer': Film,
    'review-checkpoint': ShieldCheck,
    'final-export': Download,
  };
  const categoryIcons: Record<string, typeof FileText> = {
    input: FileText,
    script: Pen,
    visuals: Image,
    audio: Mic,
    video: Film,
    utility: Sparkles,
    output: Download,
  };
  return iconMap[type] ?? categoryIcons[category] ?? FileText;
}

interface NodeLibraryItemProps {
  readonly template: TemplateMetadata;
  readonly compact?: boolean;
  readonly focused?: boolean;
  readonly disabled?: boolean;
  readonly readonly?: boolean;
  readonly onInsert?: (templateType: string) => void;
}

/**
 * NodeLibraryItem — Draggable node in the library panel.
 *
 * Design system section 9 anatomy:
 * icon + title + type/category metadata + optional badge + drag handle on hover
 *
 * States: default, hover, focus-visible, keyboard-highlighted,
 *         dragging, disabled, readonly
 */
export const NodeLibraryItem = memo(function NodeLibraryItem({
  template,
  compact = false,
  focused = false,
  disabled = false,
  readonly: isReadonly = false,
  onInsert,
}: NodeLibraryItemProps) {
  const [isDragging, setIsDragging] = useState(false);
  const Icon = getNodeIcon(template.type, template.category);
  const iconColor = categoryIconClasses[template.category] ?? 'text-muted-foreground';
  const accentColor = categoryAccentClasses[template.category] ?? 'bg-muted-foreground';
  const inputCount = template.inputs.length;
  const outputCount = template.outputs.length;

  const onDragStart = useCallback(
    (event: React.DragEvent) => {
      if (disabled || isReadonly) {
        event.preventDefault();
        return;
      }
      const dragData = { type: 'node', templateType: template.type };
      event.dataTransfer.setData('application/json', JSON.stringify(dragData));
      event.dataTransfer.effectAllowed = 'copy';
      setIsDragging(true);
    },
    [template.type, disabled, isReadonly],
  );

  const onDragEnd = useCallback(() => {
    setIsDragging(false);
  }, []);

  const handleKeyDown = useCallback(
    (event: React.KeyboardEvent) => {
      if (event.key === 'Enter' && onInsert && !disabled && !isReadonly) {
        event.preventDefault();
        onInsert(template.type);
      }
    },
    [onInsert, template.type, disabled, isReadonly],
  );

  const tooltipText = disabled
    ? 'Unavailable in current workflow scope'
    : isReadonly
      ? 'Editing is disabled'
      : `Drag to canvas: ${template.title}`;

  return (
    <div
      role="button"
      tabIndex={disabled ? -1 : 0}
      draggable={!disabled && !isReadonly}
      onDragStart={onDragStart}
      onDragEnd={onDragEnd}
      onKeyDown={handleKeyDown}
      aria-label={`${template.title} — ${template.description}. ${inputCount} inputs, ${outputCount} outputs.${disabled ? ' Unavailable.' : ''}`}
      aria-disabled={disabled}
      title={tooltipText}
      data-testid={`node-library-item-${template.type}`}
      data-focused={focused || undefined}
      data-dragging={isDragging || undefined}
      data-disabled={disabled || undefined}
      data-readonly={isReadonly || undefined}
      className={cn(
        'group flex items-start gap-2 rounded-md border border-border bg-card px-2 py-2',
        'transition-[border-color,box-shadow,opacity,background-color] duration-150 ease-out',
        'hover:border-foreground/20 hover:bg-accent/50',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        'data-[focused=true]:ring-2 data-[focused=true]:ring-primary/70 data-[focused=true]:border-primary/40',
        'data-[dragging=true]:opacity-70 data-[dragging=true]:shadow-lg',
        'data-[disabled=true]:opacity-50 data-[disabled=true]:cursor-not-allowed data-[disabled=true]:pointer-events-none',
        'data-[readonly=true]:cursor-default',
        !disabled && !isReadonly && 'cursor-grab active:cursor-grabbing',
      )}
    >
      {/* Drag handle (fades in on hover) */}
      <div className="flex shrink-0 items-center pt-0.5">
        <GripVertical
          className="h-3.5 w-3.5 text-muted-foreground/30 transition-hover group-hover:text-muted-foreground"
          aria-hidden="true"
        />
      </div>

      {/* Category accent bar + Icon */}
      <div className="flex shrink-0 flex-col items-center gap-1 pt-0.5">
        <div className={cn('h-4 w-1 rounded-full', accentColor)} aria-hidden="true" />
        <Icon className={cn('h-4 w-4', iconColor)} aria-hidden="true" />
      </div>

      {/* Content */}
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="truncate text-sm font-medium">{template.title}</span>
          {template.executable && (
            <span className="shrink-0 rounded-sm border border-border bg-muted px-1 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Exec
            </span>
          )}
        </div>

        {!compact && (
          <>
            <p className="mt-0.5 truncate font-mono text-[11px] text-muted-foreground">
              {template.category.toUpperCase()} · {inputCount} in / {outputCount} out
            </p>
            <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground/70">
              {template.description}
            </p>
          </>
        )}

        {compact && (
          <p className="truncate font-mono text-[10px] text-muted-foreground">
            {template.category.toUpperCase()}
          </p>
        )}
      </div>
    </div>
  );
});
