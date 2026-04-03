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
        isError && 'border-destructive/60',
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
                  '!w-2 !h-2 !bg-background !border-[1.5px] !border-primary',
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
                  '!w-2 !h-2 !bg-background !border-[1.5px] !border-cyan-400',
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
