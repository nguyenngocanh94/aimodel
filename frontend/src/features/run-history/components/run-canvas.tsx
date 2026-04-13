/**
 * RunCanvas - AiModel-637
 * Read-only canvas view of workflow execution for run details page.
 * Shows nodes in their original positions with execution status,
 * port handles (matching the editor), and proper bezier edges.
 */

import { useCallback, useMemo } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  Handle,
  Position,
  BaseEdge,
  EdgeLabelRenderer,
  getBezierPath,
  useReactFlow,
  ReactFlowProvider,
  type Node,
  type NodeTypes,
  type EdgeTypes,
  type EdgeProps,
  type Edge,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { getTemplate } from '@/features/node-registry/node-registry';
import type { WorkflowNode, WorkflowEdge, NodeRunRecord } from '@/features/workflows/domain/workflow-types';
import { cn } from '@/shared/lib/utils';
import {
  CheckCircle2,
  XCircle,
  Loader2,
  Clock,
  SkipForward,
  StopCircle,
  PauseCircle,
} from 'lucide-react';

// ============================================================
// Types
// ============================================================

interface RunCanvasProps {
  readonly nodes: readonly WorkflowNode[];
  readonly edges: readonly WorkflowEdge[];
  readonly nodeRunRecords: Readonly<Record<string, NodeRunRecord>>;
  readonly selectedNodeId: string | null;
  readonly onNodeClick: (nodeId: string) => void;
}

interface ReadOnlyNodeData extends Record<string, unknown> {
  readonly node: WorkflowNode;
  readonly category: string;
  readonly inputPorts: readonly { key: string; label: string }[];
  readonly outputPorts: readonly { key: string; label: string }[];
  readonly connectedPorts: ReadonlySet<string>;
  readonly runStatus: NodeRunRecord['status'] | 'idle';
  readonly startedAt?: string;
  readonly completedAt?: string;
  readonly durationMs?: number;
  readonly usedCache?: boolean;
  readonly isSelected: boolean;
  readonly errorMessage?: string;
  readonly skipReason?: string;
}

type ReadOnlyNodeType = Node<ReadOnlyNodeData, 'readOnlyNode'>;

interface ReadOnlyEdgeData {
  readonly sourceDataType?: string;
  readonly sourceStatus?: NodeRunRecord['status'];
  readonly targetStatus?: NodeRunRecord['status'];
  [key: string]: unknown;
}

type ReadOnlyEdgeType = Edge<ReadOnlyEdgeData, 'readOnlyEdge'>;

// ============================================================
// Category colors (matching WorkflowNodeCard)
// ============================================================

