import { describe, it, expect, beforeEach } from 'vitest'

import type {
  WorkflowNode,
  WorkflowEdge,
  ValidationIssue,
} from '@/features/workflows/domain/workflow-types'

import {
  createEmptyWorkflowDocument,
  useWorkflowStore,
  MAX_HISTORY,
} from './workflow-store'

import {
  selectSelectedNodes,
  selectNodeById,
  selectEdgeById,
  selectNodesByType,
  selectWorkflowIsEmpty,
  selectValidationIssuesByNode,
  selectCanUndo,
  selectCanRedo,
} from './workflow-selectors'

// ============================================================
// Test fixtures
// ============================================================

const sampleNode: WorkflowNode = {
  id: 'n1',
  type: 'userPrompt',
  label: 'Prompt',
  position: { x: 0, y: 0 },
  config: {},
}

const sampleNode2: WorkflowNode = {
  id: 'n2',
  type: 'llmCall',
  label: 'LLM Call',
  position: { x: 200, y: 100 },
  config: { model: 'gpt-4' },
}

const sampleNode3: WorkflowNode = {
  id: 'n3',
  type: 'userPrompt',
  label: 'Another Prompt',
  position: { x: 400, y: 0 },
  config: {},
}

const sampleEdge: WorkflowEdge = {
  id: 'e1',
  sourceNodeId: 'n1',
  sourcePortKey: 'output',
  targetNodeId: 'n2',
  targetPortKey: 'input',
}

// ============================================================
// Setup
// ============================================================

beforeEach(() => {
  const doc = createEmptyWorkflowDocument('test-doc')
  useWorkflowStore.getState().resetDocument(doc)
})

// ============================================================
// Node mutations
// ============================================================

describe('node mutations', () => {
  it('addNode appends a node to the document', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    const { document } = useWorkflowStore.getState()
    expect(document.nodes).toHaveLength(1)
    expect(document.nodes[0].id).toBe('n1')
  })

  it('addNode can add multiple nodes', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    const { document } = useWorkflowStore.getState()
    expect(document.nodes).toHaveLength(2)
    expect(document.nodes[0].id).toBe('n1')
    expect(document.nodes[1].id).toBe('n2')
  })

  it('removeNode removes the node from the document', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().removeNode('n1')
    const { document } = useWorkflowStore.getState()
    expect(document.nodes).toHaveLength(1)
    expect(document.nodes[0].id).toBe('n2')
  })

  it('removeNode also removes connected edges', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().addEdge(sampleEdge)
    expect(useWorkflowStore.getState().document.edges).toHaveLength(1)

    useWorkflowStore.getState().removeNode('n1')
    expect(useWorkflowStore.getState().document.edges).toHaveLength(0)
  })

  it('moveNode updates the node position', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().moveNode('n1', { x: 100, y: 200 })
    const node = useWorkflowStore.getState().document.nodes[0]
    expect(node.position).toEqual({ x: 100, y: 200 })
  })

  it('updateNodeConfig replaces the config for a specific node', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().updateNodeConfig('n1', { temperature: 0.7 })
    const node = useWorkflowStore.getState().document.nodes[0]
    expect(node.config).toEqual({ temperature: 0.7 })
  })

  it('updateNodeLabel changes the label for a specific node', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().updateNodeLabel('n1', 'Renamed Prompt')
    const node = useWorkflowStore.getState().document.nodes[0]
    expect(node.label).toBe('Renamed Prompt')
  })
})

// ============================================================
// Edge mutations
// ============================================================

describe('edge mutations', () => {
  it('addEdge appends an edge to the document', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().addEdge(sampleEdge)
    const { document } = useWorkflowStore.getState()
    expect(document.edges).toHaveLength(1)
    expect(document.edges[0].id).toBe('e1')
  })

  it('removeEdge removes an edge from the document', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().addEdge(sampleEdge)
    useWorkflowStore.getState().removeEdge('e1')
    expect(useWorkflowStore.getState().document.edges).toHaveLength(0)
  })
})

// ============================================================
// Workflow metadata mutations
// ============================================================

