import { Square } from 'lucide-react'
import { Button } from '@/shared/ui/button'
import { RunStatusBadge } from './run-status-badge'

interface RunSummary {
  readonly successCount?: number
  readonly errorCount?: number
  readonly skippedCount?: number
  readonly totalCount?: number
}

interface RunListItemProps {
  readonly id: string
  readonly status: string
  readonly trigger: string
  readonly targetNodeId?: string
  readonly startedAt?: string
  readonly completedAt?: string
  readonly summary?: RunSummary
  readonly onCancel?: () => void
}

function formatRelativeTime(dateStr?: string): string {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  return `${Math.floor(diffHours / 24)}d ago`
}

function formatDuration(startedAt?: string, completedAt?: string): string {
  if (!startedAt) return '-'
  const start = new Date(startedAt).getTime()
  const end = completedAt ? new Date(completedAt).getTime() : Date.now()
  const ms = end - start
  if (ms < 1000) return `${Math.round(ms)}ms`
  const s = Math.floor(ms / 1000)
  if (s < 60) return `${s}s`
  return `${Math.floor(s / 60)}m ${s % 60}s`
}

export function RunListItem({
  id,
  status,
  trigger,
  targetNodeId,
  startedAt,
  completedAt,
  summary,
  onCancel,
}: RunListItemProps) {
  const canCancel = status === 'running' || status === 'pending' || status === 'awaitingReview'

  return (
    <div className="flex items-center justify-between rounded-lg border bg-card p-3 text-sm" data-testid="run-list-item">
      <div className="flex items-center gap-3">
        <RunStatusBadge status={status} />
        <div>
          <span className="font-mono text-xs text-muted-foreground">{id.slice(0, 8)}</span>
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span>{trigger}</span>
            {targetNodeId && <span>→ {targetNodeId}</span>}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-4 text-xs text-muted-foreground">
        {summary && (
          <span>
            {summary.successCount ?? 0}/{summary.totalCount ?? 0} nodes
            {(summary.errorCount ?? 0) > 0 && (
              <span className="text-destructive"> ({summary.errorCount} err)</span>
            )}
          </span>
        )}
        <span>{formatDuration(startedAt, completedAt)}</span>
        <span>{formatRelativeTime(startedAt)}</span>

        {canCancel && onCancel && (
          <Button size="sm" variant="ghost" className="h-6 px-2" onClick={onCancel}>
            <Square className="h-3 w-3" />
          </Button>
        )}
      </div>
    </div>
  )
}
