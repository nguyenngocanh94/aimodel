import { memo } from 'react';
import {
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  type Edge,
  type EdgeProps,
} from '@xyflow/react';
import { cn } from '@/shared/lib/utils';
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-react';

export interface WorkflowEdgeData {
  readonly validationStatus?: 'valid' | 'invalid' | 'warning';
  readonly carryingData?: boolean;
  readonly lastRunStatus?: 'success' | 'error' | null;
  [key: string]: unknown;
}

export type WorkflowEdgeType = Edge<WorkflowEdgeData, 'workflowEdge'>;

/**
 * WorkflowEdge - Custom React Flow edge component
 *
 * Visual states per plan section 6.3:
 * - default
 * - selected
 * - invalid
 * - warning
 * - carrying data
 * - last-run success/error indicators
 */
export const WorkflowEdge = memo(function WorkflowEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  selected,
  data,
}: EdgeProps<WorkflowEdgeType>) {
  const validationStatus = data?.validationStatus;
  const carryingData = data?.carryingData;
  const lastRunStatus = data?.lastRunStatus;

  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
  });

  // Determine edge styling based on state
  let strokeColor = 'stroke-border';
  let strokeWidth = 2;

  if (selected) {
    strokeColor = 'stroke-primary';
    strokeWidth = 3;
  } else if (validationStatus === 'invalid') {
    strokeColor = 'stroke-destructive';
  } else if (validationStatus === 'warning') {
    strokeColor = 'stroke-amber-500';
  } else if (carryingData) {
    strokeColor = 'stroke-blue-500';
  } else if (lastRunStatus === 'success') {
    strokeColor = 'stroke-green-500';
  } else if (lastRunStatus === 'error') {
    strokeColor = 'stroke-destructive';
  }

  const showIndicator = validationStatus || carryingData || lastRunStatus;

  return (
    <>
      <BaseEdge
        id={id}
        path={edgePath}
        className={cn(strokeColor)}
        style={{ strokeWidth }}
      />

      {/* Status indicators on edge */}
      {showIndicator && (
        <EdgeLabelRenderer>
          <div
            className="nodrag nopan pointer-events-none"
            style={{
              position: 'absolute',
              transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            }}
          >
            <div
              className={cn(
                'flex items-center justify-center w-5 h-5 rounded-full bg-background border-2',
                validationStatus === 'invalid'
                  ? 'border-destructive text-destructive'
                  : validationStatus === 'warning'
                    ? 'border-amber-500 text-amber-500'
                    : lastRunStatus === 'success'
                      ? 'border-green-500 text-green-500'
                      : lastRunStatus === 'error'
                        ? 'border-destructive text-destructive'
                        : carryingData
                          ? 'border-blue-500 text-blue-500'
                          : 'border-border',
              )}
            >
              {validationStatus === 'invalid' && <XCircle className="w-3 h-3" />}
              {validationStatus === 'warning' && (
                <AlertTriangle className="w-3 h-3" />
              )}
              {lastRunStatus === 'success' && (
                <CheckCircle2 className="w-3 h-3" />
              )}
              {lastRunStatus === 'error' && <XCircle className="w-3 h-3" />}
              {carryingData && !validationStatus && !lastRunStatus && (
                <div className="w-2 h-2 rounded-full bg-current" />
              )}
            </div>
          </div>
        </EdgeLabelRenderer>
      )}
    </>
  );
});
