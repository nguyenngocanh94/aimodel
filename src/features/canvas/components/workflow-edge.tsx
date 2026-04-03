import { memo, useState, useCallback } from 'react';
import {
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  type Edge,
  type EdgeProps,
} from '@xyflow/react';
import { cn } from '@/shared/lib/utils';
import { XCircle, Ban } from 'lucide-react';

/* ── Edge type → pill display category ── */
type EdgeDataCategory = 'video' | 'audio' | 'image' | 'data' | 'control';

function resolveCategory(dataType?: string): EdgeDataCategory {
  if (!dataType) return 'data';
  if (dataType.startsWith('video')) return 'video';
  if (dataType.startsWith('audio')) return 'audio';
  if (dataType.startsWith('image') || dataType === 'imageFrame' || dataType === 'imageFrameList' || dataType === 'imageAsset' || dataType === 'imageAssetList') return 'image';
  return 'data';
}

/* ── Pill color lookup — edge type specific colors per design system section 11 ── */
const pillStyles: Record<EdgeDataCategory, string> = {
  video: 'border-amber-400/40 text-amber-300',      // amber VIDEO
  audio: 'border-teal-400/40 text-teal-300',        // teal AUDIO
  image: 'border-cyan-400/40 text-cyan-300',        // cyan IMAGE
  data: 'border-violet-400/40 text-violet-300',    // violet DATA
  control: 'border-border text-muted-foreground', // neutral CONTROL
};

const pillLabels: Record<EdgeDataCategory, string> = {
  video: 'VIDEO',
  audio: 'AUDIO',
  image: 'IMAGE',
  data: 'DATA',
  control: 'CTRL',
};

export interface WorkflowEdgeData {
  readonly validationStatus?: 'valid' | 'invalid' | 'warning';
  readonly carryingData?: boolean;
  readonly lastRunStatus?: 'success' | 'error' | 'cancelled' | null;
  readonly isRunning?: boolean;
  readonly isControl?: boolean;
  readonly disabled?: boolean;
  readonly blocked?: boolean;
  readonly sourceDataType?: string;
  readonly targetDataType?: string;
  // Index signature required by React Flow's Edge<T> constraint
  [key: string]: unknown;
}

export type WorkflowEdgeType = Edge<WorkflowEdgeData, 'workflowEdge'>;

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
  const [hovered, setHovered] = useState(false);

  const validationStatus = data?.validationStatus;
  const isRunning = data?.isRunning ?? false;
  const isControl = data?.isControl ?? false;
  const disabled = data?.disabled ?? false;
  const blocked = data?.blocked ?? false;
  const lastRunStatus = data?.lastRunStatus;
  const sourceDataType = data?.sourceDataType;

  const category = isControl ? 'control' : resolveCategory(sourceDataType);

  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
  });

  const onMouseEnter = useCallback(() => setHovered(true), []);
  const onMouseLeave = useCallback(() => setHovered(false), []);

  /* ── Stroke styling ── */
  const strokeWidth = selected ? 2 : 1.5;
  const strokeDasharray = isControl || disabled || blocked ? '6 4' : undefined;

  return (
    <>
      {/* Invisible wide hit area for hover */}
      <path
        d={edgePath}
        fill="none"
        strokeWidth={20}
        stroke="transparent"
        className="pointer-events-stroke"
        onMouseEnter={onMouseEnter}
        onMouseLeave={onMouseLeave}
      />

      <BaseEdge
        id={id}
        path={edgePath}
        className={cn(
          'transition-[stroke,stroke-width,opacity] duration-150 ease-out',
          /* Default */
          'stroke-border/80',
          /* Hover */
          hovered && 'stroke-foreground/70',
          /* Selected */
          selected && 'stroke-primary',
          /* Invalid */
          validationStatus === 'invalid' && 'stroke-destructive',
          /* Running */
          isRunning && 'stroke-signal animate-execution-trace',
          /* Disabled / blocked */
          (disabled || blocked) && 'opacity-40',
        )}
        style={{
          strokeWidth,
          strokeDasharray,
        }}
        data-testid={`edge-${id}`}
        data-hovered={hovered || undefined}
        data-selected={selected || undefined}
        data-invalid={validationStatus === 'invalid' || undefined}
        data-running={isRunning || undefined}
      />

      {/* Edge label pill + status indicators */}
      <EdgeLabelRenderer>
        <div
          className="nodrag nopan"
          style={{
            position: 'absolute',
            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            pointerEvents: 'all',
          }}
          onMouseEnter={onMouseEnter}
          onMouseLeave={onMouseLeave}
          data-testid={`edge-label-${id}`}
        >
          {/* Type pill — visible on hover or when selected */}
          <div
            className={cn(
              'rounded-[3px] border bg-card/95 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-wide shadow-sm backdrop-blur',
              'transition-opacity duration-150 ease-out',
              hovered || selected ? 'opacity-100' : 'opacity-0',
              pillStyles[category],
            )}
            data-type={category}
          >
            {pillLabels[category]}
          </div>

          {/* Error marker at midpoint */}
          {validationStatus === 'invalid' && (
            <div
              className="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center justify-center w-5 h-5 rounded-full bg-background border-2 border-destructive text-destructive"
              title={
                data?.sourceDataType && data?.targetDataType
                  ? `Incompatible: expected ${data.targetDataType}`
                  : 'Incompatible connection'
              }
            >
              <XCircle className="w-3 h-3" aria-hidden="true" />
            </div>
          )}

          {/* Error last-run marker */}
          {lastRunStatus === 'error' && validationStatus !== 'invalid' && (
            <div
              className="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center justify-center w-5 h-5 rounded-full bg-background border-2 border-destructive text-destructive"
              title="Last run failed here"
            >
              <XCircle className="w-3 h-3" aria-hidden="true" />
            </div>
          )}

          {/* Cancelled last-run marker — distinct from failed */}
          {lastRunStatus === 'cancelled' && validationStatus !== 'invalid' && (
            <div
              className="absolute -top-3 left-1/2 -translate-x-1/2 flex items-center justify-center w-5 h-5 rounded-full bg-background border-2 border-warning text-warning"
              title="Run was cancelled"
            >
              <Ban className="w-3 h-3" aria-hidden="true" />
            </div>
          )}
        </div>
      </EdgeLabelRenderer>
    </>
  );
});
