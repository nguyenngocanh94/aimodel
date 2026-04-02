import { describe, it, expect } from 'vitest'
import { traceLineage } from './lineage-view'
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types'

function makeDocument(overrides: Partial<WorkflowDocument> = {}): WorkflowDocument {
  return {
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00Z',
    updatedAt: '2025-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('traceLineage', () => {
  it('should return empty map for node with no inputs', () => {
    const doc = makeDocument({
      nodes: [{ id: 'n1', type: 'userPrompt', label: 'P', position: { x: 0, y: 0 }, config: {} }],
    })
    const lineages = traceLineage('n1', doc)
    expect(lineages.size).toBe(0)
  })

  it('should trace single-step lineage', () => {
    const doc = makeDocument({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'Prompt', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'scriptWriter', label: 'Script', position: { x: 200, y: 0 }, config: {} },
      ],
      edges: [{
        id: 'e1',
        sourceNodeId: 'n1',
        sourcePortKey: 'prompt',
        targetNodeId: 'n2',
        targetPortKey: 'prompt',
      }],
    })

    const lineages = traceLineage('n2', doc)
    expect(lineages.size).toBe(1)
    const promptLineage = lineages.get('prompt')!
    expect(promptLineage).toHaveLength(1)
    expect(promptLineage[0].nodeId).toBe('n1')
    expect(promptLineage[0].nodeLabel).toBe('Prompt')
  })

  it('should trace multi-step lineage', () => {
    const doc = makeDocument({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'Prompt', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'scriptWriter', label: 'Script', position: { x: 200, y: 0 }, config: {} },
        { id: 'n3', type: 'sceneSplitter', label: 'Scenes', position: { x: 400, y: 0 }, config: {} },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'prompt', targetNodeId: 'n2', targetPortKey: 'prompt' },
        { id: 'e2', sourceNodeId: 'n2', sourcePortKey: 'script', targetNodeId: 'n3', targetPortKey: 'script' },
      ],
    })

    const lineages = traceLineage('n3', doc)
    expect(lineages.size).toBe(1)
    const scriptLineage = lineages.get('script')!
    expect(scriptLineage).toHaveLength(2)
    expect(scriptLineage[0].nodeId).toBe('n1')
    expect(scriptLineage[1].nodeId).toBe('n2')
  })

  it('should handle cycles without infinite loop', () => {
    const doc = makeDocument({
      nodes: [
        { id: 'n1', type: 'a', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'b', label: 'B', position: { x: 200, y: 0 }, config: {} },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'out', targetNodeId: 'n2', targetPortKey: 'in' },
        { id: 'e2', sourceNodeId: 'n2', sourcePortKey: 'out', targetNodeId: 'n1', targetPortKey: 'in' },
      ],
    })

    // Should not infinite loop
    const lineages = traceLineage('n2', doc)
    expect(lineages.size).toBe(1)
  })
})
