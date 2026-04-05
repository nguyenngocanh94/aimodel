/**
 * MockExecutor - AiModel-ecs.3
 * Main execution loop for mock workflow runs.
 * Per plan section 11.4
 */

import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  PortPayload,
} from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';
import type { NodeTemplate } from '@/features/node-registry/node-registry';
import { useRunStore } from '../store/run-store';
import {
  RunCache,
  stableHash,
  normalizeInputsForHash,
  type CacheKeyParts,
} from './run-cache';
import type { ExecutionPlan } from './execution-types';

// ============================================================
// Input resolution (per plan 11.4.2)
// ============================================================

interface ResolvedInputs {
  readonly ok: true;
  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
}

interface UnresolvedInputs {
  readonly ok: false;
  readonly reason: 'missingRequiredInputs' | 'upstreamFailed';
  readonly blockedByNodeIds: readonly string[];
}

type InputResolutionResult = ResolvedInputs | UnresolvedInputs;

function resolveNodeInputs(args: {
  readonly workflow: WorkflowDocument;
  readonly node: WorkflowNode;
  readonly template: NodeTemplate<unknown>;
  readonly runCache: RunCache;
}): InputResolutionResult {
  const { workflow, node, template, runCache } = args;
  const store = useRunStore.getState();
  const incomingEdges = workflow.edges.filter(
    (e) => e.targetNodeId === node.id,
  );

  const inputPayloads: Record<string, PortPayload> = {};
  const blockedByNodeIds: string[] = [];

  for (const inputPort of template.inputs) {
    // Find edge(s) connecting to this input port
    const edgesForPort = incomingEdges.filter(
      (e) => e.targetPortKey === inputPort.key,
    );

    if (edgesForPort.length === 0) {
      if (inputPort.required) {
        return { ok: false, reason: 'missingRequiredInputs', blockedByNodeIds: [] };
      }
      continue;
    }

    // For now, take the first edge (multiple inputs not yet supported in v1 execution)
    const edge = edgesForPort[0];
    const payload = resolveUpstreamOutput(
      edge,
      workflow,
      store.nodeRunRecords,
      runCache,
    );

    if (!payload) {
      if (inputPort.required) {
        blockedByNodeIds.push(edge.sourceNodeId);
        return {
          ok: false,
          reason: 'upstreamFailed',
          blockedByNodeIds,
        };
      }
      continue;
    }

    inputPayloads[inputPort.key] = payload;
  }

  return { ok: true, inputPayloads };
}

/**
 * Resolve an upstream port's output in priority order:
 * 1. Successful upstream output from active run
 * 2. Reusable cache entry
 * 3. Preview output from non-executable node
 */
function resolveUpstreamOutput(
  edge: WorkflowEdge,
  workflow: WorkflowDocument,
  nodeRunRecords: Readonly<Record<string, import('@/features/workflows/domain/workflow-types').NodeRunRecord>>,
  runCache: RunCache,
): PortPayload | null {
  const sourceRecord = nodeRunRecords[edge.sourceNodeId];

  // Priority 1: successful upstream run output
  if (sourceRecord?.status === 'success') {
    const output = sourceRecord.outputPayloads[edge.sourcePortKey];
    if (output) return output;
  }

  // Priority 2: cache entry
  const sourceNode = workflow.nodes.find((n) => n.id === edge.sourceNodeId);
  if (sourceNode) {
    const sourceTemplate = getTemplate(sourceNode.type);
    if (sourceTemplate) {
      const parts = buildCacheKeyParts(sourceNode, sourceTemplate, workflow.schemaVersion, sourceRecord?.inputPayloads ?? {});
      const cached = runCache.getReusableEntry(parts);
      if (cached) {
        const output = cached.outputPayloads[edge.sourcePortKey];
        if (output) return output;
      }
    }
  }

  // Priority 3: preview output from non-executable node
  if (sourceNode) {
    const sourceTemplate = getTemplate(sourceNode.type);
    if (sourceTemplate && !sourceTemplate.executable) {
      const previewOutputs = sourceTemplate.buildPreview({
        config: sourceNode.config,
        inputs: sourceRecord?.inputPayloads ?? {},
      });
      const output = previewOutputs[edge.sourcePortKey];
      if (output) return output;
    }
  }

  return null;
}

// ============================================================
// Cache key helpers
// ============================================================

function buildCacheKeyParts(
  node: WorkflowNode,
  template: NodeTemplate<unknown>,
  schemaVersion: number,
  inputPayloads: Readonly<Record<string, PortPayload>>,
): CacheKeyParts {
  return {
    nodeType: node.type,
    templateVersion: template.templateVersion,
    schemaVersion,
    configHash: stableHash(node.config),
    inputHash: stableHash(normalizeInputsForHash(inputPayloads)),
  };
}