describe('metadata mutations', () => {
  it('updateWorkflowName changes the document name', () => {
    useWorkflowStore.getState().updateWorkflowName('My Workflow')
    expect(useWorkflowStore.getState().document.name).toBe('My Workflow')
  })

  it('updateWorkflowDescription changes the document description', () => {
    useWorkflowStore.getState().updateWorkflowDescription('A video pipeline')
    expect(useWorkflowStore.getState().document.description).toBe('A video pipeline')
  })

  it('updateWorkflowTags updates tags', () => {
    useWorkflowStore.getState().updateWorkflowTags(['video', 'ai'])
    expect(useWorkflowStore.getState().document.tags).toEqual(['video', 'ai'])
  })
})

// ============================================================
// Undo / Redo
// ============================================================

describe('undo and redo', () => {
  it('undo reverses the last document mutation', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(1)

    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(0)
  })

  it('redo re-applies the undone action', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(0)

    useWorkflowStore.getState().redo()
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(1)
  })

  it('multiple undo steps walk backwards through history', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().updateWorkflowName('Changed')

    useWorkflowStore.getState().undo() // undo rename
    expect(useWorkflowStore.getState().document.name).toBe('Untitled workflow')
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(2)

    useWorkflowStore.getState().undo() // undo addNode2
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(1)

    useWorkflowStore.getState().undo() // undo addNode1
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(0)
  })

  it('redo after undo restores sequentially', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)

    useWorkflowStore.getState().undo()
    useWorkflowStore.getState().undo()

    useWorkflowStore.getState().redo()
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(1)

    useWorkflowStore.getState().redo()
    expect(useWorkflowStore.getState().document.nodes).toHaveLength(2)
  })

  it('new mutation after undo clears redo stack', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)

    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().future).toHaveLength(1)

    useWorkflowStore.getState().addNode(sampleNode3)
    expect(useWorkflowStore.getState().future).toHaveLength(0)
  })

  it('undo is a no-op when history is empty', () => {
    const before = useWorkflowStore.getState().document
    useWorkflowStore.getState().undo()
    const after = useWorkflowStore.getState().document
    expect(after).toBe(before)
  })

  it('redo is a no-op when future is empty', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    const before = useWorkflowStore.getState().document
    useWorkflowStore.getState().redo()
    const after = useWorkflowStore.getState().document
    expect(after).toBe(before)
  })

  it('canUndo returns correct value', () => {
    expect(useWorkflowStore.getState().canUndo()).toBe(false)
    useWorkflowStore.getState().addNode(sampleNode)
    expect(useWorkflowStore.getState().canUndo()).toBe(true)
    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().canUndo()).toBe(false)
  })

  it('canRedo returns correct value', () => {
    expect(useWorkflowStore.getState().canRedo()).toBe(false)
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().canRedo()).toBe(true)
    useWorkflowStore.getState().redo()
    expect(useWorkflowStore.getState().canRedo()).toBe(false)
  })

  it('undo stack is capped at MAX_HISTORY entries', () => {
    for (let i = 0; i < MAX_HISTORY + 10; i++) {
      useWorkflowStore.getState().updateWorkflowName(`Name ${i}`)
    }
    const { past } = useWorkflowStore.getState()
    expect(past.length).toBe(MAX_HISTORY)
  })

  it('undo restores correct document when stack was trimmed', () => {
    // Fill up beyond the cap
    for (let i = 0; i < MAX_HISTORY + 5; i++) {
      useWorkflowStore.getState().updateWorkflowName(`Name ${i}`)
    }
    // The oldest entries should have been trimmed
    useWorkflowStore.getState().undo()
    // After undo, should have the second-to-last name
    expect(useWorkflowStore.getState().document.name).toBe(`Name ${MAX_HISTORY + 3}`)
  })
})

// ============================================================
// Selection does NOT affect undo stack
// ============================================================

