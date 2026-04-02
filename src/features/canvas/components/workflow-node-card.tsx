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
} from 'lucide-react';
import type { WorkflowNode, NodeRunRecord } from '@/features/workflows/domain/workflow-types';

// Map category to color
const categoryColors: Record<string, string> = {
  input: 'bg-blue-500',
  script: 'bg-amber-500',
  visuals: 'bg-purple-500',
  audio: 'bg-pink-500',
  video: 'bg-red-500',
  utility: 'bg-gray-500',
  output: 'bg-green-500',
};

const categoryBorderColors: Record<string, string> = {
  input: 'border-blue-500/50',
  script: 'border-amber-500/50',
  visuals: 'border-purple-500/50',
  audio: 'border-pink-500/50',
  video: 'border-red-500/50',
  utility: 'border-gray-500/50',
  output: 'border-green-500/50',
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
  [key: string]: unknown;
}

export type WorkflowNodeType = Node<WorkflowNodeData, 'workflowNode'>;

/**
 * WorkflowNodeCard - Custom React Flow node component
 *
 * Visual features per plan section 6.3:
 * - Title
 * - Category color/icon accent
 * - Validation badge
 * - Run status badge
 * - Disabled state
 * - Compact port labels
 */
const skipReasonLabels: Record<string, string> = {
  disabled: 'Skipped: node is disabled',
  missingRequiredInputs: 'Skipped: missing required inputs',
  upstreamFailed: 'Skipped: upstream node failed',
};

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
    pending: { icon: Clock, variant: 'secondary', title: 'Pending' },
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
      className="h-5 w-5 p-0 flex items-center justify-center"
      title={entry.title}
    >
      <Icon className={cn('h-3 w-3', entry.className)} />
    </Badge>
  );
}

export const WorkflowNodeCard = memo(function WorkflowNodeCard({
  data,
  selected,
}: NodeProps<WorkflowNodeType>) {
  const {
    node,
    category,
    inputPorts,
    outputPorts,
    validationIssues,
    runStatus,
    skipReason,
    disabled,
    previewText,
  } = data;

  const categoryColor = categoryColors[category] ?? 'bg-gray-500';
  const borderColor = categoryBorderColors[category] ?? 'border-gray-500/50';

  return (
    <div
      className={cn(
        'relative min-w-[180px] max-w-[240px] rounded-lg border-2 bg-card shadow-sm transition-all',
        borderColor,
        selected && 'ring-2 ring-primary ring-offset-2',
        disabled && 'opacity-50',
      )}
    >
      {/* Category accent bar */}
      <div className={cn('h-1.5 rounded-t-md', categoryColor)} />

      {/* Header */}
      <div className="px-3 py-2 border-b">
        <div className="flex items-center justify-between gap-2">
          <span className="font-medium text-sm truncate" title={node.label}>
            {node.label}
          </span>

          {/* Status badges */}
          <div className="flex items-center gap-1 shrink-0">
            {validationIssues != null && validationIssues > 0 && (
              <Badge
                variant="destructive"
                className="h-5 w-5 p-0 flex items-center justify-center"
              >
                <AlertCircle className="h-3 w-3" />
              </Badge>
            )}

            {runStatus && runStatus !== 'idle' && (
              <RunStatusBadge runStatus={runStatus} skipReason={skipReason} />
            )}
          </div>
        </div>

        {/* Preview text */}
        {previewText && (
          <p
            className="text-xs text-muted-foreground mt-1 truncate"
            title={previewText}
          >
            {previewText}
          </p>
        )}
      </div>

      {/* Input ports */}
      {inputPorts.length > 0 && (
        <div className="px-2 py-1 space-y-0.5">
          {inputPorts.map((port) => (
            <div key={port.key} className="relative flex items-center">
              <Handle
                type="target"
                position={Position.Left}
                id={port.key}
                className="!w-3 !h-3 !bg-background !border-2 !border-primary"
              />
              <span className="text-[10px] text-muted-foreground ml-4">
                {port.label}
              </span>
            </div>
          ))}
        </div>
      )}

      {/* Output ports */}
      {outputPorts.length > 0 && (
        <div className="px-2 py-1 border-t space-y-0.5">
          {outputPorts.map((port) => (
            <div key={port.key} className="relative flex items-center justify-end">
              <span className="text-[10px] text-muted-foreground mr-4">
                {port.label}
              </span>
              <Handle
                type="source"
                position={Position.Right}
                id={port.key}
                className="!w-3 !h-3 !bg-background !border-2 !border-primary"
              />
            </div>
          ))}
        </div>
      )}
    </div>
  );
});
