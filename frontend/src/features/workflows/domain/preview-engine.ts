/**
 * Preview Engine - AiModel-1n1.2
 *
 * Deterministic preview derivation engine for the AI Video Workflow Builder.
 * Computes instant, synchronous derived outputs as users edit the graph.
 *
 * This is NOT mock execution. Preview is immediate, cheap, UI-focused,
 * and may omit runtime metadata. Mock execution is run-oriented, produces
 * statuses/timings, writes run records, and is cancellable.
 *
 * Core responsibilities:
 * 1. Derive sample outputs from config + upstream previews
 * 2. Recompute incrementally (topological order)
 * 3. Invalidate downstream previews when upstream changes
 * 4. Stay deterministic (same inputs → same output)
 * 5. Support fixture selection
 */

import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  PortPayload,
} from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';

// ============================================================
// LRU Memoization Cache
// ============================================================

const MAX_CACHE_SIZE = 100;
const previewCache = new Map<string, Readonly<Record<string, PortPayload>>>();

function buildCacheKey(
  nodeType: string,
  config: unknown,
  inputs: Readonly<Record<string, PortPayload>>,
): string {
  return JSON.stringify({ nodeType, config, inputs });
}

function getCached(
  key: string,
): Readonly<Record<string, PortPayload>> | undefined {
  const cached = previewCache.get(key);
  if (cached !== undefined) {
    // Move to end (most recently used) by deleting and re-inserting
    previewCache.delete(key);
    previewCache.set(key, cached);
  }
  return cached;
}

function setCached(
  key: string,
  value: Readonly<Record<string, PortPayload>>,
): void {
  // Evict oldest entry if at capacity
  if (previewCache.size >= MAX_CACHE_SIZE) {
    const oldestKey = previewCache.keys().next().value;
    if (oldestKey !== undefined) {
      previewCache.delete(oldestKey);
    }
  }
  previewCache.set(key, value);
}

/**
 * Clear the memoization cache. Useful for testing or full resets.
 */
export function clearPreviewCache(): void {
  previewCache.clear();
}

// ============================================================
// Topological Sort — Kahn's Algorithm
// ============================================================

/**
 * Get topological order of nodes in the document using Kahn's algorithm.
 * Returns node IDs in execution order. Skips nodes involved in cycles
 * (those will be caught by the validator).
 *
 * Disconnected nodes are included in the result (they have zero in-degree
 * and are processed early).
 */
export function topologicalSort(
  nodes: readonly WorkflowNode[],
  edges: readonly WorkflowEdge[],
): readonly string[] {
  const nodeIds = new Set(nodes.map((n) => n.id));

  // Build adjacency list and in-degree map
  const inDegree = new Map<string, number>();
  const adjacency = new Map<string, string[]>();

  for (const id of nodeIds) {
    inDegree.set(id, 0);
    adjacency.set(id, []);
  }

  for (const edge of edges) {
    // Skip edges referencing non-existent nodes
    if (!nodeIds.has(edge.sourceNodeId) || !nodeIds.has(edge.targetNodeId)) {
      continue;
    }
    // Skip self-loops
    if (edge.sourceNodeId === edge.targetNodeId) {
      continue;
    }

    const targets = adjacency.get(edge.sourceNodeId);
    if (targets) {
      targets.push(edge.targetNodeId);
    }
    inDegree.set(
      edge.targetNodeId,
      (inDegree.get(edge.targetNodeId) ?? 0) + 1,
    );
  }

  // Seed queue with zero-in-degree nodes (stable order by original position)
  const queue: string[] = [];
  for (const node of nodes) {
    if ((inDegree.get(node.id) ?? 0) === 0) {
      queue.push(node.id);
    }
  }

  const sorted: string[] = [];

  while (queue.length > 0) {
    const current = queue.shift()!;
    sorted.push(current);

    const neighbors = adjacency.get(current) ?? [];
    for (const neighbor of neighbors) {
      const deg = (inDegree.get(neighbor) ?? 1) - 1;
      inDegree.set(neighbor, deg);
      if (deg === 0) {
        queue.push(neighbor);
      }
    }
  }

  // Nodes still with in-degree > 0 are part of cycles — omit them.
  return sorted;
}

// ============================================================
// Compute Node Preview
// ============================================================

/**
 * Compute preview for a single node given its upstream payloads.
 *
 * Looks up the node's template via `getTemplate(node.type)` and calls
 * `template.buildPreview({ config, inputs })`. Returns an empty record
 * if the template is not found.
 *
 * Results are memoized by a hash of (nodeType, config, input keys/values).
 */
export function computeNodePreview(
  node: WorkflowNode,
  upstreamPayloads: Readonly<Record<string, PortPayload>>,
): Readonly<Record<string, PortPayload>> {
  const template = getTemplate(node.type);
  if (!template) {
    return {};
  }

  const cacheKey = buildCacheKey(node.type, node.config, upstreamPayloads);
  const cached = getCached(cacheKey);
  if (cached !== undefined) {
    return cached;
  }

  const result = template.buildPreview({
    config: node.config,
    inputs: upstreamPayloads,
  });

  setCached(cacheKey, result);
  return result;
}

