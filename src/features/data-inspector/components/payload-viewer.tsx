/**
 * PayloadViewer - AiModel-1n1.3
 * Displays a single PortPayload with metadata, size, copy/download, collapse.
 * Per plan section 6.5
 */

import { useMemo, useState } from 'react'
import { Copy, Download, ChevronDown, ChevronRight, ExternalLink } from 'lucide-react'
import { Badge } from '@/shared/ui/badge'
import { Button } from '@/shared/ui/button'
import type { PortPayload } from '@/features/workflows/domain/workflow-types'

// ============================================================
// Size thresholds (plan section 6.5)
// ============================================================

const INLINE_LIMIT = 256 * 1024      // 256 KB
const COLLAPSE_LIMIT = 2 * 1024 * 1024 // 2 MB

export type PayloadSource = 'preview' | 'lastRun'

interface PayloadViewerProps {
  readonly portKey: string
  readonly payload: PortPayload
  readonly source: PayloadSource
  readonly showProducer?: boolean
}

export function PayloadViewer({ portKey, payload, source, showProducer = true }: PayloadViewerProps) {
  const [expanded, setExpanded] = useState(false)
  const [viewMode, setViewMode] = useState<'summary' | 'json'>('summary')

  const sizeBytes = payload.sizeBytesEstimate ?? estimateSize(payload.value)
  const sizeLabel = formatSize(sizeBytes)

  const jsonString = useMemo(() => {
    if (payload.value === null) return 'null'
    try {
      return JSON.stringify(payload.value, null, 2)
    } catch {
      return String(payload.value)
    }
  }, [payload.value])

  const isLarge = sizeBytes > INLINE_LIMIT
  const isHuge = sizeBytes > COLLAPSE_LIMIT
  const shouldCollapse = isLarge && !expanded

  const handleCopy = () => {
    navigator.clipboard.writeText(jsonString).catch(() => {})
  }

  const handleDownload = () => {
    const blob = new Blob([jsonString], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${portKey}.json`
    a.click()
    URL.revokeObjectURL(url)
  }

  return (
    <div className="rounded-md border p-2 space-y-1.5" data-testid={`payload-${portKey}`}>
      {/* Header row */}
      <div className="flex items-center justify-between gap-1">
        <div className="flex items-center gap-1.5 min-w-0">
          {isLarge && (
            <button
              className="shrink-0 text-muted-foreground hover:text-foreground"
              onClick={() => setExpanded(!expanded)}
              aria-label={expanded ? 'Collapse' : 'Expand'}
            >
              {expanded ? (
                <ChevronDown className="h-3 w-3" />
              ) : (
                <ChevronRight className="h-3 w-3" />
              )}
            </button>
          )}
          <span className="text-xs font-medium truncate">{portKey}</span>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          <Badge
            variant={payload.status === 'error' ? 'destructive' : 'secondary'}
            className="text-[9px] h-4 px-1"
          >
            {payload.status}
          </Badge>
          <Badge variant="outline" className="text-[9px] h-4 px-1">
            {source}
          </Badge>
        </div>
      </div>

      {/* Metadata row */}
      <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] text-muted-foreground">
        <span className="font-mono">{payload.schemaType}</span>
        <span>{sizeLabel}</span>
        {showProducer && payload.sourceNodeId && (
          <span>from: {payload.sourceNodeId}</span>
        )}
        {payload.producedAt && (
          <span>{new Date(payload.producedAt).toLocaleTimeString()}</span>
        )}
      </div>

      {/* Error message */}
      {payload.errorMessage && (
        <p className="text-xs text-destructive">{payload.errorMessage}</p>
      )}

      {/* Preview text */}
      {payload.previewText && !shouldCollapse && (
        <p className="text-xs text-foreground line-clamp-4">{payload.previewText}</p>
      )}

      {/* Preview URL */}
      {payload.previewUrl && (
        <a
          href={payload.previewUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-primary flex items-center gap-1 hover:underline"
        >
          <ExternalLink className="h-3 w-3" />
          Preview
        </a>
      )}

      {/* Collapsed state */}
      {shouldCollapse && payload.value !== null && (
        <div className="text-xs text-muted-foreground">
          {isHuge ? (
            <p>Payload too large to display inline ({sizeLabel}). Use download.</p>
          ) : (
            <button
              className="text-primary hover:underline"
              onClick={() => setExpanded(true)}
            >
              Expand payload ({sizeLabel})
            </button>
          )}
        </div>
      )}

      {/* Inline content */}
      {payload.value !== null && !shouldCollapse && !payload.previewText && (
        <div>
          {/* View mode toggle */}
          <div className="flex gap-1 mb-1">
            <button
              className={`text-[10px] px-1 rounded ${viewMode === 'summary' ? 'bg-muted text-foreground' : 'text-muted-foreground'}`}
              onClick={() => setViewMode('summary')}
            >
              Summary
            </button>
            <button
              className={`text-[10px] px-1 rounded ${viewMode === 'json' ? 'bg-muted text-foreground' : 'text-muted-foreground'}`}
              onClick={() => setViewMode('json')}
            >
              JSON
            </button>
          </div>

          {viewMode === 'json' ? (
            <pre className="text-[10px] text-muted-foreground bg-muted/50 rounded p-1 max-h-48 overflow-auto whitespace-pre-wrap break-all">
              {jsonString.slice(0, isLarge ? 10_000 : 5_000)}
              {jsonString.length > (isLarge ? 10_000 : 5_000) && '\n… truncated'}
            </pre>
          ) : (
            <pre className="text-[10px] text-muted-foreground bg-muted/50 rounded p-1 max-h-32 overflow-auto">
              {typeof payload.value === 'string'
                ? payload.value.slice(0, 500)
                : jsonString.slice(0, 500)}
              {jsonString.length > 500 && '…'}
            </pre>
          )}
        </div>
      )}

      {/* Actions */}
      {payload.value !== null && (
        <div className="flex gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-5 text-[10px] px-1.5 gap-1"
            onClick={handleCopy}
          >
            <Copy className="h-2.5 w-2.5" />
            Copy
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-5 text-[10px] px-1.5 gap-1"
            onClick={handleDownload}
          >
            <Download className="h-2.5 w-2.5" />
            Download
          </Button>
        </div>
      )}
    </div>
  )
}

// ============================================================
// Helpers
// ============================================================

function estimateSize(value: unknown): number {
  if (value === null || value === undefined) return 0
  try {
    return new Blob([JSON.stringify(value)]).size
  } catch {
    return 0
  }
}

function formatSize(bytes: number): string {
  if (bytes === 0) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
