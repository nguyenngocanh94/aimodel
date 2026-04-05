/**
 * RunPlanner - AiModel-ecs.2
 * Determines execution scope and ordering per plan section 11.3
 */

import type {
  WorkflowDocument,
  WorkflowEdge,
  ExecutionRun,
} from '@/features/workflows/domain/workflow-types';
import type { ExecutionPlan } from './execution-types';

// ============================================================
// Edge index helpers
// ============================================================

/** Build a map of nodeId → incoming edges (edges targeting that node). */
export function indexIncomingEdges(
  edges: readonly WorkflowEdge[],
): ReadonlyMap<string, readonly WorkflowEdge[]> {
  const map = new Map<string, WorkflowEdge[]>();
  for (const edge of edges) {
    const list = map.get(edge.targetNodeId);
    if (list) {
      list.push(edge);
    } else {
      map.set(edge.targetNodeId, [edge]);
    }
  }
  return map;
}

/** Build a map of nodeId → outgoing edges (edges sourced from that node). */
export function indexOutgoingEdges(
  edges: readonly WorkflowEdge[],
): ReadonlyMap<string, readonly WorkflowEdge[]> {
  const map = new Map<string, WorkflowEdge[]>();
  for (const edge of edges) {
    const list = map.get(edge.sourceNodeId);
    if (list) {
      list.push(edge);
    } else {
      map.set(edge.sourceNodeId, [edge]);
    }
  }
  return map;
}

// ============================================================
// Graph traversal helpers (per plan 11.3.2)
// ============================================================

/** Collect all upstream node IDs reachable from targetNodeId (not including targetNodeId). */
export function collectUpstreamNodeIds(
  targetNodeId: string,
  incomingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
): Set<string> {
  const visited = new Set<string>();
  const stack = [targetNodeId];

  while (stack.length > 0) {
    const current = stack.pop()!;
    for (const edge of incomingByNode.get(current) ?? []) {
      if (!visited.has(edge.sourceNodeId)) {
        visited.add(edge.sourceNodeId);
        stack.push(edge.sourceNodeId);
      }
    }
  }

  return visited;
}

/** Collect all downstream node IDs reachable from sourceNodeId (not including sourceNodeId). */
export function collectDownstreamNodeIds(
  sourceNodeId: string,
  outgoingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
): Set<string> {
  const visited = new Set<string>();
  const stack = [sourceNodeId];

  while (stack.length > 0) {
    const current = stack.pop()!;
    for (const edge of outgoingByNode.get(current) ?? []) {
      if (!visited.has(edge.targetNodeId)) {
        visited.add(edge.targetNodeId);
        stack.push(edge.targetNodeId);
      }
    }
  }

  return visited;
}

// ============================================================
// Topological sort (Kahn's algorithm, per plan 11.3.3)
// ============================================================

/** Topologically sort a subgraph. Throws if the subgraph contains a cycle. */
export function topologicallySortSubgraph(args: {
  readonly nodeIds: ReadonlySet<string>;
  readonly edges: readonly WorkflowEdge[];
}): string[] {
  const indegree = new Map<string, number>();
  const outgoing = new Map<string, string[]>();

  for (const nodeId of args.nodeIds) {
    indegree.set(nodeId, 0);
    outgoing.set(nodeId, []);
  }

  for (const edge of args.edges) {
    if (
      !args.nodeIds.has(edge.sourceNodeId) ||
      !args.nodeIds.has(edge.targetNodeId)
    ) {
      continue;
    }

    indegree.set(
      edge.targetNodeId,
      (indegree.get(edge.targetNodeId) ?? 0) + 1,
    );
    outgoing.get(edge.sourceNodeId)!.push(edge.targetNodeId);
  }

  const queue = [...indegree.entries()]
    .filter(([, count]) => count === 0)
    .map(([nodeId]) => nodeId);

  const ordered: string[] = [];

  while (queue.length > 0) {
    const nodeId = queue.shift()!;
    ordered.push(nodeId);

    for (const nextNodeId of outgoing.get(nodeId) ?? []) {
      const nextCount = (indegree.get(nextNodeId) ?? 0) - 1;
      indegree.set(nextNodeId, nextCount);
      if (nextCount === 0) {
        queue.push(nextNodeId);
      }
    }
  }

  if (ordered.length !== args.nodeIds.size) {
    throw new Error('Cannot plan execution for cyclic subgraph');
  }

  return ordered;
}

// ============================================================
// Scope extraction helpers
// ============================================================

function isNodeDisabled(
  nodeId: string,
  workflow: WorkflowDocument,
): boolean {
  const node = workflow.nodes.find((n) => n.id === nodeId);
  return node?.disabled === true;
}

// ============================================================
// RunPlanner (per plan 11.3.4)
// ============================================================

export function planExecution(args: {
  readonly workflow: WorkflowDocument;
  readonly trigger: ExecutionRun['trigger'];
  readonly targetNodeId?: string;
}): ExecutionPlan {
  const { workflow, trigger, targetNodeId } = args;
  const incomingByNode = indexIncomingEdges(workflow.edges);
  const outgoingByNode = indexOutgoingEdges(workflow.edges);

  // Phase 1: compute candidate scope per trigger type
  let candidateIds: Set<string>;
  const skippedNodeIds: string[] = [];

  switch (trigger) {
    case 'runWorkflow': {
      // All nodes — disabled ones will be pruned in phase 2
      candidateIds = new Set(workflow.nodes.map((n) => n.id));
      break;
    }

    case 'runNode': {
      if (!targetNodeId) {
        throw new Error('runNode requires a targetNodeId');
      }
      candidateIds = new Set([targetNodeId]);
      // Add upstream providers needed for inputs
      const upstream = collectUpstreamNodeIds(targetNodeId, incomingByNode);
      for (const upId of upstream) {
        if (!isNodeDisabled(upId, workflow)) {
          candidateIds.add(upId);
        }
      }
      break;
    }

    case 'runFromHere': {
      if (!targetNodeId) {
        throw new Error('runFromHere requires a targetNodeId');
      }
      candidateIds = new Set([targetNodeId]);
      const downstream = collectDownstreamNodeIds(targetNodeId, outgoingByNode);
      for (const downId of downstream) {
        if (!isNodeDisabled(downId, workflow)) {
          candidateIds.add(downId);
        }
      }
      break;
    }

    case 'runUpToHere': {
      if (!targetNodeId) {
        throw new Error('runUpToHere requires a targetNodeId');
      }
      candidateIds = new Set([targetNodeId]);
      const upstream = collectUpstreamNodeIds(targetNodeId, incomingByNode);
      for (const upId of upstream) {
        if (!isNodeDisabled(upId, workflow)) {
          candidateIds.add(upId);
        }
      }
      break;
    }
  }

  // Phase 2: identify skipped nodes (disabled within candidate set)
  for (const nodeId of candidateIds) {
    if (isNodeDisabled(nodeId, workflow)) {
      skippedNodeIds.push(nodeId);
    }
  }

  // Remove disabled from execution scope
  for (const nodeId of skippedNodeIds) {
    candidateIds.delete(nodeId);
  }

  // Phase 3: topological sort
  const orderedNodeIds = topologicallySortSubgraph({
    nodeIds: candidateIds,
    edges: workflow.edges,
  });

  return {
    runId: crypto.randomUUID(),
    workflowId: workflow.id,
    trigger,
    targetNodeId,
    scopeNodeIds: [...candidateIds],
    orderedNodeIds,
    skippedNodeIds,
  };
}