describe('selection changes do not affect undo stack', () => {
  it('setSelectedNodeIds does not push to history', () => {
    useWorkflowStore.getState().setSelectedNodeIds(['a', 'b'])
    expect(useWorkflowStore.getState().past).toHaveLength(0)
  })

  it('setSelectedEdgeId does not push to history', () => {
    useWorkflowStore.getState().setSelectedEdgeId('e1')
    expect(useWorkflowStore.getState().past).toHaveLength(0)
  })

  it('selection changes interleaved with mutations', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().setSelectedNodeIds(['n1'])
    useWorkflowStore.getState().addNode(sampleNode2)
    expect(useWorkflowStore.getState().past).toHaveLength(2) // only two mutations
  })
})

// ============================================================
// Viewport does NOT affect undo stack
// ============================================================

describe('viewport changes do not affect undo stack', () => {
  it('setViewport does not push to undo history', () => {
    useWorkflowStore.getState().commitAuthoring((d) => ({
      ...d,
      name: 'A',
    }))
    useWorkflowStore.getState().setViewport({ x: 10, y: 20, zoom: 0.5 })
    expect(useWorkflowStore.getState().past).toHaveLength(1)
    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().document.name).toBe('Untitled workflow')
    // Viewport on the document gets restored from the undo snapshot
    expect(useWorkflowStore.getState().document.viewport).toEqual({
      x: 0,
      y: 0,
      zoom: 1,
    })
  })
})

// ============================================================
// Dirty state tracking
// ============================================================

describe('dirty state', () => {
  it('starts as false', () => {
    expect(useWorkflowStore.getState().dirty).toBe(false)
  })

  it('becomes true after a document mutation', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    expect(useWorkflowStore.getState().dirty).toBe(true)
  })

  it('becomes false after markSaved', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    expect(useWorkflowStore.getState().dirty).toBe(true)
    useWorkflowStore.getState().markSaved()
    expect(useWorkflowStore.getState().dirty).toBe(false)
  })

  it('becomes true again after another mutation following markSaved', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().markSaved()
    useWorkflowStore.getState().addNode(sampleNode2)
    expect(useWorkflowStore.getState().dirty).toBe(true)
  })

  it('markSaved stores lastSavedDocument', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().markSaved()
    const saved = useWorkflowStore.getState().lastSavedDocument
    expect(saved).not.toBeNull()
    expect(saved?.nodes).toHaveLength(1)
  })

  it('undo marks dirty as true', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().markSaved()
    expect(useWorkflowStore.getState().dirty).toBe(false)
    useWorkflowStore.getState().undo()
    expect(useWorkflowStore.getState().dirty).toBe(true)
  })

  it('viewport changes mark dirty', () => {
    useWorkflowStore.getState().setViewport({ x: 10, y: 0, zoom: 1 })
    expect(useWorkflowStore.getState().dirty).toBe(true)
  })

  it('selection changes do not mark dirty', () => {
    useWorkflowStore.getState().setSelectedNodeIds(['n1'])
    expect(useWorkflowStore.getState().dirty).toBe(false)
  })
})

// ============================================================
// loadDocument
// ============================================================

describe('loadDocument', () => {
  it('replaces the document and resets undo stack', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    expect(useWorkflowStore.getState().past).toHaveLength(2)

    const freshDoc = createEmptyWorkflowDocument('loaded-doc')
    useWorkflowStore.getState().loadDocument(freshDoc)

    expect(useWorkflowStore.getState().document.id).toBe('loaded-doc')
    expect(useWorkflowStore.getState().past).toHaveLength(0)
    expect(useWorkflowStore.getState().future).toHaveLength(0)
  })

  it('resets dirty state to false', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    expect(useWorkflowStore.getState().dirty).toBe(true)

    const freshDoc = createEmptyWorkflowDocument('loaded-doc')
    useWorkflowStore.getState().loadDocument(freshDoc)
    expect(useWorkflowStore.getState().dirty).toBe(false)
  })

  it('sets lastSavedDocument to the loaded document', () => {
    const freshDoc = createEmptyWorkflowDocument('loaded-doc')
    useWorkflowStore.getState().loadDocument(freshDoc)
    expect(useWorkflowStore.getState().lastSavedDocument).toBe(freshDoc)
  })

  it('clears selection', () => {
    useWorkflowStore.getState().setSelectedNodeIds(['n1'])
    useWorkflowStore.getState().setSelectedEdgeId('e1')

    const freshDoc = createEmptyWorkflowDocument('loaded-doc')
    useWorkflowStore.getState().loadDocument(freshDoc)

    expect(useWorkflowStore.getState().selectedNodeIds).toHaveLength(0)
    expect(useWorkflowStore.getState().selectedEdgeId).toBeNull()
  })

  it('clears validation issues', () => {
    const issue: ValidationIssue = {
      id: 'v1',
      severity: 'error',
      scope: 'node',
      message: 'test error',
      nodeId: 'n1',
      code: 'configInvalid',
    }
    useWorkflowStore.getState().setValidationIssues([issue])
    expect(useWorkflowStore.getState().validationIssues).toHaveLength(1)

    const freshDoc = createEmptyWorkflowDocument('loaded-doc')
    useWorkflowStore.getState().loadDocument(freshDoc)
    expect(useWorkflowStore.getState().validationIssues).toHaveLength(0)
  })
})

