import type { WorkflowNode, WorkflowDocument } from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';

/**
 * Create a new node from a template type at the given position.
 * Returns a recipe function for commitAuthoring.
 */
export function addNode(
  templateType: string,
  position: { readonly x: number; readonly y: number },
): (doc: WorkflowDocument) => WorkflowDocument {
  return (doc) => {
    const template = getTemplate(templateType);
    if (!template) return doc;

    const newNode: WorkflowNode = {
      id: `node-${crypto.randomUUID().slice(0, 8)}`,
      type: template.type,
      label: template.title,
      position: { x: position.x, y: position.y },
      config: template.defaultConfig,
    };

    return {
      ...doc,
      nodes: [...doc.nodes, newNode],
    };
  };
}
