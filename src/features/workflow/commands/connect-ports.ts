import type { WorkflowDocument, WorkflowEdge } from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';
import { checkCompatibility } from '@/features/workflows/domain/type-compatibility';

export interface ConnectPortsArgs {
  readonly sourceNodeId: string;
  readonly sourcePortKey: string;
  readonly targetNodeId: string;
  readonly targetPortKey: string;
}

export interface ConnectPortsResult {
  readonly success: boolean;
  readonly reason?: string;
}

/**
 * Validate that a connection is type-compatible, then return a recipe
 * to add the edge. Returns { success: false, reason } if incompatible.
 */
export function connectPorts(
  args: ConnectPortsArgs,
  doc: WorkflowDocument,
): ConnectPortsResult & { recipe?: (doc: WorkflowDocument) => WorkflowDocument } {
  const { sourceNodeId, sourcePortKey, targetNodeId, targetPortKey } = args;

  // Find the source and target nodes
  const sourceNode = doc.nodes.find((n) => n.id === sourceNodeId);
  const targetNode = doc.nodes.find((n) => n.id === targetNodeId);
  if (!sourceNode || !targetNode) {
    return { success: false, reason: 'Source or target node not found' };
  }

  // Prevent self-connections
  if (sourceNodeId === targetNodeId) {
    return { success: false, reason: 'Cannot connect a node to itself' };
  }

  // Look up port definitions from templates
  const sourceTemplate = getTemplate(sourceNode.type);
  const targetTemplate = getTemplate(targetNode.type);
  if (!sourceTemplate || !targetTemplate) {
    return { success: false, reason: 'Source or target template not found' };
  }

  const sourcePort = sourceTemplate.outputs.find((p) => p.key === sourcePortKey);
  const targetPort = targetTemplate.inputs.find((p) => p.key === targetPortKey);
  if (!sourcePort || !targetPort) {
    return { success: false, reason: 'Source or target port not found' };
  }

  // Check type compatibility
  const compat = checkCompatibility(sourcePort.dataType, targetPort.dataType);
  if (!compat.compatible) {
    return { success: false, reason: compat.reason };
  }

  // Prevent duplicate edges to the same target port (unless port is multiple)
  if (!targetPort.multiple) {
    const existing = doc.edges.find(
      (e) => e.targetNodeId === targetNodeId && e.targetPortKey === targetPortKey,
    );
    if (existing) {
      return { success: false, reason: 'Target port already has a connection' };
    }
  }

  const edgeId = `edge-${sourceNodeId}-${sourcePortKey}-${targetNodeId}-${targetPortKey}`;

  const recipe = (d: WorkflowDocument): WorkflowDocument => {
    const newEdge: WorkflowEdge = {
      id: edgeId,
      sourceNodeId,
      sourcePortKey,
      targetNodeId,
      targetPortKey,
    };
    return { ...d, edges: [...d.edges, newEdge] };
  };

  return { success: true, recipe };
}
