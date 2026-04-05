import { Square } from 'lucide-react'
import { Button } from '@/shared/ui/button'
import { RunStatusBadge } from './run-status-badge'
import type { ExecutionRun } from '@/shared/api/schemas'

export interface RunListItemProps {
  readonly run: ExecutionRun
  readonly isCancelling?: boolean
  readonly onCancel?: () => void
  readonly onClick?: (runId: string) => void
}

const triggerLabels: Record<string, string> = {
  runWorkflow: 'Full Run',
  runNode: 'Single Node',
  runFromHere: 'From Node',
  runUpToHere: 'Up To Node',
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
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 30) return `${diffDays}d ago`
  return date.toLocaleDateString()
}

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

export function RunListItem({ run, isCancelling, onCancel, onClick }: RunListItemProps) {
  const canCancel =
    run.status === 'running' ||
    run.status === 'pending' ||
    run.status === 'awaitingReview'

  const triggerLabel = triggerLabels[run.trigger] ?? run.trigger

  return (
    <div
      className="flex items-center justify-between rounded-lg border bg-card p-4 text-sm transition-colors hover:bg-accent/50 cursor-pointer"
      data-testid="run-list-item"
      onClick={() => onClick?.(run.id)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault()
          onClick?.(run.id)
        }
      }}
    >
      {/* Left section: status + identifiers */}
      <div className="flex items-center gap-3 min-w-0">
        <RunStatusBadge status={run.status} />
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <span className="font-mono text-xs text-muted-foreground">
              {run.id.slice(0, 8)}
            </span>
            <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              {triggerLabel}
            </span>
          </div>
          {run.targetNodeId && (
            <p className="mt-0.5 truncate text-xs text-muted-foreground">
              Target: {run.targetNodeId}
            </p>
          )}
        </div>
      </div>

      {/* Right section: stats + timestamps + cancel */}
      <div className="flex items-center gap-4 shrink-0 text-xs text-muted-foreground">
        {/* Node stats (success / error / skipped) */}
        {run.summary && (
          <div className="hidden sm:flex items-center gap-1.5" data-testid="run-node-stats">
            <span className="text-green-600 dark:text-green-400" title="Success">
              {run.summary.success}
            </span>
            <span className="text-muted-foreground/40">/</span>
            <span className="text-red-600 dark:text-red-400" title="Error">
              {run.summary.error}
            </span>
            <span className="text-muted-foreground/40">/</span>
            <span className="text-muted-foreground" title="Skipped">
              {run.summary.skipped}
            </span>
            <span className="ml-0.5 text-muted-foreground/60">nodes</span>
          </div>
        )}

        {/* Duration */}
        <span className="hidden md:inline tabular-nums" data-testid="run-duration">
          {formatDuration(run.startedAt, run.completedAt)}
        </span>

        {/* Relative start time */}
        <span className="tabular-nums w-16 text-right" data-testid="run-start-time">
          {formatRelativeTime(run.startedAt)}
        </span>

        {/* Cancel button */}
        {canCancel && onCancel && (
          <Button
            size="sm"
            variant="ghost"
            className="h-7 w-7 p-0 text-destructive hover:text-destructive hover:bg-destructive/10"
            disabled={isCancelling}
            onClick={(e) => {
              e.stopPropagation()
              onCancel()
            }}
            aria-label="Cancel run"
            data-testid="cancel-run-btn"
          >
            <Square className="h-3.5 w-3.5" />
          </Button>
        )}
      </div>
    </div>
  )
}
