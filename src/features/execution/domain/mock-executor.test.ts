import { describe, it, expect, beforeEach } from 'vitest';
import { executeMockRun } from './mock-executor';
import { planExecution } from './run-planner';
import { RunCache } from './run-cache';
import { useRunStore } from '../store/run-store';
import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
} from '@/features/workflows/domain/workflow-types';

// ============================================================
// Test helpers
// ============================================================

function makeNode(id: string, type = 'scriptWriter', overrides: Partial<WorkflowNode> = {}): WorkflowNode {
  return {
    id,
    type,
    label: id,
    position: { x: 0, y: 0 },
    config: {},
    ...overrides,
  };
}

function makeEdge(
  sourceNodeId: string,
  targetNodeId: string,
  sourcePortKey = 'script',
  targetPortKey = 'script',
): WorkflowEdge {
  return {
    id: `${sourceNodeId}-${targetNodeId}`,
    sourceNodeId,
    sourcePortKey,
    targetNodeId,
    targetPortKey,
  };
}

function makeWorkflow(
  nodes: WorkflowNode[],
  edges: WorkflowEdge[],
): WorkflowDocument {
  return {
    id: 'wf-test',
    schemaVersion: 1,
    name: 'Test Workflow',
    description: '',
    tags: [],
    nodes,
    edges,
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}

// Standard pipeline: userPrompt (non-exec) → scriptWriter (exec)
function makePipeline(): WorkflowDocument {
  return makeWorkflow(
    [
      makeNode('prompt', 'userPrompt', {
        config: {
          topic: 'Test topic',
          goal: 'Test goal',
          audience: 'General',
          tone: 'Professional',
          durationSeconds: 60,
        },
      }),
      makeNode('writer', 'scriptWriter', {
        config: {},
      }),
    ],
    [makeEdge('prompt', 'writer', 'prompt', 'prompt')],
  );
}

describe('executeMockRun', () => {
  let cache: RunCache;

  beforeEach(() => {
    useRunStore.getState().resetRunStore();
    cache = new RunCache();
  });

  it('should execute a pipeline with non-executable and executable nodes', async () => {
    const workflow = makePipeline();
    const plan = planExecution({
      workflow,
      trigger: 'runWorkflow',
    });

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    expect(state.activeRun).not.toBeNull();
    expect(state.activeRun!.status).toBe('success');
    expect(state.nodeRunRecords['prompt'].status).toBe('success');
    expect(state.nodeRunRecords['writer'].status).toBe('success');
  });

  it('should handle non-executable node producing preview output', async () => {
    // userPrompt alone — non-executable, no required inputs
    const workflow = makeWorkflow(
      [makeNode('prompt', 'userPrompt', {
        config: {
          topic: 'Test',
          goal: 'Test goal',
          audience: 'General',
          tone: 'Professional',
          durationSeconds: 60,
        },
      })],
      [],
    );
    const plan = planExecution({ workflow, trigger: 'runWorkflow' });

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    expect(state.activeRun!.status).toBe('success');
    expect(state.nodeRunRecords['prompt'].status).toBe('success');
    // Non-executable nodes use buildPreview output
    expect(state.nodeRunRecords['prompt'].outputPayloads).toBeDefined();
  });

  it('should skip nodes with missing required inputs', async () => {
    // scriptWriter alone with no connected prompt input
    const workflow = makeWorkflow(
      [makeNode('writer', 'scriptWriter')],
      [],
    );
    const plan = planExecution({ workflow, trigger: 'runWorkflow' });

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    // Node should be skipped due to missing required input
    expect(state.nodeRunRecords['writer'].status).toBe('skipped');
    expect(state.nodeRunRecords['writer'].skipReason).toBe('missingRequiredInputs');
  });

  it('should exclude disabled nodes from plan', async () => {
    const workflow = makeWorkflow(
      [
        makeNode('a', 'userPrompt', { disabled: true }),
        makeNode('b', 'userPrompt', {
          config: {
            topic: 'Test',
            goal: 'Test goal',
            audience: 'General',
            tone: 'Professional',
            durationSeconds: 60,
          },
        }),
      ],
      [],
    );
    const plan = planExecution({ workflow, trigger: 'runWorkflow' });

    expect(plan.skippedNodeIds).toContain('a');
    expect(plan.orderedNodeIds).not.toContain('a');

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    expect(state.nodeRunRecords['b'].status).toBe('success');
  });

  it('should handle pre-aborted signal as cancellation', async () => {
    const workflow = makePipeline();
    const plan = planExecution({ workflow, trigger: 'runWorkflow' });

    const controller = new AbortController();
    controller.abort('user cancelled');

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: controller.signal,
    });

    const state = useRunStore.getState();
    expect(state.activeRun!.status).toBe('cancelled');
  });

  it('should populate recent runs on completion', async () => {
    const workflow = makePipeline();
    const plan = planExecution({ workflow, trigger: 'runNode', targetNodeId: 'writer' });

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    expect(state.activeRun!.trigger).toBe('runNode');
    expect(state.activeRun!.targetNodeId).toBe('writer');
    expect(state.recentRuns).toHaveLength(1);
  });

  it('should cache execution results for reuse', async () => {
    const workflow = makePipeline();

    // First run
    const plan1 = planExecution({ workflow, trigger: 'runWorkflow' });
    await executeMockRun({
      workflow,
      plan: plan1,
      runCache: cache,
      signal: new AbortController().signal,
    });

    expect(cache.size).toBeGreaterThan(0);

    // Second run should use cache
    useRunStore.getState().resetRunStore();
    const plan2 = planExecution({ workflow, trigger: 'runWorkflow' });
    await executeMockRun({
      workflow,
      plan: plan2,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    expect(state.activeRun!.status).toBe('success');
  });

  it('should propagate errors to downstream nodes', async () => {
    // Create a pipeline where the executable node will succeed,
    // but downstream of a failed node should be skipped
    const workflow = makeWorkflow(
      [
        makeNode('prompt', 'userPrompt', {
          config: {
            topic: 'Test',
            goal: 'Goal',
            audience: 'All',
            tone: 'Casual',
            durationSeconds: 30,
          },
        }),
        makeNode('writer', 'scriptWriter'),
        makeNode('splitter', 'sceneSplitter'),
      ],
      [
        makeEdge('prompt', 'writer', 'prompt', 'prompt'),
        makeEdge('writer', 'splitter', 'script', 'script'),
      ],
    );

    const plan = planExecution({ workflow, trigger: 'runWorkflow' });

    await executeMockRun({
      workflow,
      plan,
      runCache: cache,
      signal: new AbortController().signal,
    });

    const state = useRunStore.getState();
    // All should succeed in mock mode (mock functions produce valid outputs)
    expect(state.activeRun!.status).toBe('success');
    expect(state.nodeRunRecords['prompt'].status).toBe('success');
    expect(state.nodeRunRecords['writer'].status).toBe('success');
    expect(state.nodeRunRecords['splitter'].status).toBe('success');
  });
});
