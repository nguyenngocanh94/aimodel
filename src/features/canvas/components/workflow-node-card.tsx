import { memo } from 'react';
import { Handle, Position, type Node, type NodeProps } from '@xyflow/react';
import { cn } from '@/shared/lib/utils';
import { Badge } from '@/shared/ui/badge';
import {
  AlertCircle,
  CheckCircle2,
  XCircle,
  Loader2,
  MinusCircle,
  StopCircle,
  PauseCircle,
  Clock,
  MoreVertical,
  ImageIcon,
  Play,
} from 'lucide-react';
import type { WorkflowNode, NodeRunRecord } from '@/features/workflows/domain/workflow-types';

/* ── Category accent colors (from design-system tokens) ── */
const categoryAccentBg: Record<string, string> = {
  input: 'bg-node-input/70',
  script: 'bg-node-script/70',
  visuals: 'bg-node-visuals/70',
  audio: 'bg-node-audio/70',
  video: 'bg-node-video/70',
  utility: 'bg-node-utility/70',
  output: 'bg-node-output/70',
};

const categoryIconTint: Record<string, string> = {
  input: 'text-node-input',
  script: 'text-node-script',
  visuals: 'text-node-visuals',
  audio: 'text-node-audio',
  video: 'text-node-video',
  utility: 'text-node-utility',
  output: 'text-node-output',
};

export type NodeRunStatus = NodeRunRecord['status'] | 'idle';

export interface WorkflowNodeData {
  readonly node: WorkflowNode;
  readonly category: string;
  readonly inputPorts: readonly { key: string; label: string }[];
  readonly outputPorts: readonly { key: string; label: string }[];
  readonly validationIssues?: number;
  readonly runStatus?: NodeRunStatus;
  readonly skipReason?: NodeRunRecord['skipReason'];
  readonly disabled?: boolean;
  readonly previewText?: string;
  readonly staleData?: boolean;
  readonly previewAvailable?: boolean;
  readonly footerMeta?: string;
  readonly errorMessage?: string;
  readonly thumbnailUrls?: readonly string[];
  readonly videoPreviewUrl?: string;
  readonly videoDuration?: string;
  [key: string]: unknown;
}

export type WorkflowNodeType = Node<WorkflowNodeData, 'workflowNode'>;

/* ── Skip reason labels ── */
const skipReasonLabels: Record<string, string> = {
  disabled: 'Skipped: node is disabled',
  missingRequiredInputs: 'Skipped: missing required inputs',
  upstreamFailed: 'Skipped: upstream node failed',
};

/* ── Status dot component ── */
function StatusDot({ runStatus }: { readonly runStatus: NodeRunStatus }) {
  if (runStatus === 'idle') return null;

  const isRunning = runStatus === 'running';
  const isPending = runStatus === 'pending';
  const isSuccess = runStatus === 'success';
  const isError = runStatus === 'error';

  return (
    <span
      className={cn(
        'h-2 w-2 shrink-0 rounded-full',
        isRunning && 'bg-signal animate-status-dot',
        isPending && 'bg-signal/60',
        isSuccess && 'bg-success',
        isError && 'bg-destructive',
        !isRunning && !isPending && !isSuccess && !isError && 'bg-muted-foreground',
      )}
      data-running={isRunning || undefined}
      aria-hidden="true"
    />
  );
}

/* ── Run status badge ── */
function RunStatusBadge({
  runStatus,
  skipReason,
}: {
  readonly runStatus: NodeRunStatus;
  readonly skipReason?: NodeRunRecord['skipReason'];
}) {
  const config: Record<
    string,
    { icon: typeof CheckCircle2; variant: 'default' | 'destructive' | 'secondary'; className?: string; title: string }
  > = {
    pending: { icon: Clock, variant: 'secondary', title: 'Queued' },
    running: { icon: Loader2, variant: 'secondary', className: 'animate-spin', title: 'Running...' },
    success: { icon: CheckCircle2, variant: 'default', title: 'Succeeded' },
    error: { icon: XCircle, variant: 'destructive', title: 'Error' },
    skipped: { icon: MinusCircle, variant: 'secondary', title: skipReason ? skipReasonLabels[skipReason] ?? 'Skipped' : 'Skipped' },
    cancelled: { icon: StopCircle, variant: 'secondary', title: 'Cancelled' },
    awaitingReview: { icon: PauseCircle, variant: 'secondary', title: 'Awaiting review' },
  };

  const entry = config[runStatus];
  if (!entry) return null;

  const Icon = entry.icon;

  return (
    <Badge
      variant={entry.variant}
      className="h-5 px-1.5 py-0 gap-1 text-[10px] font-medium"
      title={entry.title}
      aria-label={entry.title}
    >
      <Icon className={cn('h-3 w-3', entry.className)} aria-hidden="true" />
      <span className="sr-only">{entry.title}</span>
    </Badge>
  );
}

