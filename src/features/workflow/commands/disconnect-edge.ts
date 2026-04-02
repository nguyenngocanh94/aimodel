import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';

/**
 * Remove an edge by id.
 * Returns a recipe function for commitAuthoring.
 */
export function disconnectEdge(
  edgeId: string,
): (doc: WorkflowDocument) => WorkflowDocument {
  return (doc) => ({
    ...doc,
    edges: doc.edges.filter((e) => e.id !== edgeId),
  });
}
