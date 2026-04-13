import { useState, useCallback, useMemo } from 'react'
import { useNavigate, useParams } from '@tanstack/react-router'
import { ArrowLeft, Loader2, Clock } from 'lucide-react'

import { AppHeader } from '@/app/layout/app-header'
import { Button } from '@/shared/ui/button'
import { useWorkflow, useRun } from '@/shared/api/queries'
import { RunStatusBadge } from '@/features/run-history/components/run-status-badge'
import { RunCanvas } from '@/features/run-history/components/run-canvas'
import { RunNodeInspector } from '@/features/run-history/components/run-node-inspector'
import type { ExecutionRun } from '@/shared/api/schemas'
import type { Workflow } from '@/shared/api/schemas'
import type { NodeRunRecord } from '@/features/workflows/domain/workflow-types'
import type { WorkflowNode } from '@/features/workflows/domain/workflow-types'

function formatDuration(startedAt?: string, completedAt?: string): string {
  if (!startedAt) return '-'
  const start = new Date(startedAt).getTime()
  const end = completedAt ? new Date(completedAt).getTime() : Date.now()
  const ms = end - start
  if (ms < 1000) return `${Math.round(ms)}ms`
  const seconds = Math.floor(ms / 1000)
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes}m ${remainingSeconds}s`
}

/**
 * RunDetailsPage - Shows detailed view of a single workflow run
 * Editor-style canvas with node selection → inspector panel
 *
 * Route: /workflows/$workflowId/runs/$runId
 */
export function RunDetailsPage() {
  const { workflowId, runId } = useParams({
    from: '/workflows/$workflowId/runs/$runId',
  })
  const navigate = useNavigate()

  const { data: workflowData } = useWorkflow(workflowId)
  const {
    data: runData,
    isLoading,
    isError,
    error,
  } = useRun(runId)

  const workflowName =
    (workflowData as { data?: { name?: string } })?.data?.name ??
    `Workflow ${workflowId.slice(0, 8)}`

  const workflowData2 = workflowData as { data?: Workflow }

  const workflowNodes = useMemo(
    () => workflowData2?.data?.document?.nodes ?? [],
    [workflowData2?.data?.document?.nodes],
  )

  const workflowEdges = useMemo(
    () => workflowData2?.data?.document?.edges ?? [],
    [workflowData2?.data?.document?.edges],
  )

  const run = (runData as { data?: ExecutionRun })?.data

  // Build nodeRunRecords map
  const nodeRunRecords = useMemo(() => {
    const records: Record<string, NodeRunRecord> = {}
    run?.nodeRunRecords?.forEach((record) => {
      records[record.nodeId] = record as NodeRunRecord
    })
    return records
  }, [run?.nodeRunRecords])

  // Node selection state
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null)

  const selectedNode = useMemo<WorkflowNode | null>(
    () => workflowNodes.find((n) => n.id === selectedNodeId) ?? null,
    [workflowNodes, selectedNodeId],
  )

  const selectedRecord = useMemo(
    () => (selectedNodeId ? nodeRunRecords[selectedNodeId] ?? null : null),
    [selectedNodeId, nodeRunRecords],
  )

  // Handle node click on canvas
  const handleNodeClick = useCallback((nodeId: string) => {
    setSelectedNodeId((prev) => (prev === nodeId ? null : nodeId))
  }, [])

  // Handle close inspector
  const handleCloseInspector = useCallback(() => {
    setSelectedNodeId(null)
  }, [])

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader workflowId={workflowId} workflowName={workflowName} />

      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Minimal toolbar */}
        <div className="flex items-center gap-3 border-b px-4 py-2 shrink-0">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate({ to: '/workflows/$workflowId/runs', params: { workflowId } })}
          >
            <ArrowLeft className="mr-1.5 h-4 w-4" />
            Back
          </Button>
          <div className="h-4 w-px bg-border" />
          {run && (
            <>
              <RunStatusBadge status={run.status} />
              <span className="font-mono text-xs text-muted-foreground">
                {run.id.slice(0, 8)}
              </span>
              <div className="h-4 w-px bg-border" />
              <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                <Clock className="h-3 w-3" />
                <span>{formatDuration(run.startedAt, run.completedAt)}</span>
              </div>
              {run.summary && (
                <>
                  <div className="h-4 w-px bg-border" />
                  <div className="flex items-center gap-2 text-xs">
                    <span className="text-green-600 dark:text-green-400">
                      {run.summary.success} ok
                    </span>
                    <span className="text-red-600 dark:text-red-400">
                      {run.summary.error} err
                    </span>
                    <span className="text-muted-foreground">
                      {run.summary.skipped} skip
                    </span>
                  </div>
                </>
              )}
            </>
          )}
        </div>

        {/* Loading state */}
        {isLoading && (
          <div className="flex-1 flex flex-col items-center justify-center gap-3">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            <p className="text-sm text-muted-foreground">Loading run...</p>
          </div>
        )}

        {/* Error state */}
        {isError && (
          <div className="flex-1 flex flex-col items-center justify-center gap-3 px-6">
            <p className="text-sm text-destructive">
              {error instanceof Error ? error.message : 'Failed to load run'}
            </p>
          </div>
        )}

        {/* Canvas + Inspector layout */}
        {run && (
          <div className="flex-1 flex overflow-hidden">
            {/* Canvas area */}
            <div className="flex-1 relative">
              <RunCanvas
                nodes={workflowNodes}
                edges={workflowEdges}
                nodeRunRecords={nodeRunRecords}
                selectedNodeId={selectedNodeId}
                onNodeClick={handleNodeClick}
              />
            </div>

            {/* Inspector panel - shows when a node is selected */}
            {selectedNodeId && (
              <div className="w-[400px] shrink-0 border-l bg-card overflow-hidden">
                <RunNodeInspector
                  node={selectedNode}
                  record={selectedRecord}
                  onClose={handleCloseInspector}
                />
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
