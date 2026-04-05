/**
 * DataInspectorPanel - AiModel-1n1.3
 * First-class data inspection panel per plan section 6.5.
 *
 * Modes:
 * - Node selected: show inputs/outputs with metadata
 * - Edge selected: show EdgePayloadSnapshot with coercion info
 * - Workflow selected / nothing: run summary or instructions
 */

import { useMemo } from 'react'
import {
  ArrowRightLeft,
  Database,
  Layers,
  BarChart3,
} from 'lucide-react'
import { Badge } from '@/shared/ui/badge'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import {
  selectDocument,
  selectSelectedNodeIds,
  selectSelectedEdgeId,
} from '@/features/workflow/store/workflow-selectors'
import { useRunStore } from '@/features/execution/store/run-store'
import {
  selectNodeRunRecords,
  selectEdgePayloadSnapshots,
  selectActiveRun,
  selectRecentRuns,
} from '@/features/execution/store/run-selectors'
import { computeAllPreviews } from '@/features/workflows/domain/preview-engine'
import type {
  WorkflowNode,
  WorkflowEdge,
  PortPayload,
  EdgePayloadSnapshot,
  NodeRunRecord,
} from '@/features/workflows/domain/workflow-types'
import { PayloadViewer, type PayloadSource } from './payload-viewer'
import { LineageView } from './lineage-view'

// ============================================================
// Main panel
// ============================================================

export function DataInspectorPanel() {
  const document = useWorkflowStore(selectDocument)
  const selectedNodeIds = useWorkflowStore(selectSelectedNodeIds)
  const selectedEdgeId = useWorkflowStore(selectSelectedEdgeId)

  const selectedNode = useMemo(() => {
    if (selectedNodeIds.length !== 1) return null
    return document.nodes.find((n) => n.id === selectedNodeIds[0]) ?? null
  }, [document.nodes, selectedNodeIds])

  const selectedEdge = useMemo(() => {
    if (!selectedEdgeId) return null
    return document.edges.find((e) => e.id === selectedEdgeId) ?? null
  }, [document.edges, selectedEdgeId])

  if (selectedNode) {
    return <NodeDataView node={selectedNode} />
  }

  if (selectedEdge) {
    return <EdgeDataView edge={selectedEdge} />
  }

  return <WorkflowSummaryView />
}

// ============================================================
// Node data view
// ============================================================

function NodeDataView({ node }: { readonly node: WorkflowNode }) {
  const document = useWorkflowStore(selectDocument)
  const nodeRunRecords = useRunStore(selectNodeRunRecords)

  const allPreviews = useMemo(() => computeAllPreviews(document), [document])
  const previewOutputs = allPreviews.get(node.id)
  const runRecord = nodeRunRecords[node.id]

  // Determine source: prefer run output, fall back to preview
  const hasRunOutput = runRecord && runRecord.status === 'success' && Object.keys(runRecord.outputPayloads).length > 0
  const source: PayloadSource = hasRunOutput ? 'lastRun' : 'preview'

  const outputs: Record<string, PortPayload> = hasRunOutput
    ? runRecord.outputPayloads as Record<string, PortPayload>
    : previewOutputs ?? {}

  const inputs: Record<string, PortPayload> = hasRunOutput
    ? runRecord.inputPayloads as Record<string, PortPayload>
    : {}

  return (
    <div className="space-y-3 p-3" data-testid="data-inspector-node">
      <div className="flex items-center gap-2">
        <Database className="h-3.5 w-3.5 text-muted-foreground" />
        <span className="text-xs font-medium">{node.label}</span>
        <Badge variant="outline" className="text-[9px] h-4 px-1 font-mono">
          {node.type}
        </Badge>
      </div>

      {/* Input payloads */}
      {Object.keys(inputs).length > 0 && (
        <div className="space-y-1.5">
          <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            Inputs
          </h4>
          {Object.entries(inputs).map(([key, payload]) => (
            <PayloadViewer key={key} portKey={key} payload={payload} source={source} />
          ))}
        </div>
      )}

      {/* Output payloads */}
      {Object.keys(outputs).length > 0 ? (
        <div className="space-y-1.5">
          <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            Outputs
          </h4>
          {Object.entries(outputs).map(([key, payload]) => (
            <PayloadViewer key={key} portKey={key} payload={payload} source={source} />
          ))}
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          {source === 'preview'
            ? 'No preview available. Connect inputs or configure node.'
            : 'This node has not produced output yet.'}
        </p>
      )}

      {/* Run record info */}
      {runRecord && (
        <RunRecordSummary record={runRecord} />
      )}

      {/* Lineage */}
      <LineageView nodeId={node.id} document={document} />
    </div>
  )
}

