import { cn } from '@/shared/lib/utils'
import type { ExecutionRun } from '@/shared/api/schemas'

type RunStatus = ExecutionRun['status']

const statusConfig: Record<RunStatus, { label: string; className: string }> = {
  success: {
    label: 'Success',
    className:
      'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
  },
  error: {
    label: 'Error',
    className:
      'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800',
  },
  running: {
    label: 'Running',
    className:
      'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
  },
  pending: {
    label: 'Pending',
    className:
      'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800/30 dark:text-gray-400 dark:border-gray-700',
  },
  cancelled: {
    label: 'Cancelled',
    className:
      'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
  },
  awaitingReview: {
    label: 'Awaiting Review',
    className:
      'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-400 dark:border-orange-800',
  },
  interrupted: {
    label: 'Interrupted',
    className:
      'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
  },
}

export interface RunStatusBadgeProps {
  readonly status: RunStatus
  readonly className?: string
}

export function RunStatusBadge({ status, className }: RunStatusBadgeProps) {
  const config = statusConfig[status] ?? statusConfig.pending

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold',
        config.className,
        className,
      )}
      data-testid="run-status-badge"
    >
      {status === 'running' && (
        <span className="mr-1.5 h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
      )}
      {config.label}
    </span>
  )
}
