import { useWorkflowStore } from './workflow-store'
import type { WorkflowStore } from './workflow-store'
import type { WorkflowNode, WorkflowEdge, ValidationIssue } from '@/features/workflows/domain/workflow-types'

// ============================================================
// Plain selector functions (for use with useWorkflowStore(selector))
// ============================================================

export function selectDocument(state: WorkflowStore) {
  return state.document
}

export function selectDirty(state: WorkflowStore) {
  return state.dirty
}

export function selectCanUndo(state: WorkflowStore) {
  return state.past.length > 0
}

export function selectCanRedo(state: WorkflowStore) {
  return state.future.length > 0
}

export function selectSelectedNodeIds(state: WorkflowStore) {
  return state.selectedNodeIds
}

export function selectSelectedEdgeId(state: WorkflowStore) {
  return state.selectedEdgeId
}

export function selectValidationSummary(state: WorkflowStore) {
  return state.validationSummary
}

export function selectValidationIssues(state: WorkflowStore) {
  return state.validationIssues
}

export function selectPreviewCaches(state: WorkflowStore) {
  return state.previewCaches
}

export function selectNodes(state: WorkflowStore) {
  return state.document.nodes
}

export function selectEdges(state: WorkflowStore) {
  return state.document.edges
}

// ============================================================
// Hook-based selectors (for React components)
// ============================================================

export function useDocument() {
  return useWorkflowStore(selectDocument)
}

export function useDirtyFlag() {
  return useWorkflowStore(selectDirty)
}

export function useCanUndo() {
  return useWorkflowStore(selectCanUndo)
}

export function useCanRedo() {
  return useWorkflowStore(selectCanRedo)
}

export function useSelectedNodeIds() {
  return useWorkflowStore(selectSelectedNodeIds)
}

export function useSelectedEdgeId() {
  return useWorkflowStore(selectSelectedEdgeId)
}

/** Returns the actual WorkflowNode objects for the currently selected node IDs. */
export function useSelectedNodes(): readonly WorkflowNode[] {
  return useWorkflowStore((state) => {
    const { selectedNodeIds, document } = state
    if (selectedNodeIds.length === 0) return []
    const idSet = new Set(selectedNodeIds)
    return document.nodes.filter((n) => idSet.has(n.id))
  })
}

/** Returns a single node by ID, or undefined if not found. */
export function useNodeById(nodeId: string): WorkflowNode | undefined {
  return useWorkflowStore((state) =>
    state.document.nodes.find((n) => n.id === nodeId)
  )
}

/** Returns a single edge by ID, or undefined if not found. */
export function useEdgeById(edgeId: string): WorkflowEdge | undefined {
  return useWorkflowStore((state) =>
    state.document.edges.find((e) => e.id === edgeId)
  )
}

/** Returns nodes filtered by type string. */
export function useNodesByType(type: string): readonly WorkflowNode[] {
  return useWorkflowStore((state) =>
    state.document.nodes.filter((n) => n.type === type)
  )
}

/** Returns true if the workflow has no nodes. */
export function useWorkflowIsEmpty(): boolean {
  return useWorkflowStore((state) => state.document.nodes.length === 0)
}

/** Returns validation issues filtered by a specific node ID. */
export function useValidationIssuesByNode(nodeId: string): readonly ValidationIssue[] {
  return useWorkflowStore((state) =>
    state.validationIssues.filter((issue) => issue.nodeId === nodeId)
  )
}

// ============================================================
// Pure selector functions (for non-React or getState() usage)
// ============================================================

export function selectSelectedNodes(state: WorkflowStore): readonly WorkflowNode[] {
  const { selectedNodeIds, document } = state
  if (selectedNodeIds.length === 0) return []
  const idSet = new Set(selectedNodeIds)
  return document.nodes.filter((n) => idSet.has(n.id))
}

export function selectNodeById(state: WorkflowStore, nodeId: string): WorkflowNode | undefined {
  return state.document.nodes.find((n) => n.id === nodeId)
}

export function selectEdgeById(state: WorkflowStore, edgeId: string): WorkflowEdge | undefined {
  return state.document.edges.find((e) => e.id === edgeId)
}

export function selectNodesByType(state: WorkflowStore, type: string): readonly WorkflowNode[] {
  return state.document.nodes.filter((n) => n.type === type)
}

export function selectWorkflowIsEmpty(state: WorkflowStore): boolean {
  return state.document.nodes.length === 0
}

export function selectValidationIssuesByNode(state: WorkflowStore, nodeId: string): readonly ValidationIssue[] {
  return state.validationIssues.filter((issue) => issue.nodeId === nodeId)
}
