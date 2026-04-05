/**
 * Performance budgets and size warnings - AiModel-537.6
 * Per plan section 18.2
 */

import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types'

// ============================================================
// Graph size budgets
// ============================================================

export const GRAPH_SIZE_BUDGET = {
  /** Smooth authoring target */
  maxNodes: 15,
  maxEdges: 25,
} as const

export interface GraphSizeCheck {
  readonly withinBudget: boolean
  readonly nodeCount: number
  readonly edgeCount: number
  readonly nodeOverflow: number
  readonly edgeOverflow: number
}

export function checkGraphSize(document: WorkflowDocument): GraphSizeCheck {
  const nodeCount = document.nodes.length
  const edgeCount = document.edges.length
  return {
    withinBudget: nodeCount <= GRAPH_SIZE_BUDGET.maxNodes && edgeCount <= GRAPH_SIZE_BUDGET.maxEdges,
    nodeCount,
    edgeCount,
    nodeOverflow: Math.max(0, nodeCount - GRAPH_SIZE_BUDGET.maxNodes),
    edgeOverflow: Math.max(0, edgeCount - GRAPH_SIZE_BUDGET.maxEdges),
  }
}

// ============================================================
// Serialized document size budgets
// ============================================================

export const DOCUMENT_SIZE_BUDGET = {
  /** Warn threshold */
  warnBytes: 750 * 1024,
  /** Max before degrading persistence */
  maxBytes: 1024 * 1024,
} as const

export type DocumentSizeLevel = 'ok' | 'warning' | 'critical'

export interface DocumentSizeCheck {
  readonly level: DocumentSizeLevel
  readonly sizeBytes: number
  readonly sizeLabel: string
}

export function checkDocumentSize(document: WorkflowDocument): DocumentSizeCheck {
  let sizeBytes: number
  try {
    sizeBytes = new Blob([JSON.stringify(document)]).size
  } catch {
    sizeBytes = 0
  }

  let level: DocumentSizeLevel
  if (sizeBytes > DOCUMENT_SIZE_BUDGET.maxBytes) {
    level = 'critical'
  } else if (sizeBytes > DOCUMENT_SIZE_BUDGET.warnBytes) {
    level = 'warning'
  } else {
    level = 'ok'
  }

  return { level, sizeBytes, sizeLabel: formatBytes(sizeBytes) }
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

// ============================================================
// Preview debounce constant
// ============================================================

/** Debounce delay for preview recomputation after config/topology changes */
export const PREVIEW_DEBOUNCE_MS = 150