// ============================================================
// Compute All Previews
// ============================================================

/**
 * Compute all previews for a workflow document.
 *
 * 1. Topologically sorts the nodes.
 * 2. For each node in order, gathers upstream payloads from the previews
 *    of connected source nodes (via edges).
 * 3. Calls `computeNodePreview` and stores the result.
 *
 * For gathering upstream payloads: for each edge targeting this node,
 * look up the source node's preview output for the source port key,
 * and place it in the inputs map keyed by the target port key.
 */
export function computeAllPreviews(
  document: WorkflowDocument,
): ReadonlyMap<string, Readonly<Record<string, PortPayload>>> {
  const { nodes, edges } = document;
  const sortedIds = topologicalSort(nodes, edges);

  // Build a map of nodeId → WorkflowNode for quick lookup
  const nodeMap = new Map(nodes.map((n) => [n.id, n]));

  // Build a map of targetNodeId → edges targeting it
  const incomingEdges = new Map<string, WorkflowEdge[]>();
  for (const edge of edges) {
    const list = incomingEdges.get(edge.targetNodeId);
    if (list) {
      list.push(edge);
    } else {
      incomingEdges.set(edge.targetNodeId, [edge]);
    }
  }

  const previews = new Map<string, Readonly<Record<string, PortPayload>>>();

  for (const nodeId of sortedIds) {
    const node = nodeMap.get(nodeId);
    if (!node) continue;

    // Gather upstream payloads
    const upstreamPayloads: Record<string, PortPayload> = {};
    const incoming = incomingEdges.get(nodeId) ?? [];

    for (const edge of incoming) {
      const sourcePreview = previews.get(edge.sourceNodeId);
      if (sourcePreview) {
        const sourcePayload = sourcePreview[edge.sourcePortKey];
        if (sourcePayload) {
          upstreamPayloads[edge.targetPortKey] = sourcePayload;
        }
      }
    }

    const nodePreview = computeNodePreview(node, upstreamPayloads);
    previews.set(nodeId, nodePreview);
  }

  return previews;
}

// ============================================================
// Incremental Recompute
// ============================================================

/**
 * Get IDs of all transitive downstream nodes from a given node.
 */
export function getDownstreamNodeIds(
  document: WorkflowDocument,
  nodeId: string,
): readonly string[] {
  const downstream = new Set<string>();
  const queue = [nodeId];
  while (queue.length > 0) {
    const current = queue.shift()!;
    for (const edge of document.edges) {
      if (edge.sourceNodeId === current && !downstream.has(edge.targetNodeId)) {
        downstream.add(edge.targetNodeId);
        queue.push(edge.targetNodeId);
      }
    }
  }
  return Array.from(downstream);
}

/**
 * Incrementally recompute previews starting from a changed node.
 *
 * 1. Determine which downstream nodes need recompute.
 * 2. In topological order, recompute the changed node and all downstream.
 * 3. Return updated preview map (existing previews for unchanged nodes preserved).
 */
export function computeIncrementalPreviews(
  document: WorkflowDocument,
  changedNodeId: string,
  existing: ReadonlyMap<string, Readonly<Record<string, PortPayload>>>,
): ReadonlyMap<string, Readonly<Record<string, PortPayload>>> {
  const { nodes, edges } = document;
  const sortedIds = topologicalSort(nodes, edges);

  const downstreamIds = new Set(getDownstreamNodeIds(document, changedNodeId));
  downstreamIds.add(changedNodeId);

  const nodeMap = new Map(nodes.map((n) => [n.id, n]));

  const incomingEdges = new Map<string, WorkflowEdge[]>();
  for (const edge of edges) {
    const list = incomingEdges.get(edge.targetNodeId);
    if (list) {
      list.push(edge);
    } else {
      incomingEdges.set(edge.targetNodeId, [edge]);
    }
  }

  // Start from existing previews, then overwrite affected nodes
  const previews = new Map(existing);

  for (const nodeId of sortedIds) {
    if (!downstreamIds.has(nodeId)) continue;

    const node = nodeMap.get(nodeId);
    if (!node) continue;

    if (node.disabled) {
      previews.delete(nodeId);
      continue;
    }

    const upstreamPayloads: Record<string, PortPayload> = {};
    for (const edge of incomingEdges.get(nodeId) ?? []) {
      const sourcePreview = previews.get(edge.sourceNodeId);
      if (sourcePreview) {
        const payload = sourcePreview[edge.sourcePortKey];
        if (payload) {
          upstreamPayloads[edge.targetPortKey] = payload;
        }
      }
    }

    const template = getTemplate(node.type);
    if (!template) {
      previews.delete(nodeId);
      continue;
    }

    // Check for missing required inputs — stop here if missing
    const missingRequired = template.inputs.some(
      (port) => port.required && !upstreamPayloads[port.key],
    );
    if (missingRequired && nodeId !== changedNodeId) {
      // Keep existing preview if available, skip recompute
      continue;
    }

    const result = computeNodePreview(node, upstreamPayloads);
    previews.set(nodeId, result);
  }

  return previews;
}