const categoryAccentBorder: Record<string, string> = {
  input: 'border-l-node-input',
  script: 'border-l-node-script',
  visuals: 'border-l-node-visuals',
  audio: 'border-l-node-audio',
  video: 'border-l-node-video',
  utility: 'border-l-node-utility',
  output: 'border-l-node-output',
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

// ============================================================
// Status configuration
// ============================================================

const statusConfig: Record<
  NodeRunRecord['status'] | 'idle',
  { icon: React.ReactNode; className: string; bgClass: string; label: string }
> = {
  idle: {
    icon: <Clock className="h-3 w-3" />,
    className: 'text-muted-foreground',
    bgClass: 'bg-muted',
    label: 'Not executed',
  },
  pending: {
    icon: <Clock className="h-3 w-3" />,
    className: 'text-muted-foreground',
    bgClass: 'bg-gray-100 dark:bg-gray-800',
    label: 'Pending',
  },
  running: {
    icon: <Loader2 className="h-3 w-3 animate-spin" />,
    className: 'text-blue-600',
    bgClass: 'bg-blue-50 dark:bg-blue-900/20',
    label: 'Running',
  },
  success: {
    icon: <CheckCircle2 className="h-3 w-3" />,
    className: 'text-green-600',
    bgClass: 'bg-green-50 dark:bg-green-900/20',
    label: 'Success',
  },
  error: {
    icon: <XCircle className="h-3 w-3" />,
    className: 'text-red-600',
    bgClass: 'bg-red-50 dark:bg-red-900/20',
    label: 'Error',
  },
  skipped: {
    icon: <SkipForward className="h-3 w-3" />,
    className: 'text-gray-500',
    bgClass: 'bg-gray-50 dark:bg-gray-900/20',
    label: 'Skipped',
  },
  cancelled: {
    icon: <StopCircle className="h-3 w-3" />,
    className: 'text-yellow-600',
    bgClass: 'bg-yellow-50 dark:bg-yellow-900/20',
    label: 'Cancelled',
  },
  awaitingReview: {
    icon: <PauseCircle className="h-3 w-3" />,
    className: 'text-orange-600',
    bgClass: 'bg-orange-50 dark:bg-orange-900/20',
    label: 'Awaiting Review',
  },
};

function formatDuration(durationMs?: number): string {
  if (durationMs === undefined) return '';
  if (durationMs < 1000) return `${durationMs}ms`;
  const seconds = Math.floor(durationMs / 1000);
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}m ${remainingSeconds}s`;
}

function formatTimestamp(dateStr?: string): string {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

// ============================================================
// Read-only node component (editor-style with ports)
// ============================================================

function ReadOnlyNodeCard({ data }: { readonly data: ReadOnlyNodeData }) {
  const {
    node, category, inputPorts, outputPorts, connectedPorts,
    runStatus, startedAt, completedAt, durationMs, usedCache,
    isSelected, errorMessage, skipReason,
  } = data;
  const status = statusConfig[runStatus];
  const accentBorder = categoryAccentBorder[category] ?? 'border-l-node-utility';
  const iconTint = categoryIconTint[category] ?? 'text-node-utility';

  return (
    <div
      className={cn(
        // Base styles (matching editor WorkflowNodeCard)
        'w-[260px] rounded-lg border border-l-[3px] bg-card text-card-foreground',
        'shadow-[0_2px_8px_rgba(0,0,0,0.3)] cursor-pointer',
        'transition-[border-color,box-shadow] duration-150 ease-out',
        'border-border',
        // Category accent
        accentBorder,
        // Selection state
        isSelected && 'border-[1.5px] border-primary ring-1 ring-primary/20 shadow-[0_2px_12px_rgba(0,0,0,0.35)]',
        // Hover state
        !isSelected && 'hover:-translate-y-px hover:shadow-[0_4px_12px_rgba(0,0,0,0.4)]',
        // Status-based border
        runStatus === 'error' && 'border-2 border-destructive/50',
        runStatus === 'running' && 'border-l-signal',
      )}
      data-testid={`run-node-${node.id}`}
      data-selected={isSelected || undefined}
    >
      {/* Header */}
      <div className="flex items-start gap-2 px-3 pb-2 pt-3">
        <div className={cn('mt-0.5 shrink-0', iconTint)} aria-hidden="true">
          <div className="h-4 w-4 rounded-sm bg-current opacity-40" />
        </div>

        <div className="min-w-0 flex-1">
          <h3 className="truncate text-[13px] font-semibold">{node.label}</h3>
          <p className="truncate font-mono text-[10px] text-muted-foreground">
            {category.toUpperCase()} · {node.type}
          </p>
        </div>
      </div>

      {/* Status bar with timestamp */}
      <div
        className={cn(
          'flex items-center justify-between px-3 py-2 border-t',
          status.bgClass,
          runStatus === 'idle' ? 'border-border' : 'border-transparent',
        )}
      >
        <div className={cn('flex items-center gap-1.5 text-xs font-medium', status.className)}>
          {status.icon}
          <span>{status.label}</span>
        </div>

        <div className="flex items-center gap-2">
          {usedCache && (
            <span className="text-[9px] px-1.5 py-0.5 rounded bg-primary/10 text-primary">
              Cache
            </span>
          )}
          {durationMs !== undefined && (
            <span className="text-[10px] font-mono text-muted-foreground">
              {formatDuration(durationMs)}
            </span>
          )}
        </div>
      </div>

      {/* Timestamps */}
      {(startedAt || completedAt) && (
        <div className="px-3 py-1.5 border-t border-border/50 bg-muted/30">
          <div className="flex items-center gap-3 text-[10px] text-muted-foreground font-mono">
            {startedAt && <span>{formatTimestamp(startedAt)}</span>}
            {startedAt && completedAt && <span>→</span>}
            {completedAt && <span>{formatTimestamp(completedAt)}</span>}
          </div>
        </div>
      )}

      {/* Inline error */}
      {runStatus === 'error' && errorMessage && (
        <div className="mx-3 mb-2 rounded-sm bg-destructive/[0.06] px-2 py-1.5 flex items-start gap-1.5">
          <XCircle className="h-3 w-3 shrink-0 text-destructive mt-px" aria-hidden="true" />
          <span className="text-[11px] text-destructive line-clamp-2">{errorMessage}</span>
        </div>
      )}

      {/* Skip reason */}
      {runStatus === 'skipped' && skipReason && (
        <div className="mx-3 mb-2 rounded-sm bg-muted px-2 py-1.5 flex items-start gap-1.5">
          <SkipForward className="h-3 w-3 shrink-0 text-muted-foreground mt-px" aria-hidden="true" />
          <span className="text-[11px] text-muted-foreground line-clamp-2">{skipReason}</span>
        </div>
      )}

      {/* Input port rail */}
      {inputPorts.length > 0 && (
        <div className="border-t border-border px-2 py-1 space-y-0.5">
          {inputPorts.map((port) => (
            <div key={port.key} className="relative flex items-center" data-testid={`run-port-in-${node.id}-${port.key}`}>
              <Handle
                type="target"
                position={Position.Left}
                id={port.key}
                className={cn(
                  '!w-2.5 !h-2.5 !border-2 !border-primary !transition-shadow !duration-150',
                  connectedPorts.has(`in:${port.key}`) ? '!bg-primary' : '!bg-background',
                )}
                isConnectable={false}
              />
              <span className="font-mono text-[11px] text-muted-foreground ml-4">
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
            <div key={port.key} className="relative flex items-center justify-end" data-testid={`run-port-out-${node.id}-${port.key}`}>
              <span className="font-mono text-[11px] text-muted-foreground mr-4">
                {port.label}
              </span>
              <Handle
                type="source"
                position={Position.Right}
                id={port.key}
                className={cn(
                  '!w-2.5 !h-2.5 !border-2 !border-cyan-400 !transition-shadow !duration-150',
                  connectedPorts.has(`out:${port.key}`) ? '!bg-cyan-400' : '!bg-background',
                )}
                isConnectable={false}
              />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ============================================================
// Edge data type → category (matching editor)
// ============================================================

type EdgeDataCategory = 'video' | 'audio' | 'image' | 'data';

function resolveCategory(dataType?: string): EdgeDataCategory {
  if (!dataType) return 'data';
  if (dataType.startsWith('video')) return 'video';
  if (dataType.startsWith('audio')) return 'audio';
  if (dataType.startsWith('image') || dataType === 'imageFrame' || dataType === 'imageFrameList' || dataType === 'imageAsset' || dataType === 'imageAssetList') return 'image';
  return 'data';
}

const strokeStyles: Record<EdgeDataCategory, string> = {
  video: 'stroke-amber-400/50',
  audio: 'stroke-teal-400/50',
  image: 'stroke-cyan-400/50',
  data: 'stroke-violet-400/40',
};

const pillStyles: Record<EdgeDataCategory, string> = {
  video: 'border-amber-400/40 text-amber-300',
  audio: 'border-teal-400/40 text-teal-300',
  image: 'border-cyan-400/40 text-cyan-300',
  data: 'border-violet-400/40 text-violet-300',
};

const pillLabels: Record<EdgeDataCategory, string> = {
  video: 'VIDEO',
  audio: 'AUDIO',
  image: 'IMAGE',
  data: 'DATA',
};

// ============================================================
// Read-only bezier edge (matching editor style)
// ============================================================

function ReadOnlyEdge({
  id,
  sourceX,
  sourceY,
  targetX,
  targetY,
  sourcePosition,
  targetPosition,
  data,
}: EdgeProps<ReadOnlyEdgeType>) {
  const sourceDataType = data?.sourceDataType;
  const category = resolveCategory(sourceDataType);

  const [edgePath, labelX, labelY] = getBezierPath({
    sourceX,
    sourceY,
    sourcePosition,
    targetX,
    targetY,
    targetPosition,
  });

  // Determine edge status from connected node statuses
  const sourceStatus = data?.sourceStatus;
  const isRunning = sourceStatus === 'running';
  const isError = sourceStatus === 'error';
  const isSuccess = sourceStatus === 'success';

  return (
    <>
      <BaseEdge
        id={id}
        path={edgePath}
        className={cn(
          'transition-[stroke,stroke-width,opacity] duration-150 ease-out',
          strokeStyles[category],
          isRunning && 'stroke-signal animate-execution-trace',
          isError && 'stroke-destructive/60',
          isSuccess && 'stroke-success/60',
        )}
        style={{
          strokeWidth: 2,
          strokeDasharray: isRunning ? '12 8' : undefined,
        }}
      />

      <EdgeLabelRenderer>
        <div
          className="nodrag nopan"
          style={{
            position: 'absolute',
            transform: `translate(-50%, -50%) translate(${labelX}px,${labelY}px)`,
            pointerEvents: 'none',
          }}
        >
          <div
            className={cn(
              'rounded-[3px] border bg-card/95 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-wide shadow-sm backdrop-blur opacity-60',
              pillStyles[category],
            )}
          >
            {pillLabels[category]}
          </div>
        </div>
      </EdgeLabelRenderer>
    </>
  );
}

const nodeTypes: NodeTypes = {
  readOnlyNode: ReadOnlyNodeCard,
};

const edgeTypes: EdgeTypes = {
  readOnlyEdge: ReadOnlyEdge as unknown as EdgeTypes[string],
};

// ============================================================
// Main component
// ============================================================

function RunCanvasInner({
  nodes,
  edges,
  nodeRunRecords,
  selectedNodeId,
  onNodeClick,
}: RunCanvasProps) {
  const { fitView } = useReactFlow();

  // Build connected ports map
  const connectedByNode = useMemo(() => {
    const map = new Map<string, Set<string>>();
    for (const edge of edges) {
      let srcSet = map.get(edge.sourceNodeId);
      if (!srcSet) { srcSet = new Set(); map.set(edge.sourceNodeId, srcSet); }
      srcSet.add(`out:${edge.sourcePortKey}`);
      let tgtSet = map.get(edge.targetNodeId);
      if (!tgtSet) { tgtSet = new Set(); map.set(edge.targetNodeId, tgtSet); }
      tgtSet.add(`in:${edge.targetPortKey}`);
    }
    return map;
  }, [edges]);

  const emptySet = useMemo(() => new Set<string>(), []);

  // Convert workflow nodes to React Flow nodes
  const reactFlowNodes = useMemo(() => {
    return nodes.map((node) => {
      const template = getTemplate(node.type);
      const category = template?.category ?? 'utility';
      const record = nodeRunRecords[node.id];

      const inputPorts = (template?.inputs ?? []).map((p) => ({
        key: p.key,
        label: p.label,
      }));
      const outputPorts = (template?.outputs ?? []).map((p) => ({
        key: p.key,
        label: p.label,
      }));

      return {
        id: node.id,
        type: 'readOnlyNode' as const,
        position: { x: node.position.x, y: node.position.y },
        data: {
          node,
          category,
          inputPorts,
          outputPorts,
          connectedPorts: connectedByNode.get(node.id) ?? emptySet,
          runStatus: record?.status ?? 'idle',
          startedAt: record?.startedAt,
          completedAt: record?.completedAt,
          durationMs: record?.durationMs,
          usedCache: record?.usedCache,
          isSelected: selectedNodeId === node.id,
          errorMessage: record?.errorMessage,
          skipReason: record?.skipReason,
        } as ReadOnlyNodeData,
        draggable: false,
        selectable: true,
        connectable: false,
      } satisfies ReadOnlyNodeType;
    });
  }, [nodes, nodeRunRecords, selectedNodeId, connectedByNode, emptySet]);

  // Convert workflow edges to React Flow edges with proper port handles
  const reactFlowEdges = useMemo(() => {
    return edges.map((edge) => {
      // Look up source port data type for edge styling
      const sourceNode = nodes.find((n) => n.id === edge.sourceNodeId);
      const sourceTemplate = sourceNode ? getTemplate(sourceNode.type) : null;
      const sourcePort = sourceTemplate?.outputs.find((p) => p.key === edge.sourcePortKey);
      const sourceRecord = nodeRunRecords[edge.sourceNodeId];

      return {
        id: edge.id,
        source: edge.sourceNodeId,
        target: edge.targetNodeId,
        sourceHandle: edge.sourcePortKey,
        targetHandle: edge.targetPortKey,
        type: 'readOnlyEdge' as const,
        selectable: false,
        data: {
          sourceDataType: sourcePort?.dataType,
          sourceStatus: sourceRecord?.status,
        } as ReadOnlyEdgeData,
      } satisfies ReadOnlyEdgeType;
    });
  }, [edges, nodes, nodeRunRecords]);

  // Fit view on mount
  useCallback(() => {
    fitView({ padding: 0.2, duration: 300 });
  }, [fitView]);

  // Handle node click
  const handleNodeClick = useCallback(
    (_event: React.MouseEvent, node: Node) => {
      onNodeClick(node.id);
    },
    [onNodeClick],
  );

  return (
    <ReactFlow
      nodes={reactFlowNodes}
      edges={reactFlowEdges}
      nodeTypes={nodeTypes}
      edgeTypes={edgeTypes}
      onNodeClick={handleNodeClick}
      draggable={false}
      panOnDrag={true}
      zoomOnScroll={true}
      zoomOnPinch={true}
      zoomOnDoubleClick={false}
      selectNodesOnDrag={false}
      selectionOnDrag={false}
      panOnScroll={true}
      fitView
      fitViewOptions={{ padding: 0.2, duration: 300 }}
      proOptions={{ hideAttribution: true }}
    >
      <Background gap={15} size={1.5} color="#1E2536" />
      <Controls
        className="!bg-card !border !border-border !rounded-md !shadow-sm [&>button]:!bg-card [&>button]:!text-foreground [&>button]:!border-border [&>button:hover]:!bg-accent"
      />
      <MiniMap
        className="!bg-card !border-border !shadow-lg"
        maskColor="rgba(0,0,0,0.2)"
        nodeColor={(node) => {
          const status = (node.data as ReadOnlyNodeData).runStatus;
          switch (status) {
            case 'success': return '#22c55e';
            case 'error': return '#ef4444';
            case 'running': return '#3b82f6';
            case 'skipped': return '#9ca3af';
            case 'cancelled': return '#eab308';
            case 'awaitingReview': return '#f97316';
            default: return '#64748b';
          }
        }}
      />
    </ReactFlow>
  );
}

// ============================================================
// Exported component with provider
// ============================================================

export function RunCanvas(props: RunCanvasProps) {
  return (
    <ReactFlowProvider>
      <RunCanvasInner {...props} />
    </ReactFlowProvider>
  );
}