// ============================================================
// Edge data view
// ============================================================

function EdgeDataView({ edge }: { readonly edge: WorkflowEdge }) {
  const document = useWorkflowStore(selectDocument)
  const edgePayloadSnapshots = useRunStore(selectEdgePayloadSnapshots)

  const snapshot = edgePayloadSnapshots[edge.id]
  const sourceNode = document.nodes.find((n) => n.id === edge.sourceNodeId)
  const targetNode = document.nodes.find((n) => n.id === edge.targetNodeId)

  return (
    <div className="space-y-3 p-3" data-testid="data-inspector-edge">
      <div className="flex items-center gap-2">
        <ArrowRightLeft className="h-3.5 w-3.5 text-muted-foreground" />
        <span className="text-xs font-medium">{sourceNode?.label ?? edge.sourceNodeId}</span>
        <span className="rounded-sm border border-cyan-400/40 bg-card px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-wide text-cyan-400">
          {edge.sourcePortKey}
        </span>
      </div>

      {/* Connection info */}
      <div className="text-[10px] text-muted-foreground space-y-0.5">
        <div>
          <span className="font-mono">{sourceNode?.label ?? edge.sourceNodeId}</span>
          <span>.{edge.sourcePortKey}</span>
        </div>
        <div className="pl-2">→</div>
        <div>
          <span className="font-mono">{targetNode?.label ?? edge.targetNodeId}</span>
          <span>.{edge.targetPortKey}</span>
        </div>
      </div>

      {/* EdgePayloadSnapshot */}
      {snapshot ? (
        <EdgeSnapshotView snapshot={snapshot} />
      ) : (
        <p className="text-xs text-muted-foreground">
          No payload snapshot available. Run the workflow to capture edge data.
        </p>
      )}
    </div>
  )
}

function EdgeSnapshotView({ snapshot }: { readonly snapshot: EdgePayloadSnapshot }) {
  return (
    <div className="space-y-2">
      <div className="space-y-1.5">
        <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
          Source Output
        </h4>
        <PayloadViewer
          portKey="source"
          payload={snapshot.sourcePayload}
          source="lastRun"
          showProducer={false}
        />
      </div>

      {snapshot.coercionApplied && (
        <div className="flex items-center gap-1 text-[10px] text-amber-600">
          <Badge variant="outline" className="text-[9px] h-4 px-1 border-amber-400">
            Coercion: {snapshot.coercionApplied}
          </Badge>
        </div>
      )}

      <div className="space-y-1.5">
        <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
          Transported (as received)
        </h4>
        <PayloadViewer
          portKey="transported"
          payload={snapshot.transportedPayload}
          source="lastRun"
          showProducer={false}
        />
      </div>
    </div>
  )
}

// ============================================================
// Workflow summary view
// ============================================================

