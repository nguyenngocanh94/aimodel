import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';

const OFFSET = 30;

/**
 * Duplicate selected nodes at an offset position.
 * Returns a recipe function for commitAuthoring and the new node IDs.
 */
export function duplicateNodes(
  nodeIds: readonly string[],
): {
  recipe: (doc: WorkflowDocument) => WorkflowDocument;
  getNewIds: (doc: WorkflowDocument) => string[];
} {
  const idMap = new Map<string, string>();
  for (const id of nodeIds) {
    idMap.set(id, `node-${crypto.randomUUID().slice(0, 8)}`);
  }

  const recipe = (doc: WorkflowDocument): WorkflowDocument => {
    const toDuplicate = doc.nodes.filter((n) => nodeIds.includes(n.id));
    const newNodes = toDuplicate.map((n) => ({
      ...n,
      id: idMap.get(n.id)!,
      position: { x: n.position.x + OFFSET, y: n.position.y + OFFSET },
    }));
    return { ...doc, nodes: [...doc.nodes, ...newNodes] };
  };

  const getNewIds = () => Array.from(idMap.values());

  return { recipe, getNewIds };
}
