import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';

export interface UpdateNodeConfigResult {
  readonly success: boolean;
  readonly errors?: readonly string[];
}

/**
 * Validate config against the node's Zod schema, then return a recipe
 * to apply it. Returns validation errors if invalid.
 */
export function updateNodeConfig(
  nodeId: string,
  config: unknown,
  doc: WorkflowDocument,
): UpdateNodeConfigResult & { recipe?: (doc: WorkflowDocument) => WorkflowDocument } {
  const node = doc.nodes.find((n) => n.id === nodeId);
  if (!node) {
    return { success: false, errors: ['Node not found'] };
  }

  const template = getTemplate(node.type);
  if (!template) {
    return { success: false, errors: ['Template not found'] };
  }

  // Validate with Zod schema when available (pilot templates rely on backend validation)
  if (template.configSchema) {
    const parseResult = template.configSchema.safeParse(config);
    if (!parseResult.success) {
      const errors = parseResult.error.issues.map(
        (issue) => `${issue.path.join('.')}: ${issue.message}`,
      );
      return { success: false, errors };
    }
  }

  const validatedConfig = config;

  const recipe = (d: WorkflowDocument): WorkflowDocument => ({
    ...d,
    nodes: d.nodes.map((n) =>
      n.id === nodeId
        ? ({ ...n, config: validatedConfig } as typeof n)
        : n,
    ),
  });

  return { success: true, recipe };
}
