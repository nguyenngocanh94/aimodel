/**
 * RunNodeTimeline - AiModel-635
 * Displays node execution records in order with expandable details.
 */

import { useState, useCallback } from 'react'
import {
  ChevronDown,
  ChevronRight,
  Clock,
  Database,
  AlertCircle,
  SkipForward,
  CheckCircle2,
  XCircle,
  Loader2,
  HelpCircle,
} from 'lucide-react'
import { Badge } from '@/shared/ui/badge'
import { Button } from '@/shared/ui/button'
import { PayloadViewer } from '@/features/data-inspector/components/payload-viewer'
import type { NodeRunRecord } from '@/shared/api/schemas'
import type { WorkflowNode } from '@/shared/api/schemas'

interface RunNodeTimelineProps {
  readonly records: readonly NodeRunRecord[]
  readonly workflowNodes: readonly WorkflowNode[]
}

const statusConfig: Record<
  NodeRunRecord['status'],
  { icon: React.ReactNode; className: string; label: string }
> = {
  pending: {
    icon: <HelpCircle className="h-4 w-4" />,
    className: 'text-muted-foreground',
    label: 'Pending',
  },
  running: {
    icon: <Loader2 className="h-4 w-4 animate-spin" />,
    className: 'text-blue-500',
    label: 'Running',
  },
  success: {
    icon: <CheckCircle2 className="h-4 w-4" />,
    className: 'text-green-500',
    label: 'Success',
  },
  error: {
    icon: <XCircle className="h-4 w-4" />,
    className: 'text-red-500',
    label: 'Error',
  },
  skipped: {
    icon: <SkipForward className="h-4 w-4" />,
    className: 'text-gray-400',
    label: 'Skipped',
  },
  cancelled: {
    icon: <XCircle className="h-4 w-4" />,
    className: 'text-yellow-500',
    label: 'Cancelled',
  },
  awaitingReview: {
    icon: <HelpCircle className="h-4 w-4" />,
    className: 'text-orange-500',
    label: 'Awaiting Review',
  },
}