function WorkflowSummaryView() {
  const activeRun = useRunStore(selectActiveRun)
  const recentRuns = useRunStore(selectRecentRuns)
  const nodeRunRecords = useRunStore(selectNodeRunRecords)

  const statusCounts = useMemo(() => {
    const records = Object.values(nodeRunRecords)
    return {
      success: records.filter((r) => r.status === 'success').length,
      error: records.filter((r) => r.status === 'error').length,
      pending: records.filter((r) => r.status === 'pending').length,
      running: records.filter((r) => r.status === 'running').length,
      skipped: records.filter((r) => r.status === 'skipped').length,
      total: records.length,
    }
  }, [nodeRunRecords])

  if (!activeRun && recentRuns.length === 0) {
    return (
      <div className="p-3 space-y-2" data-testid="data-inspector-empty">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Layers className="h-4 w-4" />
          <span className="text-xs font-medium">Data Inspector</span>
        </div>
        <p className="text-xs text-muted-foreground">
          Select a node to inspect its inputs and outputs.
        </p>
        <p className="text-xs text-muted-foreground">
          Select an edge to inspect its payload snapshot.
        </p>
        <p className="text-xs text-muted-foreground">
          Run the workflow in mock mode to generate payloads.
        </p>
      </div>
    )
  }

  return (
    <div className="p-3 space-y-3" data-testid="data-inspector-summary">
      <div className="flex items-center gap-2">
        <BarChart3 className="h-3.5 w-3.5 text-muted-foreground" />
        <span className="text-xs font-medium">Run Summary</span>
        {activeRun && (
          <Badge variant="secondary" className="text-[9px] h-4 px-1">
            {activeRun.status}
          </Badge>
        )}
      </div>

      {statusCounts.total > 0 && (
        <div className="grid grid-cols-3 gap-2 text-center">
          <StatBox label="Success" value={statusCounts.success} color="text-success" />
          <StatBox label="Error" value={statusCounts.error} color="text-destructive" />
          <StatBox label="Pending" value={statusCounts.pending + statusCounts.running} color="text-muted-foreground" />
        </div>
      )}

      {recentRuns.length > 0 && (
        <div className="space-y-1">
          <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            Recent Runs
          </h4>
          {recentRuns.slice(0, 5).map((run) => (
            <div key={run.id} className="flex items-center justify-between text-[10px]">
              <span className="font-mono truncate">{run.id.slice(0, 12)}</span>
              <div className="flex items-center gap-1">
                <Badge
                  variant={run.status === 'success' ? 'default' : run.status === 'error' ? 'destructive' : 'secondary'}
                  className="text-[9px] h-4 px-1"
                >
                  {run.status}
                </Badge>
                <span className="text-muted-foreground">
                  {run.startedAt ? new Date(run.startedAt).toLocaleTimeString() : ''}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

function StatBox({
  label,
  value,
  color,
}: {
  readonly label: string
  readonly value: number
  readonly color: string
}) {
  return (
    <div className="border border-border rounded-md p-1.5">
      <div className={`text-base font-semibold ${color}`}>{value}</div>
      <div className="text-[10px] text-muted-foreground">{label}</div>
    </div>
  )
}

// ============================================================
// Run record summary
// ============================================================

function RunRecordSummary({ record }: { readonly record: NodeRunRecord }) {
  return (
    <div className="space-y-1">
      <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
        Run Record
      </h4>
      <div className="grid grid-cols-2 gap-x-3 gap-y-0.5 text-[10px]">
        <span className="text-muted-foreground">Status</span>
        <Badge
          variant={record.status === 'success' ? 'default' : record.status === 'error' ? 'destructive' : 'secondary'}
          className="text-[9px] h-4 px-1 w-fit"
        >
          {record.status}
        </Badge>
        {record.durationMs !== undefined && (
          <>
            <span className="text-muted-foreground">Duration</span>
            <span>{record.durationMs}ms</span>
          </>
        )}
        {record.usedCache && (
          <>
            <span className="text-muted-foreground">Cache</span>
            <span className="text-success">Hit</span>
          </>
        )}
        {record.errorMessage && (
          <>
            <span className="text-muted-foreground">Error</span>
            <span className="text-destructive">{record.errorMessage}</span>
          </>
        )}
      </div>
    </div>
  )
}