// ============================================================
// resetDocument
// ============================================================

describe('resetDocument', () => {
  it('clears history, selection, and dirty state', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().setSelectedNodeIds(['n1'])
    useWorkflowStore.getState().resetDocument(createEmptyWorkflowDocument('fresh'))

    expect(useWorkflowStore.getState().past).toHaveLength(0)
    expect(useWorkflowStore.getState().future).toHaveLength(0)
    expect(useWorkflowStore.getState().selectedNodeIds).toHaveLength(0)
    expect(useWorkflowStore.getState().document.id).toBe('fresh')
    expect(useWorkflowStore.getState().dirty).toBe(false)
  })
})

// ============================================================
// UI state
// ============================================================

describe('UI state', () => {
  it('setLibrarySearchFilter updates the filter', () => {
    useWorkflowStore.getState().setLibrarySearchFilter('prompt')
    expect(useWorkflowStore.getState().librarySearchFilter).toBe('prompt')
  })

  it('setInspectorTab updates the active tab', () => {
    useWorkflowStore.getState().setInspectorTab('validation')
    expect(useWorkflowStore.getState().inspectorTab).toBe('validation')
  })

  it('setLibraryUi merges partial state', () => {
    useWorkflowStore.getState().setLibraryUi({ searchQuery: 'test' })
    expect(useWorkflowStore.getState().libraryUi.searchQuery).toBe('test')
    expect(useWorkflowStore.getState().libraryUi.displayMode).toBe('expanded') // unchanged
  })
})

// ============================================================
// Validation
// ============================================================

describe('validation', () => {
  it('setValidationIssues updates the issues list', () => {
    const issues: ValidationIssue[] = [
      {
        id: 'v1',
        severity: 'error',
        scope: 'node',
        message: 'Missing input',
        nodeId: 'n1',
        code: 'missingRequiredInput',
      },
      {
        id: 'v2',
        severity: 'warning',
        scope: 'edge',
        message: 'Coercion applied',
        edgeId: 'e1',
        code: 'coercionApplied',
      },
    ]
    useWorkflowStore.getState().setValidationIssues(issues)
    expect(useWorkflowStore.getState().validationIssues).toHaveLength(2)
    expect(useWorkflowStore.getState().validationIssues[0].code).toBe('missingRequiredInput')
  })
})

// ============================================================
// updatedAt timestamp
// ============================================================

describe('updatedAt timestamp', () => {
  it('document mutations update the updatedAt field', () => {
    // Force an older timestamp so the mutation will always produce a different one
    const store = useWorkflowStore.getState()
    const oldDoc = { ...store.document, updatedAt: '2020-01-01T00:00:00.000Z' }
    useWorkflowStore.setState({ document: oldDoc })
    const before = useWorkflowStore.getState().document.updatedAt
    useWorkflowStore.getState().addNode(sampleNode)
    const after = useWorkflowStore.getState().document.updatedAt
    expect(after).not.toBe(before)
  })
})

// ============================================================
// Selectors
// ============================================================

