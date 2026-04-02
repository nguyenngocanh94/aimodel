import { describe, it, expect } from 'vitest'
import {
  checkGraphSize,
  checkDocumentSize,
  GRAPH_SIZE_BUDGET,
  DOCUMENT_SIZE_BUDGET,
  PREVIEW_DEBOUNCE_MS,
} from './perf-budget'
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types'

function makeDocument(nodeCount: number, edgeCount: number): WorkflowDocument {
  return {
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test',
    description: '',
    tags: [],
    nodes: Array.from({ length: nodeCount }, (_, i) => ({
      id: `n${i}`,
      type: 'userPrompt',
      label: `Node ${i}`,
      position: { x: i * 100, y: 0 },
      config: {},
    })),
    edges: Array.from({ length: edgeCount }, (_, i) => ({
      id: `e${i}`,
      sourceNodeId: `n${i}`,
      sourcePortKey: 'out',
      targetNodeId: `n${i + 1}`,
      targetPortKey: 'in',
    })),
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00Z',
    updatedAt: '2025-01-01T00:00:00Z',
  }
}

describe('checkGraphSize', () => {
  it('should report within budget for small graphs', () => {
    const result = checkGraphSize(makeDocument(5, 4))
    expect(result.withinBudget).toBe(true)
    expect(result.nodeOverflow).toBe(0)
    expect(result.edgeOverflow).toBe(0)
  })

  it('should report within budget at exact limits', () => {
    const result = checkGraphSize(makeDocument(GRAPH_SIZE_BUDGET.maxNodes, GRAPH_SIZE_BUDGET.maxEdges))
    expect(result.withinBudget).toBe(true)
  })

  it('should report overflow when exceeding node limit', () => {
    const result = checkGraphSize(makeDocument(20, 5))
    expect(result.withinBudget).toBe(false)
    expect(result.nodeOverflow).toBe(5)
  })

  it('should report overflow when exceeding edge limit', () => {
    const result = checkGraphSize(makeDocument(5, 30))
    expect(result.withinBudget).toBe(false)
    expect(result.edgeOverflow).toBe(5)
  })
})

describe('checkDocumentSize', () => {
  it('should report ok for small documents', () => {
    const result = checkDocumentSize(makeDocument(1, 0))
    expect(result.level).toBe('ok')
    expect(result.sizeBytes).toBeGreaterThan(0)
  })

  it('should have correct format for size label', () => {
    const result = checkDocumentSize(makeDocument(1, 0))
    expect(result.sizeLabel).toMatch(/\d+/)
  })
})

describe('PREVIEW_DEBOUNCE_MS', () => {
  it('should be 150ms', () => {
    expect(PREVIEW_DEBOUNCE_MS).toBe(150)
  })
})

describe('budget constants', () => {
  it('graph budget should be 15 nodes / 25 edges', () => {
    expect(GRAPH_SIZE_BUDGET.maxNodes).toBe(15)
    expect(GRAPH_SIZE_BUDGET.maxEdges).toBe(25)
  })

  it('document size budget should be 750KB warn / 1MB max', () => {
    expect(DOCUMENT_SIZE_BUDGET.warnBytes).toBe(750 * 1024)
    expect(DOCUMENT_SIZE_BUDGET.maxBytes).toBe(1024 * 1024)
  })
})