// ============================================================
// Main execution function
// ============================================================

export async function executeMockRun(args: {
  readonly workflow: WorkflowDocument;
  readonly plan: ExecutionPlan;
  readonly runCache: RunCache;
  readonly signal: AbortSignal;
}): Promise<void> {
  const { workflow, plan, runCache, signal } = args;
  const store = useRunStore.getState();

  // Set up run-level abort forwarding
  const runAbortController = new AbortController();
  const forwardAbort = () => runAbortController.abort(signal.reason);
  if (signal.aborted) {
    runAbortController.abort(signal.reason);
  } else {
    signal.addEventListener('abort', forwardAbort, { once: true });
  }

  // Start the run in the store
  store.startRun(
    {
      id: plan.runId,
      workflowId: workflow.id,
      mode: 'mock',
      trigger: plan.trigger,
      targetNodeId: plan.targetNodeId,
      plannedNodeIds: plan.orderedNodeIds,
      status: 'pending',
      startedAt: new Date().toISOString(),
      documentHash: stableHash(workflow),
      nodeConfigHashes: Object.fromEntries(
        workflow.nodes.map((n) => [n.id, stableHash(n.config)]),
      ),
    },
    runAbortController,
  );

  try {
    for (const nodeId of plan.orderedNodeIds) {
      // Check for cancellation
      if (runAbortController.signal.aborted) {
        useRunStore.getState().markPendingNodesCancelled();
        useRunStore.getState().completeRun('cancelled', 'userCancelled');
        return;
      }

      const node = workflow.nodes.find((n) => n.id === nodeId);
      if (!node) continue;

      const template = getTemplate(node.type);
      if (!template) continue;

      // Skip disabled nodes
      if (node.disabled) {
        useRunStore.getState().writeSkippedNode(nodeId, 'disabled');
        continue;
      }

      // Resolve inputs
      const resolvedInputs = resolveNodeInputs({
        workflow,
        node,
        template,
        runCache,
      });

      if (!resolvedInputs.ok) {
        useRunStore.getState().writeSkippedNode(
          nodeId,
          resolvedInputs.reason,
          resolvedInputs.blockedByNodeIds,
        );
        continue;
      }

      // Non-executable nodes: use preview as output
      if (!template.executable) {
        const outputPayloads = template.buildPreview({
          config: node.config,
          inputs: resolvedInputs.inputPayloads,
        });
        useRunStore.getState().writeSucceededNode(nodeId, outputPayloads, 0);
        continue;
      }

      // Check cache
      const parts = buildCacheKeyParts(
        node,
        template,
        workflow.schemaVersion,
        resolvedInputs.inputPayloads,
      );
      const cacheHit = runCache.getReusableEntry(parts);

      if (cacheHit) {
        useRunStore.getState().writeSucceededNode(
          nodeId,
          cacheHit.outputPayloads,
          0,
        );
        continue;
      }

      // Execute the node
      useRunStore.getState().markNodeRunning(nodeId);

      const nodeAbortController = new AbortController();
      const abortNode = () =>
        nodeAbortController.abort(runAbortController.signal.reason);
      runAbortController.signal.addEventListener('abort', abortNode, {
        once: true,
      });

      const startedAt = performance.now();

      try {
        const outputPayloads = await template.mockExecute({
          nodeId,
          config: node.config,
          inputs: resolvedInputs.inputPayloads,
          signal: nodeAbortController.signal,
          runId: plan.runId,
        });

        const durationMs = performance.now() - startedAt;

        // Cache the result
        runCache.put(parts, outputPayloads);

        useRunStore.getState().writeSucceededNode(
          nodeId,
          outputPayloads,
          durationMs,
        );
      } catch (error) {
        const durationMs = performance.now() - startedAt;

        if (nodeAbortController.signal.aborted) {
          useRunStore.getState().writeCancelledNode(nodeId);
          useRunStore.getState().markPendingNodesCancelled();
          useRunStore.getState().completeRun('cancelled', 'userCancelled');
          return;
        }

        useRunStore.getState().writeErroredNode(
          nodeId,
          error instanceof Error
            ? error.message
            : 'Unknown mock execution error',
          durationMs,
        );
      } finally {
        runAbortController.signal.removeEventListener('abort', abortNode);
      }
    }

    // Derive final run status from node states
    useRunStore.getState().completeRunFromNodeStates();
  } finally {
    signal.removeEventListener('abort', forwardAbort);
  }
}