describe('selectors', () => {
  it('selectSelectedNodes returns WorkflowNode objects for selected IDs', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().setSelectedNodeIds(['n1'])

    const result = selectSelectedNodes(useWorkflowStore.getState())
    expect(result).toHaveLength(1)
    expect(result[0].id).toBe('n1')
  })

  it('selectSelectedNodes returns empty array when nothing selected', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    const result = selectSelectedNodes(useWorkflowStore.getState())
    expect(result).toHaveLength(0)
  })

  it('selectNodeById finds a node', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)

    const found = selectNodeById(useWorkflowStore.getState(), 'n2')
    expect(found?.label).toBe('LLM Call')
  })

  it('selectNodeById returns undefined for missing ID', () => {
    const found = selectNodeById(useWorkflowStore.getState(), 'nonexistent')
    expect(found).toBeUndefined()
  })

  it('selectEdgeById finds an edge', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    useWorkflowStore.getState().addNode(sampleNode2)
    useWorkflowStore.getState().addEdge(sampleEdge)

    const found = selectEdgeById(useWorkflowStore.getState(), 'e1')
    expect(found?.sourceNodeId).toBe('n1')
  })

  it('selectEdgeById returns undefined for missing ID', () => {
    const found = selectEdgeById(useWorkflowStore.getState(), 'nonexistent')
    expect(found).toBeUndefined()
  })

  it('selectNodesByType filters nodes by type', () => {
    useWorkflowStore.getState().addNode(sampleNode) // userPrompt
    useWorkflowStore.getState().addNode(sampleNode2) // llmCall
    useWorkflowStore.getState().addNode(sampleNode3) // userPrompt

    const prompts = selectNodesByType(useWorkflowStore.getState(), 'userPrompt')
    expect(prompts).toHaveLength(2)
    expect(prompts[0].id).toBe('n1')
    expect(prompts[1].id).toBe('n3')
  })

  it('selectWorkflowIsEmpty returns true for empty workflow', () => {
    expect(selectWorkflowIsEmpty(useWorkflowStore.getState())).toBe(true)
  })

  it('selectWorkflowIsEmpty returns false when nodes exist', () => {
    useWorkflowStore.getState().addNode(sampleNode)
    expect(selectWorkflowIsEmpty(useWorkflowStore.getState())).toBe(false)
  })

  it('selectValidationIssuesByNode filters issues by nodeId', () => {
    const issues: ValidationIssue[] = [
      {
        id: 'v1',
        severity: 'error',
        scope: 'node',
        message: 'Error on n1',
        nodeId: 'n1',
        code: 'configInvalid',
      },
      {
        id: 'v2',
        severity: 'warning',
        scope: 'node',
        message: 'Warning on n2',
        nodeId: 'n2',
        code: 'orphanNode',
      },
      {
        id: 'v3',
        severity: 'error',
        scope: 'node',
        message: 'Another error on n1',
        nodeId: 'n1',
        code: 'missingRequiredInput',
      },
    ]
    useWorkflowStore.getState().setValidationIssues(issues)

    const n1Issues = selectValidationIssuesByNode(useWorkflowStore.getState(), 'n1')
    expect(n1Issues).toHaveLength(2)
    expect(n1Issues[0].id).toBe('v1')
    expect(n1Issues[1].id).toBe('v3')

    const n2Issues = selectValidationIssuesByNode(useWorkflowStore.getState(), 'n2')
    expect(n2Issues).toHaveLength(1)

    const n3Issues = selectValidationIssuesByNode(useWorkflowStore.getState(), 'n3')
    expect(n3Issues).toHaveLength(0)
  })

  it('selectCanUndo / selectCanRedo reflect undo stack state', () => {
    expect(selectCanUndo(useWorkflowStore.getState())).toBe(false)
    expect(selectCanRedo(useWorkflowStore.getState())).toBe(false)

    useWorkflowStore.getState().addNode(sampleNode)
    expect(selectCanUndo(useWorkflowStore.getState())).toBe(true)
    expect(selectCanRedo(useWorkflowStore.getState())).toBe(false)

    useWorkflowStore.getState().undo()
    expect(selectCanUndo(useWorkflowStore.getState())).toBe(false)
    expect(selectCanRedo(useWorkflowStore.getState())).toBe(true)
  })
})
