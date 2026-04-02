import { create } from 'zustand'

import type {
  ValidationIssue,
  WorkflowDocument,
  WorkflowEdge,
  WorkflowNode,
} from '@/features/workflows/domain/workflow-types'

/** Inspector tab selection; authoring-adjacent but not part of undo/redo. */
export type InspectorTab = 'config' | 'validation' | 'metadata' | 'data'

export interface LibraryUiState {
  readonly searchQuery: string
  readonly expandedCategoryIds: readonly string[]
  readonly displayMode: 'compact' | 'expanded'
}

export interface ValidationSummaryState {
  readonly issues: readonly ValidationIssue[]
}

export const MAX_HISTORY = 50

function touchDocument(document: WorkflowDocument): WorkflowDocument {
  return {
    ...document,
    updatedAt: new Date().toISOString(),
  }
}

export function createEmptyWorkflowDocument(id: string): WorkflowDocument {
  const now = new Date().toISOString()
  return {
    id,
    schemaVersion: 1,
    name: 'Untitled workflow',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
  }
}

export interface WorkflowStoreState {
  readonly document: WorkflowDocument
  /** Authoring snapshots for undo; excludes selection, library, inspector, validation, previews. */
  readonly past: readonly WorkflowDocument[]
  readonly future: readonly WorkflowDocument[]
  readonly selectedNodeIds: readonly string[]
  readonly selectedEdgeId: string | null
  readonly libraryUi: LibraryUiState
  readonly librarySearchFilter: string
  readonly inspectorTab: InspectorTab
  readonly dirty: boolean
  readonly lastSavedDocument: WorkflowDocument | null
  readonly validationSummary: ValidationSummaryState
  readonly validationIssues: readonly ValidationIssue[]
  /** In-memory preview payloads keyed by node id; not part of undo. */
  readonly previewCaches: Readonly<Record<string, unknown>>
  readonly viewport: { readonly x: number; readonly y: number; readonly zoom: number }
}

export interface WorkflowStoreActions {
  /** Document-affecting change that participates in undo/redo. */
  readonly commitAuthoring: (
    recipe: (previous: WorkflowDocument) => WorkflowDocument
  ) => void
  /** Replace the entire document (e.g. load, new workflow) without recording undo. */
  readonly resetDocument: (document: WorkflowDocument) => void
  readonly undo: () => void
  readonly redo: () => void
  readonly canUndo: () => boolean
  readonly canRedo: () => boolean

  // Specific document mutation actions (all push to undo stack)
  readonly addNode: (node: WorkflowNode) => void
  readonly removeNode: (nodeId: string) => void
  readonly moveNode: (nodeId: string, position: { x: number; y: number }) => void
  readonly updateNodeConfig: (nodeId: string, config: unknown) => void
  readonly updateNodeLabel: (nodeId: string, label: string) => void
  readonly addEdge: (edge: WorkflowEdge) => void
  readonly removeEdge: (edgeId: string) => void
  readonly updateWorkflowName: (name: string) => void
  readonly updateWorkflowDescription: (description: string) => void
  readonly updateWorkflowTags: (tags: readonly string[]) => void

  /** Pan/zoom; does not push undo history; still marks dirty. */
  readonly setViewport: (viewport: { x: number; y: number; zoom: number }) => void
  readonly setSelectedNodeIds: (ids: readonly string[]) => void
  readonly setSelectedEdgeId: (edgeId: string | null) => void
  readonly setLibraryUi: (partial: Partial<LibraryUiState>) => void
  readonly setLibrarySearchFilter: (filter: string) => void
  readonly setInspectorTab: (tab: InspectorTab) => void
  readonly setValidationSummary: (summary: ValidationSummaryState) => void
  readonly setValidationIssues: (issues: readonly ValidationIssue[]) => void
  readonly setPreviewCaches: (caches: Readonly<Record<string, unknown>>) => void
  readonly setPreviewCache: (nodeId: string, payload: unknown) => void
  readonly clearPreviewCaches: () => void
  readonly markSaved: () => void
  /** Load a document (e.g. from persistence). Resets undo stack and dirty state. */
  readonly loadDocument: (doc: WorkflowDocument) => void
}

export type WorkflowStore = WorkflowStoreState & WorkflowStoreActions

const defaultLibraryUi: LibraryUiState = {
  searchQuery: '',
  expandedCategoryIds: [],
  displayMode: 'expanded',
}

const initialDocument = createEmptyWorkflowDocument(crypto.randomUUID())

