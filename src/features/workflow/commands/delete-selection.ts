import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';

/**
 * Delete selected nodes (and all connected edges) plus a selected edge.
 * Returns a recipe function for commitAuthoring.
 */
export function deleteSelection(
  nodeIds: readonly string[],
  edgeId: string | null,
): (doc: WorkflowDocument) => WorkflowDocument {
  return (doc) => {
    const nodeSet = new Set(nodeIds);
    return {
      ...doc,
      nodes: doc.nodes.filter((n) => !nodeSet.has(n.id)),
      edges: doc.edges.filter(
        (e) =>
          e.id !== edgeId &&
          !nodeSet.has(e.sourceNodeId) &&
          !nodeSet.has(e.targetNodeId),
      ),
    };
  };
}