/* ── Image thumbnail grid (image-generator nodes) ── */
function ImageThumbnailGrid({ thumbnailUrls }: { readonly thumbnailUrls?: readonly string[] }) {
  return (
    <div className="grid grid-cols-2 gap-1 px-3 pb-2" data-testid="image-thumbnail-grid">
      {Array.from({ length: 4 }, (_, i) => {
        const url = thumbnailUrls?.[i];
        return (
          <div
            key={i}
            className="w-[56px] h-[56px] rounded-sm bg-border overflow-hidden flex items-center justify-center"
          >
            {url ? (
              <img src={url} alt={`Generated image ${i + 1}`} className="h-full w-full object-cover" />
            ) : (
              <ImageIcon className="h-4 w-4 text-muted-foreground/40" aria-hidden="true" />
            )}
          </div>
        );
      })}
    </div>
  );
}

/* ── Video preview frame (video-composer nodes) ── */
function VideoPreviewFrame({
  videoPreviewUrl,
  videoDuration = '0:00 / 0:30',
}: {
  readonly videoPreviewUrl?: string;
  readonly videoDuration?: string;
}) {
  return (
    <div className="px-3 pb-2" data-testid="video-preview-frame">
      <div className="w-[236px] h-[128px] rounded-sm bg-border relative mx-auto overflow-hidden">
        {videoPreviewUrl ? (
          <img src={videoPreviewUrl} alt="Video preview" className="h-full w-full object-cover" />
        ) : null}
        {/* Play button overlay */}
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="w-8 h-8 rounded-full bg-background/80 border border-border flex items-center justify-center">
            <Play className="h-3.5 w-3.5 text-foreground ml-0.5" aria-hidden="true" />
          </div>
        </div>
        {/* Timeline */}
        <span className="absolute bottom-1 right-1.5 font-mono text-[10px] text-muted-foreground">
          {videoDuration}
        </span>
      </div>
    </div>
  );
}