export const useWorkflowStore = create<WorkflowStore>((set, get) => ({
  document: initialDocument,
  past: [],
  future: [],
  selectedNodeIds: [],
  selectedEdgeId: null,
  libraryUi: defaultLibraryUi,
  librarySearchFilter: '',
  inspectorTab: 'config',
  dirty: false,
  lastSavedDocument: null,
  validationSummary: { issues: [] },
  validationIssues: [],
  previewCaches: {},
  viewport: { x: 0, y: 0, zoom: 1 },

  commitAuthoring: (recipe) => {
    set((state) => {
      const previous = state.document
      const next = touchDocument(recipe(previous))
      if (next === previous) {
        return state
      }
      const past = [...state.past, previous]
      const trimmedPast =
        past.length > MAX_HISTORY ? past.slice(past.length - MAX_HISTORY) : past
      return {
        ...state,
        document: next,
        dirty: true,
        past: trimmedPast,
        future: [],
      }
    })
  },

  resetDocument: (document) => {
    set({
      document,
      past: [],
      future: [],
      dirty: false,
      lastSavedDocument: document,
      selectedNodeIds: [],
      selectedEdgeId: null,
      previewCaches: {},
      validationSummary: { issues: [] },
      validationIssues: [],
    })
  },

  undo: () => {
    set((state) => {
      if (state.past.length === 0) {
        return state
      }
      const previous = state.past[state.past.length - 1]
      const newPast = state.past.slice(0, -1)
      return {
        ...state,
        document: previous,
        past: newPast,
        future: [state.document, ...state.future],
        dirty: true,
      }
    })
  },

  redo: () => {
    set((state) => {
      if (state.future.length === 0) {
        return state
      }
      const [next, ...restFuture] = state.future
      return {
        ...state,
        document: next,
        past: [...state.past, state.document],
        future: restFuture,
        dirty: true,
      }
    })
  },

  canUndo: () => get().past.length > 0,

  canRedo: () => get().future.length > 0,

  // --- Specific document mutation actions ---

  addNode: (node) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      nodes: [...doc.nodes, node],
    }))
  },

  removeNode: (nodeId) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.filter((n) => n.id !== nodeId),
      edges: doc.edges.filter(
        (e) => e.sourceNodeId !== nodeId && e.targetNodeId !== nodeId
      ),
    }))
  },

  moveNode: (nodeId, position) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n) =>
        n.id === nodeId ? { ...n, position } : n
      ),
    }))
  },

  updateNodeConfig: (nodeId, config) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n): WorkflowNode =>
        n.id === nodeId ? { ...n, config: config as Readonly<unknown> } : n
      ),
    }))
  },

  updateNodeLabel: (nodeId, label) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n) =>
        n.id === nodeId ? { ...n, label } : n
      ),
    }))
  },

  addEdge: (edge) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      edges: [...doc.edges, edge],
    }))
  },

  removeEdge: (edgeId) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      edges: doc.edges.filter((e) => e.id !== edgeId),
    }))
  },

  updateWorkflowName: (name) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      name,
    }))
  },

  updateWorkflowDescription: (description) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      description,
    }))
  },

  updateWorkflowTags: (tags) => {
    get().commitAuthoring((doc) => ({
      ...doc,
      tags,
    }))
  },

  // --- Non-undoable actions ---

  setViewport: (viewport) => {
    set((state) => ({
      ...state,
      viewport,
      document: touchDocument({ ...state.document, viewport }),
      dirty: true,
    }))
  },

  setSelectedNodeIds: (ids) => {
    set({ selectedNodeIds: ids })
  },

  setSelectedEdgeId: (edgeId) => {
    set({ selectedEdgeId: edgeId })
  },

  setLibraryUi: (partial) => {
    set((state) => ({
      ...state,
      libraryUi: { ...state.libraryUi, ...partial },
    }))
  },

  setLibrarySearchFilter: (filter) => {
    set({ librarySearchFilter: filter })
  },

  setInspectorTab: (inspectorTab) => {
    set({ inspectorTab })
  },

  setValidationSummary: (validationSummary) => {
    set({ validationSummary })
  },

  setValidationIssues: (validationIssues) => {
    set({ validationIssues })
  },

  setPreviewCaches: (previewCaches) => {
    set({ previewCaches })
  },

  setPreviewCache: (nodeId, payload) => {
    set((state) => ({
      ...state,
      previewCaches: { ...state.previewCaches, [nodeId]: payload },
    }))
  },

  clearPreviewCaches: () => {
    set({ previewCaches: {} })
  },

  markSaved: () => {
    set((state) => ({
      dirty: false,
      lastSavedDocument: state.document,
    }))
  },

  loadDocument: (doc) => {
    set({
      document: doc,
      past: [],
      future: [],
      dirty: false,
      lastSavedDocument: doc,
      selectedNodeIds: [],
      selectedEdgeId: null,
      previewCaches: {},
      validationSummary: { issues: [] },
      validationIssues: [],
    })
  },
}))
