/**
 * DegradedModeBanner - AiModel-537.5
 * Shows a warning when persistence is in memory-fallback mode.
 * Per plan section 6.1 and 7.3.4
 */

import { AlertTriangle, Download, RefreshCw } from 'lucide-react'
import { useBootState } from './boot-provider'

interface DegradedModeBannerProps {
  readonly onExport?: () => void
  readonly onRetry?: () => void
}

export function DegradedModeBanner({ onExport, onRetry }: DegradedModeBannerProps) {
  const boot = useBootState()

  if (boot.status !== 'degraded') return null

  return (
    <div
      className="flex items-center gap-3 bg-amber-50 border-b border-amber-200 px-3 py-1.5 text-amber-800 dark:bg-amber-950/30 dark:border-amber-800 dark:text-amber-200"
      role="alert"
      data-testid="degraded-mode-banner"
    >
      <AlertTriangle className="h-4 w-4 shrink-0" />
      <span className="text-xs flex-1">
        <strong>Degraded mode:</strong> {boot.reason}. Changes will not persist after closing the tab.
      </span>
      <div className="flex items-center gap-1 shrink-0">
        {onExport && (
          <button
            className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-medium bg-amber-100 hover:bg-amber-200 dark:bg-amber-900 dark:hover:bg-amber-800"
            onClick={onExport}
          >
            <Download className="h-3 w-3" />
            Export
          </button>
        )}
        {onRetry && (
          <button
            className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-medium bg-amber-100 hover:bg-amber-200 dark:bg-amber-900 dark:hover:bg-amber-800"
            onClick={onRetry}
          >
            <RefreshCw className="h-3 w-3" />
            Retry
          </button>
        )}
      </div>
    </div>
  )
}
