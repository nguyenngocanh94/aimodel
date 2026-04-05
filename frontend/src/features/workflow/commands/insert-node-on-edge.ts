import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  PortDefinition,
  DataType,
} from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';
import { checkCompatibility } from '@/features/workflows/domain/type-compatibility';

// ============================================================
// Port Matching Algorithm (plan 6.3.2)
// ============================================================

/** Score: exact match = 100, coercion = 60, adapter = 40, incompatible = -1 */
function scorePortPair(sourceType: DataType, targetType: DataType): number {
  const compat = checkCompatibility(sourceType, targetType);
  if (!compat.compatible) return -1;
  if (!compat.coercionApplied) return 100; // exact
  if (compat.suggestedAdapterNodeType) return 40; // adapter
  return 60; // explicit coercion
}

interface PortPairCandidate {
  readonly inputPort: PortDefinition;
  readonly outputPort: PortDefinition;
  readonly inScore: number; // source-edge -> newNode input
  readonly outScore: number; // newNode output -> target-edge
  readonly totalScore: number;
}

function findBestPortPairs(
  edgeSourcePort: PortDefinition,
  edgeTargetPort: PortDefinition,
  newInputs: readonly PortDefinition[],
  newOutputs: readonly PortDefinition[],
): PortPairCandidate[] {
  const candidates: PortPairCandidate[] = [];

  for (const inp of newInputs) {
    for (const out of newOutputs) {
      const inScore = scorePortPair(edgeSourcePort.dataType, inp.dataType);
      const outScore = scorePortPair(out.dataType, edgeTargetPort.dataType);
      if (inScore < 0 || outScore < 0) continue;

      let total = inScore + outScore;
      // Tie-breakers: prefer required ports
      if (inp.required) total += 1;
      if (out.required) total += 1;

      candidates.push({
        inputPort: inp,
        outputPort: out,
        inScore,
        outScore,
        totalScore: total,
      });
    }
  }

  // Sort descending by total score
  candidates.sort((a, b) => b.totalScore - a.totalScore);
  return candidates;
}

// ============================================================
// Insert Node On Edge Command (plan 6.3.1)
// ============================================================

export interface InsertNodeOnEdgeArgs {
  readonly edgeId: string;
  readonly newNodeType: string;
  readonly preferredInputPortKey?: string;
  readonly preferredOutputPortKey?: string;
}

export type InsertNodeOnEdgeResult =
  | { readonly status: 'inserted'; readonly newNodeId: string }
  | { readonly status: 'ambiguous'; readonly candidates: readonly PortPairCandidate[] }
  | { readonly status: 'incompatible'; readonly reason: string };

/**
 * Insert a new node onto an existing edge, auto-reconnecting ports.
 * Returns the result and a recipe for commitAuthoring (when status === 'inserted').
 */
export function insertNodeOnEdge(
  args: InsertNodeOnEdgeArgs,
  doc: WorkflowDocument,
): InsertNodeOnEdgeResult & { recipe?: (doc: WorkflowDocument) => WorkflowDocument } {
  const { edgeId, newNodeType, preferredInputPortKey, preferredOutputPortKey } = args;

  const edge = doc.edges.find((e) => e.id === edgeId);
  if (!edge) {
    return { status: 'incompatible', reason: 'Edge not found' };
  }

  const sourceNode = doc.nodes.find((n) => n.id === edge.sourceNodeId);
  const targetNode = doc.nodes.find((n) => n.id === edge.targetNodeId);
  if (!sourceNode || !targetNode) {
    return { status: 'incompatible', reason: 'Source or target node not found' };
  }

  const sourceTemplate = getTemplate(sourceNode.type);
  const targetTemplate = getTemplate(targetNode.type);
  const newTemplate = getTemplate(newNodeType);
  if (!sourceTemplate || !targetTemplate || !newTemplate) {
    return { status: 'incompatible', reason: 'Template not found' };
  }

  // Find the ports on the original edge
  const edgeSourcePort = sourceTemplate.outputs.find((p) => p.key === edge.sourcePortKey);
  const edgeTargetPort = targetTemplate.inputs.find((p) => p.key === edge.targetPortKey);
  if (!edgeSourcePort || !edgeTargetPort) {
    return { status: 'incompatible', reason: 'Edge port definitions not found' };
  }

  // Score port pairs
  const candidates = findBestPortPairs(
    edgeSourcePort,
    edgeTargetPort,
    newTemplate.inputs,
    newTemplate.outputs,
  );

  if (candidates.length === 0) {
    return { status: 'incompatible', reason: 'No compatible port pairs found' };
  }

  // Apply preferred ports if specified
  let chosen = candidates[0];
  if (preferredInputPortKey || preferredOutputPortKey) {
    const preferred = candidates.find(
      (c) =>
        (!preferredInputPortKey || c.inputPort.key === preferredInputPortKey) &&
        (!preferredOutputPortKey || c.outputPort.key === preferredOutputPortKey),
    );
    if (preferred) chosen = preferred;
  }

  // Check for ties at the top score
  const topScore = candidates[0].totalScore;
  const tiedCandidates = candidates.filter((c) => c.totalScore === topScore);
  if (tiedCandidates.length > 1 && !preferredInputPortKey && !preferredOutputPortKey) {
    return { status: 'ambiguous', candidates: tiedCandidates };
  }

  // Create the node at the midpoint of the two connected nodes
  const midX = (sourceNode.position.x + targetNode.position.x) / 2;
  const midY = (sourceNode.position.y + targetNode.position.y) / 2;
  const newNodeId = `node-${crypto.randomUUID().slice(0, 8)}`;

  const recipe = (d: WorkflowDocument): WorkflowDocument => {
    const newNode: WorkflowNode = {
      id: newNodeId,
      type: newTemplate.type,
      label: newTemplate.title,
      position: { x: midX, y: midY },
      config: newTemplate.defaultConfig,
    };

    // Remove the original edge, add two replacement edges
    const inEdge: WorkflowEdge = {
      id: `edge-${edge.sourceNodeId}-${edge.sourcePortKey}-${newNodeId}-${chosen.inputPort.key}`,
      sourceNodeId: edge.sourceNodeId,
      sourcePortKey: edge.sourcePortKey,
      targetNodeId: newNodeId,
      targetPortKey: chosen.inputPort.key,
    };

    const outEdge: WorkflowEdge = {
      id: `edge-${newNodeId}-${chosen.outputPort.key}-${edge.targetNodeId}-${edge.targetPortKey}`,
      sourceNodeId: newNodeId,
      sourcePortKey: chosen.outputPort.key,
      targetNodeId: edge.targetNodeId,
      targetPortKey: edge.targetPortKey,
    };

    return {
      ...d,
      nodes: [...d.nodes, newNode],
      edges: [...d.edges.filter((e) => e.id !== edgeId), inEdge, outEdge],
    };
  };

  return { status: 'inserted', newNodeId, recipe };
}