/* ── Main node card ── */
export const WorkflowNodeCard = memo(function WorkflowNodeCard({
  data,
  selected,
  dragging,
}: NodeProps<WorkflowNodeType>) {
  const {
    node,
    category,
    inputPorts,
    outputPorts,
    validationIssues,
    runStatus = 'idle',
    skipReason,
    disabled,
    previewText,
    staleData,
    previewAvailable,
    footerMeta,
    errorMessage,
    thumbnailUrls,
    videoPreviewUrl,
    videoDuration,
  } = data;

  const accentBg = categoryAccentBg[category] ?? 'bg-node-utility/70';
  const iconTint = categoryIconTint[category] ?? 'text-node-utility';
  const isRunning = runStatus === 'running';
  const isError = runStatus === 'error';
  const hasValidationIssues = validationIssues != null && validationIssues > 0;

  return (
    <div
      role="button"
      aria-label={`${node.label}${disabled ? ' (disabled)' : ''}${runStatus !== 'idle' ? `, status: ${runStatus}` : ''}`}
      aria-selected={selected}
      className={cn(
        // Base
        'group relative w-[260px] rounded-lg border bg-card text-card-foreground shadow-sm',
        // Transitions
        'transition-[border-color,box-shadow,opacity,transform] duration-150 ease-out',
        // Default border
        'border-border',
        // Hover
        'hover:border-foreground/20',
        // Focus-visible
        'focus-within:ring-[1.5px] focus-within:ring-ring',
        // Selected
        selected && 'border-[1.5px] border-primary/50 ring-[1.5px] ring-primary/70 shadow-[0_0_12px_rgba(56,189,248,0.12)]',
        // Dragging
        dragging && 'opacity-95 shadow-lg',
        // Disabled
        disabled && 'opacity-55 pointer-events-auto',
        // Error
        isError && 'border-2 border-destructive/50',
        // Stale data
        staleData && 'border-dashed',
        // Running — thin amber left rule
        isRunning && 'border-l-2 border-l-signal',
      )}
      data-testid={`node-card-${node.id}`}
      data-selected={selected || undefined}
      data-dragging={dragging || undefined}
      data-disabled={disabled || undefined}
      data-running={isRunning || undefined}
    >
      {/* Category accent line (2px top bar) */}
      <div
        className={cn('absolute inset-x-0 top-0 h-0.5 rounded-t-lg', accentBg)}
        aria-hidden="true"
      />

      {/* Header */}
      <div className="flex items-start gap-2 px-3 pb-2 pt-3">
        {/* Icon placeholder with category tint */}
        <div className={cn('mt-0.5 shrink-0', iconTint)} aria-hidden="true">
          <div className="h-4 w-4 rounded-sm bg-current opacity-40" />
        </div>

        {/* Title + status dot */}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <h3 className="truncate text-[13px] font-medium">{node.label}</h3>
            <StatusDot runStatus={runStatus} />
          </div>

          {/* Subtitle: type + category */}
          <p className="truncate font-mono text-[10px] text-muted-foreground">
            {category.toUpperCase()} · {node.type}
          </p>
        </div>

        {/* Quick menu button */}
        <button
          className="flex h-8 w-8 items-center justify-center rounded-sm p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-accent hover:text-accent-foreground focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-hover"
          data-testid={`node-menu-btn-${node.id}`}
          aria-label={`Menu for ${node.label}`}
        >
          <MoreVertical className="h-4 w-4" aria-hidden="true" />
        </button>
      </div>

      {/* Inline badges */}
      {(hasValidationIssues || staleData || previewAvailable || (runStatus !== 'idle' && runStatus !== 'running')) && (
        <div className="flex flex-wrap gap-1 px-3 pb-2">
          {hasValidationIssues && (
            <Badge
              variant="destructive"
              className="h-5 px-1.5 py-0 gap-1 text-[10px] font-medium"
              aria-label={`${validationIssues} validation ${validationIssues === 1 ? 'issue' : 'issues'}`}
              title="Resolve validation issues to run this node"
            >
              <AlertCircle className="h-3 w-3" aria-hidden="true" />
              {validationIssues}
            </Badge>
          )}

          {runStatus !== 'idle' && runStatus !== 'running' && (
            <RunStatusBadge runStatus={runStatus} skipReason={skipReason} />
          )}

          {staleData && (
            <span
              className="rounded-sm border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
              title="Output no longer matches current configuration"
            >
              Stale
            </span>
          )}

          {previewAvailable && (
            <span
              className="rounded-sm border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
              title="Latest mock output available"
            >
              Preview
            </span>
          )}
        </div>
      )}

      {/* Preview text */}
      {previewText && (
        <p
          className="px-3 pb-2 text-xs text-muted-foreground truncate"
          title={previewText}
        >
          {previewText}
        </p>
      )}

      {/* Inline error message */}
      {isError && errorMessage && (
        <div className="mx-3 mb-2 rounded-sm bg-destructive/[0.06] px-2 py-1.5 flex items-start gap-1.5">
          <XCircle className="h-3 w-3 shrink-0 text-destructive mt-px" aria-hidden="true" />
          <span className="text-[11px] text-destructive">{errorMessage}</span>
        </div>
      )}

      {/* Image thumbnail grid (image-generator nodes) */}
      {node.type === 'image-generator' && (
        <ImageThumbnailGrid thumbnailUrls={thumbnailUrls} />
      )}

      {/* Video preview frame (video-composer nodes) */}
      {node.type === 'video-composer' && (
        <VideoPreviewFrame videoPreviewUrl={videoPreviewUrl} videoDuration={videoDuration} />
      )}

      {/* Input port rail */}
      {inputPorts.length > 0 && (
        <div className="border-t border-border px-2 py-1 space-y-0.5">
          {inputPorts.map((port) => (
            <div key={port.key} className="relative flex items-center" data-testid={`node-port-in-${node.id}-${port.key}`}>
              <Handle
                type="target"
                position={Position.Left}
                id={port.key}
                className={cn(
                  '!w-2 !h-2 !bg-background !border-2 !border-primary',
                  disabled && '!border-muted-foreground',
                )}
              />
              <span className="font-mono text-[10px] text-muted-foreground ml-4">
                {port.label}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* Output port rail */}
      {outputPorts.length > 0 && (
        <div className="border-t border-border px-2 py-1 space-y-0.5">
          {outputPorts.map((port) => (
            <div key={port.key} className="relative flex items-center justify-end" data-testid={`node-port-out-${node.id}-${port.key}`}>
              <span className="font-mono text-[10px] text-muted-foreground mr-4">
                {port.label}
              </span>
              <Handle
                type="source"
                position={Position.Right}
                id={port.key}
                className={cn(
                  '!w-2 !h-2 !bg-background !border-2 !border-cyan-400',
                  disabled && '!border-muted-foreground',
                )}
              />
            </div>
          ))}
        </div>
      )}

      {/* Footer metadata row */}
      {footerMeta && (
        <div className="border-t border-border px-3 py-2 font-mono text-[11px] text-muted-foreground tabular-nums">
          {footerMeta}
        </div>
      )}
    </div>
  );
});