function formatDuration(durationMs?: number): string {
  if (durationMs === undefined) return '-'
  if (durationMs < 1000) return `${durationMs}ms`
  const seconds = Math.floor(durationMs / 1000)
  if (seconds < 60) return `${seconds}s`
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes}m ${remainingSeconds}s`
}

interface NodeTimelineItemProps {
  readonly record: NodeRunRecord
  readonly nodeType: string
  readonly isExpanded: boolean
  readonly onToggle: () => void
}

function NodeTimelineItem({
  record,
  nodeType,
  isExpanded,
  onToggle,
}: NodeTimelineItemProps) {
  const status = statusConfig[record.status]
  const inputKeys = Object.keys(record.inputPayloads ?? {})
  const outputKeys = Object.keys(record.outputPayloads ?? {})

  return (
    <div className="rounded-lg border overflow-hidden" data-testid="node-timeline-item">
      {/* Header - always visible */}
      <button
        className="w-full flex items-center gap-3 p-3 text-left hover:bg-accent/50 transition-colors"
        onClick={onToggle}
      >
        <span className={status.className}>{status.icon}</span>

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <span className="font-medium text-sm truncate">{record.nodeId}</span>
            <Badge variant="secondary" className="text-[10px] h-4">
              {nodeType}
            </Badge>
            {record.usedCache && (
              <Badge variant="outline" className="text-[10px] h-4 gap-1">
                <Database className="h-3 w-3" />
                Cache
              </Badge>
            )}
          </div>
        </div>

        <div className="flex items-center gap-3 shrink-0 text-xs text-muted-foreground">
          {record.durationMs !== undefined && (
            <div className="flex items-center gap-1">
              <Clock className="h-3 w-3" />
              <span>{formatDuration(record.durationMs)}</span>
            </div>
          )}
          {isExpanded ? (
            <ChevronDown className="h-4 w-4" />
          ) : (
            <ChevronRight className="h-4 w-4" />
          )}
        </div>
      </button>

      {/* Expanded details */}
      {isExpanded && (
        <div className="border-t bg-muted/30 p-3 space-y-4">
          {/* Error message */}
          {record.errorMessage && (
            <div className="rounded-md border border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800 p-3">
              <div className="flex items-start gap-2">
                <AlertCircle className="h-4 w-4 text-red-500 mt-0.5 shrink-0" />
                <div>
                  <p className="text-sm font-medium text-red-700 dark:text-red-400">Error</p>
                  <p className="text-sm text-red-600 dark:text-red-300">{record.errorMessage}</p>
                </div>
              </div>
            </div>
          )}

          {/* Skip reason */}
          {record.skipReason && (
            <div className="rounded-md border border-gray-200 bg-gray-50 dark:bg-gray-900/10 dark:border-gray-700 p-3">
              <div className="flex items-start gap-2">
                <SkipForward className="h-4 w-4 text-gray-400 mt-0.5 shrink-0" />
                <div>
                  <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Skipped</p>
                  <p className="text-sm text-gray-500">{record.skipReason}</p>
                </div>
              </div>
            </div>
          )}

          {/* Inputs */}
          {inputKeys.length > 0 && (
            <div className="space-y-2">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Inputs ({inputKeys.length})
              </h4>
              <div className="space-y-2">
                {inputKeys.map((key) => (
                  <PayloadViewer
                    key={`input-${key}`}
                    portKey={key}
                    payload={record.inputPayloads![key]}
                    source="lastRun"
                    showProducer={true}
                  />
                ))}
              </div>
            </div>
          )}

          {/* Outputs */}
          {outputKeys.length > 0 && (
            <div className="space-y-2">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Outputs ({outputKeys.length})
              </h4>
              <div className="space-y-2">
                {outputKeys.map((key) => (
                  <PayloadViewer
                    key={`output-${key}`}
                    portKey={key}
                    payload={record.outputPayloads![key]}
                    source="lastRun"
                    showProducer={false}
                  />
                ))}
              </div>
            </div>
          )}

          {/* No data message */}
          {inputKeys.length === 0 && outputKeys.length === 0 && (
            <p className="text-sm text-muted-foreground italic">No payload data available</p>
          )}
        </div>
      )}
    </div>
  )
}

export function RunNodeTimeline({ records, workflowNodes }: RunNodeTimelineProps) {
  const [expandedNodes, setExpandedNodes] = useState<Set<string>>(new Set())

  // Build node type lookup map
  const nodeTypeMap = new Map(workflowNodes.map((n) => [n.id, n.type]))

  const toggleNode = useCallback((nodeId: string) => {
    setExpandedNodes((prev) => {
      const next = new Set(prev)
      if (next.has(nodeId)) {
        next.delete(nodeId)
      } else {
        next.add(nodeId)
      }
      return next
    })
  }, [])

  const expandAll = useCallback(() => {
    setExpandedNodes(new Set(records.map((r) => r.nodeId)))
  }, [records])

  const collapseAll = useCallback(() => {
    setExpandedNodes(new Set())
  }, [])

  if (records.length === 0) {
    return (
      <div className="rounded-lg border p-6 text-center">
        <p className="text-sm text-muted-foreground">No node execution records available</p>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {/* Toolbar */}
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          {records.length} node{records.length === 1 ? '' : 's'} executed
        </p>
        <div className="flex gap-2">
          <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={expandAll}>
            Expand All
          </Button>
          <Button variant="ghost" size="sm" className="h-7 text-xs" onClick={collapseAll}>
            Collapse All
          </Button>
        </div>
      </div>

      {/* Timeline */}
      <div className="space-y-2">
        {records.map((record) => (
          <NodeTimelineItem
            key={record.id}
            record={record}
            nodeType={nodeTypeMap.get(record.nodeId) ?? 'unknown'}
            isExpanded={expandedNodes.has(record.nodeId)}
            onToggle={() => toggleNode(record.nodeId)}
          />
        ))}
      </div>
    </div>
  )
}
