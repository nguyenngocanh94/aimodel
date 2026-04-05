/**
 * PayloadViewer - AiModel-1n1.3
 * Displays a single PortPayload with metadata, size, copy/download, collapse.
 * Per plan section 6.5
 */

import { type ReactNode, useMemo, useState } from 'react'
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
    <div className="rounded-md border p-2 space-y-1.5 transition-pulse" data-testid={`payload-${portKey}`}>
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
          <Badge
            variant="outline"
            className={`rounded-sm text-[9px] h-4 px-1 py-0.5 font-mono ${
              source === 'lastRun'
                ? 'bg-success/10 text-success'
                : 'bg-primary/10 text-primary'
            }`}
          >
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
            <pre className="font-mono text-[11px] bg-muted border border-border rounded-md p-3 max-h-48 overflow-auto whitespace-pre-wrap break-all">
              <code>{syntaxHighlightJson(jsonString.slice(0, isLarge ? 10_000 : 5_000))}</code>
              {jsonString.length > (isLarge ? 10_000 : 5_000) && <span className="text-muted-foreground">{'\n'}... truncated</span>}
            </pre>
          ) : (
            <pre className="font-mono text-[11px] bg-muted border border-border rounded-md p-3 max-h-32 overflow-auto">
              <code className="text-muted-foreground">
                {typeof payload.value === 'string'
                  ? payload.value.slice(0, 500)
                  : jsonString.slice(0, 500)}
                {jsonString.length > 500 && '...'}
              </code>
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

/**
 * Tokenise a JSON string and return syntax-highlighted React nodes.
 *
 * Colour mapping (design-system tokens + Tailwind):
 *   Keys        text-cyan-400
 *   Strings     text-green-400
 *   Numbers     text-amber-400
 *   Booleans    text-violet-400
 *   Null        text-muted-foreground
 *   Brackets    text-foreground/60
 */
const JSON_TOKEN_RE =
  /("(?:\\.|[^"\\])*"\s*:)|("(?:\\.|[^"\\])*")|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)\b|(true|false)|(null)|([[\]{}])|([,:])|([ \t\n\r]+)/g

function syntaxHighlightJson(raw: string): ReactNode[] {
  const nodes: ReactNode[] = []
  let match: RegExpExecArray | null
  let lastIndex = 0

  // Reset regex state for each call
  JSON_TOKEN_RE.lastIndex = 0

  while ((match = JSON_TOKEN_RE.exec(raw)) !== null) {
    // Any text between matches (shouldn't happen for valid JSON, but be safe)
    if (match.index > lastIndex) {
      nodes.push(raw.slice(lastIndex, match.index))
    }
    lastIndex = JSON_TOKEN_RE.lastIndex

    const idx = match.index
    if (match[1] !== undefined) {
      // Object key (includes trailing colon)
      const colonIdx = match[1].lastIndexOf(':')
      nodes.push(
        <span key={`k${idx}`} className="text-cyan-400">{match[1].slice(0, colonIdx)}</span>,
        <span key={`c${idx}`} className="text-foreground/60">{match[1].slice(colonIdx)}</span>,
      )
    } else if (match[2] !== undefined) {
      nodes.push(<span key={`s${idx}`} className="text-green-400">{match[2]}</span>)
    } else if (match[3] !== undefined) {
      nodes.push(<span key={`n${idx}`} className="text-amber-400">{match[3]}</span>)
    } else if (match[4] !== undefined) {
      nodes.push(<span key={`b${idx}`} className="text-violet-400">{match[4]}</span>)
    } else if (match[5] !== undefined) {
      nodes.push(<span key={`x${idx}`} className="text-muted-foreground">{match[5]}</span>)
    } else if (match[6] !== undefined) {
      nodes.push(<span key={`br${idx}`} className="text-foreground/60">{match[6]}</span>)
    } else {
      // Punctuation (comma, colon) or whitespace - no special colouring
      nodes.push(match[0])
    }
  }

  // Trailing text
  if (lastIndex < raw.length) {
    nodes.push(raw.slice(lastIndex))
  }

  return nodes
}
