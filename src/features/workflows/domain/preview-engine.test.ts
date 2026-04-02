import { describe, it, expect, beforeEach } from 'vitest';
import {
  computeAllPreviews,
  computeNodePreview,
  computeIncrementalPreviews,
  getDownstreamNodeIds,
  topologicalSort,
  clearPreviewCache,
} from './preview-engine';
import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  PortPayload,
} from './workflow-types';

// ============================================================
// Test helpers
// ============================================================

function createTestWorkflow(
  nodes: Partial<WorkflowNode>[],
  edges: Partial<WorkflowEdge>[],
): WorkflowDocument {
  const now = '2026-04-02T00:00:00.000Z';
  return {
    id: 'test-wf',
    schemaVersion: 1,
    name: 'Test Workflow',
    description: '',
    tags: [],
    nodes: nodes.map((n, i) => ({
      id: n.id ?? `node-${i}`,
      type: n.type ?? 'userPrompt',
      label: n.label ?? `Node ${i}`,
      position: n.position ?? { x: i * 100, y: i * 100 },
      config: n.config ?? {},
    })) as readonly WorkflowNode[],
    edges: edges.map((e, i) => ({
      id: e.id ?? `edge-${i}`,
      sourceNodeId: e.sourceNodeId ?? '',
      sourcePortKey: e.sourcePortKey ?? 'output',
      targetNodeId: e.targetNodeId ?? '',
      targetPortKey: e.targetPortKey ?? 'input',
    })) as readonly WorkflowEdge[],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
  };
}

// ============================================================
// Tests
// ============================================================

describe('Preview Engine - AiModel-1n1.2', () => {
  beforeEach(() => {
    clearPreviewCache();
  });

  // ----------------------------------------------------------
  // topologicalSort
  // ----------------------------------------------------------
  describe('topologicalSort', () => {
    it('returns correct order for a simple chain A→B→C', () => {
      const nodes: WorkflowNode[] = [
        { id: 'A', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'B', type: 'scriptWriter', label: 'B', position: { x: 100, y: 0 }, config: {} },
        { id: 'C', type: 'sceneSplitter', label: 'C', position: { x: 200, y: 0 }, config: {} },
      ];
      const edges: WorkflowEdge[] = [
        { id: 'e1', sourceNodeId: 'A', sourcePortKey: 'prompt', targetNodeId: 'B', targetPortKey: 'prompt' },
        { id: 'e2', sourceNodeId: 'B', sourcePortKey: 'script', targetNodeId: 'C', targetPortKey: 'script' },
      ];

      const sorted = topologicalSort(nodes, edges);

      expect(sorted).toEqual(['A', 'B', 'C']);
    });

    it('handles disconnected nodes (includes all)', () => {
      const nodes: WorkflowNode[] = [
        { id: 'X', type: 'userPrompt', label: 'X', position: { x: 0, y: 0 }, config: {} },
        { id: 'Y', type: 'scriptWriter', label: 'Y', position: { x: 100, y: 0 }, config: {} },
        { id: 'Z', type: 'sceneSplitter', label: 'Z', position: { x: 200, y: 0 }, config: {} },
      ];
      const edges: WorkflowEdge[] = [
        { id: 'e1', sourceNodeId: 'X', sourcePortKey: 'prompt', targetNodeId: 'Y', targetPortKey: 'prompt' },
      ];

      const sorted = topologicalSort(nodes, edges);

      // All three should appear; Z is disconnected and has 0 in-degree
      expect(sorted).toHaveLength(3);
      expect(sorted).toContain('X');
      expect(sorted).toContain('Y');
      expect(sorted).toContain('Z');
      // X must come before Y
      expect(sorted.indexOf('X')).toBeLessThan(sorted.indexOf('Y'));
    });

    it('skips nodes involved in cycles', () => {
      const nodes: WorkflowNode[] = [
        { id: 'A', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'B', type: 'scriptWriter', label: 'B', position: { x: 100, y: 0 }, config: {} },
        { id: 'C', type: 'sceneSplitter', label: 'C', position: { x: 200, y: 0 }, config: {} },
      ];
      const edges: WorkflowEdge[] = [
        { id: 'e1', sourceNodeId: 'A', sourcePortKey: 'out', targetNodeId: 'B', targetPortKey: 'in' },
        { id: 'e2', sourceNodeId: 'B', sourcePortKey: 'out', targetNodeId: 'C', targetPortKey: 'in' },
        { id: 'e3', sourceNodeId: 'C', sourcePortKey: 'out', targetNodeId: 'A', targetPortKey: 'in' },
      ];

      const sorted = topologicalSort(nodes, edges);

      // All three form a cycle; none should appear
      expect(sorted).toHaveLength(0);
    });

    it('handles empty graph', () => {
      const sorted = topologicalSort([], []);
      expect(sorted).toEqual([]);
    });

    it('handles single node with no edges', () => {
      const nodes: WorkflowNode[] = [
        { id: 'solo', type: 'userPrompt', label: 'Solo', position: { x: 0, y: 0 }, config: {} },
      ];

      const sorted = topologicalSort(nodes, []);
      expect(sorted).toEqual(['solo']);
    });

    it('handles diamond DAG A→B, A→C, B→D, C→D', () => {
      const nodes: WorkflowNode[] = [
        { id: 'A', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'B', type: 'scriptWriter', label: 'B', position: { x: 100, y: 0 }, config: {} },
        { id: 'C', type: 'sceneSplitter', label: 'C', position: { x: 100, y: 100 }, config: {} },
        { id: 'D', type: 'promptRefiner', label: 'D', position: { x: 200, y: 50 }, config: {} },
      ];
      const edges: WorkflowEdge[] = [
        { id: 'e1', sourceNodeId: 'A', sourcePortKey: 'out', targetNodeId: 'B', targetPortKey: 'in' },
        { id: 'e2', sourceNodeId: 'A', sourcePortKey: 'out', targetNodeId: 'C', targetPortKey: 'in' },
        { id: 'e3', sourceNodeId: 'B', sourcePortKey: 'out', targetNodeId: 'D', targetPortKey: 'in' },
        { id: 'e4', sourceNodeId: 'C', sourcePortKey: 'out', targetNodeId: 'D', targetPortKey: 'in2' },
      ];

      const sorted = topologicalSort(nodes, edges);
      expect(sorted).toHaveLength(4);
      expect(sorted.indexOf('A')).toBeLessThan(sorted.indexOf('B'));
      expect(sorted.indexOf('A')).toBeLessThan(sorted.indexOf('C'));
      expect(sorted.indexOf('B')).toBeLessThan(sorted.indexOf('D'));
      expect(sorted.indexOf('C')).toBeLessThan(sorted.indexOf('D'));
    });

    it('ignores self-loop edges', () => {
      const nodes: WorkflowNode[] = [
        { id: 'A', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
      ];
      const edges: WorkflowEdge[] = [
        { id: 'e1', sourceNodeId: 'A', sourcePortKey: 'out', targetNodeId: 'A', targetPortKey: 'in' },
      ];

      const sorted = topologicalSort(nodes, edges);
      expect(sorted).toEqual(['A']);
    });
  });

  // ----------------------------------------------------------
  // computeNodePreview
  // ----------------------------------------------------------
  describe('computeNodePreview', () => {
    it('produces preview for a single node with no inputs (userPrompt)', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'userPrompt',
        label: 'User Prompt',
        position: { x: 0, y: 0 },
        config: {
          topic: 'Test Topic',
          goal: 'Test Goal',
          audience: 'Test Audience',
          tone: 'educational',
          durationSeconds: 60,
        },
      };

      const result = computeNodePreview(node, {});

      expect(result).toBeDefined();
      expect(result.prompt).toBeDefined();
      expect(result.prompt.status).toBe('ready');
      expect(result.prompt.schemaType).toBe('prompt');
      expect(result.prompt.value).not.toBeNull();
    });

    it('returns empty record for unknown node type', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'nonExistentType',
        label: 'Unknown',
        position: { x: 0, y: 0 },
        config: {},
      };

      const result = computeNodePreview(node, {});

      expect(result).toEqual({});
    });

    it('produces idle output when required upstream input is missing (scriptWriter)', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'scriptWriter',
        label: 'Script Writer',
        position: { x: 0, y: 0 },
        config: {
          style: 'Conversational',
          structure: 'three_act',
          includeHook: true,
          includeCTA: true,
          targetDurationSeconds: 90,
        },
      };

      // No upstream inputs — buildPreview should return idle
      const result = computeNodePreview(node, {});

      expect(result.script).toBeDefined();
      expect(result.script.status).toBe('idle');
      expect(result.script.value).toBeNull();
    });

    it('produces ready output when upstream input is provided (scriptWriter)', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'scriptWriter',
        label: 'Script Writer',
        position: { x: 0, y: 0 },
        config: {
          style: 'Conversational',
          structure: 'three_act',
          includeHook: true,
          includeCTA: true,
          targetDurationSeconds: 90,
        },
      };

      const upstreamPayloads: Record<string, PortPayload> = {
        prompt: {
          value: {
            topic: 'Neural networks',
            goal: 'Explain basics',
            audience: 'Students',
            tone: 'educational',
            durationSeconds: 90,
          },
          status: 'ready',
          schemaType: 'prompt',
        },
      };

      const result = computeNodePreview(node, upstreamPayloads);

      expect(result.script).toBeDefined();
      expect(result.script.status).toBe('ready');
      expect(result.script.value).not.toBeNull();
      expect(result.script.schemaType).toBe('script');
    });
  });

  // ----------------------------------------------------------
  // computeAllPreviews
  // ----------------------------------------------------------
  describe('computeAllPreviews', () => {
    it('computes previews for a single-node workflow', () => {
      const doc = createTestWorkflow(
        [
          {
            id: 'n1',
            type: 'userPrompt',
            config: {
              topic: 'Test',
              goal: 'Goal',
              audience: 'Everyone',
              tone: 'educational',
              durationSeconds: 60,
            },
          },
        ],
        [],
      );

      const previews = computeAllPreviews(doc);

      expect(previews.size).toBe(1);
      expect(previews.get('n1')).toBeDefined();
      expect(previews.get('n1')!.prompt).toBeDefined();
      expect(previews.get('n1')!.prompt.status).toBe('ready');
    });

    it('computes downstream previews from upstream in a chain (A→B)', () => {
      const doc = createTestWorkflow(
        [
          {
            id: 'prompt-node',
            type: 'userPrompt',
            config: {
              topic: 'Photosynthesis',
              goal: 'Explain the process',
              audience: 'Students',
              tone: 'educational',
              durationSeconds: 90,
            },
          },
          {
            id: 'script-node',
            type: 'scriptWriter',
            config: {
              style: 'Clear and concise',
              structure: 'three_act',
              includeHook: true,
              includeCTA: true,
              targetDurationSeconds: 90,
            },
          },
        ],
        [
          {
            id: 'e1',
            sourceNodeId: 'prompt-node',
            sourcePortKey: 'prompt',
            targetNodeId: 'script-node',
            targetPortKey: 'prompt',
          },
        ],
      );

      const previews = computeAllPreviews(doc);

      expect(previews.size).toBe(2);

      // Upstream node should have prompt output
      const promptPreview = previews.get('prompt-node');
      expect(promptPreview).toBeDefined();
      expect(promptPreview!.prompt.status).toBe('ready');

      // Downstream node should receive the prompt and produce script output
      const scriptPreview = previews.get('script-node');
      expect(scriptPreview).toBeDefined();
      expect(scriptPreview!.script).toBeDefined();
      expect(scriptPreview!.script.status).toBe('ready');
      expect(scriptPreview!.script.value).not.toBeNull();
    });

    it('produces idle downstream when upstream provides no usable data', () => {
      // scriptWriter without a connected upstream prompt
      const doc = createTestWorkflow(
        [
          {
            id: 'script-node',
            type: 'scriptWriter',
            config: {
              style: 'Clear',
              structure: 'three_act',
              includeHook: true,
              includeCTA: true,
              targetDurationSeconds: 90,
            },
          },
        ],
        [],
      );

      const previews = computeAllPreviews(doc);

      const scriptPreview = previews.get('script-node');
      expect(scriptPreview).toBeDefined();
      expect(scriptPreview!.script.status).toBe('idle');
      expect(scriptPreview!.script.value).toBeNull();
    });

    it('handles disconnected nodes in the workflow', () => {
      const doc = createTestWorkflow(
        [
          {
            id: 'n1',
            type: 'userPrompt',
            config: {
              topic: 'A',
              goal: 'B',
              audience: 'C',
              tone: 'playful',
              durationSeconds: 30,
            },
          },
          {
            id: 'n2',
            type: 'userPrompt',
            config: {
              topic: 'D',
              goal: 'E',
              audience: 'F',
              tone: 'dramatic',
              durationSeconds: 60,
            },
          },
        ],
        [],
      );

      const previews = computeAllPreviews(doc);

      expect(previews.size).toBe(2);
      expect(previews.get('n1')).toBeDefined();
      expect(previews.get('n2')).toBeDefined();
    });

    it('skips nodes with unknown types gracefully', () => {
      const doc = createTestWorkflow(
        [
          {
            id: 'n1',
            type: 'nonExistentType',
            config: {},
          },
        ],
        [],
      );

      const previews = computeAllPreviews(doc);

      expect(previews.size).toBe(1);
      expect(previews.get('n1')).toEqual({});
    });
  });

  // ----------------------------------------------------------
  // Determinism
  // ----------------------------------------------------------
  describe('determinism', () => {
    it('produces identical output for identical inputs', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'userPrompt',
        label: 'Prompt',
        position: { x: 0, y: 0 },
        config: {
          topic: 'Determinism test',
          goal: 'Verify stable output',
          audience: 'Engineers',
          tone: 'educational',
          durationSeconds: 45,
        },
      };

      clearPreviewCache();
      const result1 = computeNodePreview(node, {});
      clearPreviewCache();
      const result2 = computeNodePreview(node, {});

      // Compare by value (ignoring generatedAt which has timestamp)
      expect(result1.prompt.status).toBe(result2.prompt.status);
      expect(result1.prompt.schemaType).toBe(result2.prompt.schemaType);
      expect(result1.prompt.previewText).toBe(result2.prompt.previewText);
    });

    it('computeAllPreviews is deterministic across calls', () => {
      const doc = createTestWorkflow(
        [
          {
            id: 'p',
            type: 'userPrompt',
            config: {
              topic: 'Stable',
              goal: 'Test',
              audience: 'All',
              tone: 'cinematic',
              durationSeconds: 120,
            },
          },
          {
            id: 's',
            type: 'scriptWriter',
            config: {
              style: 'Narrator voice',
              structure: 'story_arc',
              includeHook: true,
              includeCTA: false,
              targetDurationSeconds: 120,
            },
          },
        ],
        [
          {
            id: 'e1',
            sourceNodeId: 'p',
            sourcePortKey: 'prompt',
            targetNodeId: 's',
            targetPortKey: 'prompt',
          },
        ],
      );

      clearPreviewCache();
      const previews1 = computeAllPreviews(doc);
      clearPreviewCache();
      const previews2 = computeAllPreviews(doc);

      const script1 = previews1.get('s')!.script;
      const script2 = previews2.get('s')!.script;

      expect(script1.status).toBe(script2.status);
      expect(script1.schemaType).toBe(script2.schemaType);
      expect(script1.previewText).toBe(script2.previewText);
      // Value objects should be structurally equal
      expect(JSON.stringify(script1.value)).toBe(JSON.stringify(script2.value));
    });
  });

  // ----------------------------------------------------------
  // Memoization
  // ----------------------------------------------------------
  describe('memoization', () => {
    it('returns cached result for same inputs', () => {
      const node: WorkflowNode = {
        id: 'n1',
        type: 'userPrompt',
        label: 'Prompt',
        position: { x: 0, y: 0 },
        config: {
          topic: 'Cache test',
          goal: 'Verify caching',
          audience: 'Testers',
          tone: 'educational',
          durationSeconds: 30,
        },
      };

      const result1 = computeNodePreview(node, {});
      const result2 = computeNodePreview(node, {});

      // Same reference due to cache hit
      expect(result1).toBe(result2);
    });

    it('returns different result for different configs', () => {
      const node1: WorkflowNode = {
        id: 'n1',
        type: 'userPrompt',
        label: 'Prompt',
        position: { x: 0, y: 0 },
        config: {
          topic: 'Topic A',
          goal: 'Goal A',
          audience: 'Audience A',
          tone: 'educational',
          durationSeconds: 30,
        },
      };

      const node2: WorkflowNode = {
        ...node1,
        config: {
          topic: 'Topic B',
          goal: 'Goal B',
          audience: 'Audience B',
          tone: 'dramatic',
          durationSeconds: 60,
        },
      };

      const result1 = computeNodePreview(node1, {});
      const result2 = computeNodePreview(node2, {});

      expect(result1).not.toBe(result2);
      expect(result1.prompt.previewText).not.toBe(result2.prompt.previewText);
    });
  });

  // ============================================================
  // getDownstreamNodeIds
  // ============================================================

  describe('getDownstreamNodeIds', () => {
    it('returns direct and transitive downstream nodes', () => {
      const doc = createTestWorkflow(
        [
          { id: 'a', type: 'userPrompt' },
          { id: 'b', type: 'scriptWriter' },
          { id: 'c', type: 'sceneSplitter' },
        ],
        [
          { sourceNodeId: 'a', targetNodeId: 'b', sourcePortKey: 'prompt', targetPortKey: 'prompt' },
          { sourceNodeId: 'b', targetNodeId: 'c', sourcePortKey: 'script', targetPortKey: 'script' },
        ],
      );

      const result = getDownstreamNodeIds(doc, 'a');
      expect(result).toContain('b');
      expect(result).toContain('c');
      expect(result).not.toContain('a');
    });

    it('returns empty array for leaf nodes', () => {
      const doc = createTestWorkflow(
        [{ id: 'a', type: 'userPrompt' }],
        [],
      );
      expect(getDownstreamNodeIds(doc, 'a')).toEqual([]);
    });
  });

  // ============================================================
  // computeIncrementalPreviews
  // ============================================================

  describe('computeIncrementalPreviews', () => {
    it('recomputes changed node and downstream, preserves upstream', () => {
      const doc = createTestWorkflow(
        [
          { id: 'a', type: 'userPrompt', config: { topic: 'AI', goal: 'Teach', audience: 'Devs', tone: 'educational', durationSeconds: 60 } },
          { id: 'b', type: 'scriptWriter', config: { tone: 'neutral', lengthHint: 'medium', includeHooks: true } },
        ],
        [
          { sourceNodeId: 'a', targetNodeId: 'b', sourcePortKey: 'prompt', targetPortKey: 'prompt' },
        ],
      );

      // Compute initial previews
      const initial = computeAllPreviews(doc);
      expect(initial.has('a')).toBe(true);
      expect(initial.has('b')).toBe(true);

      // Change node 'a' and recompute incrementally
      const incremental = computeIncrementalPreviews(doc, 'a', initial);
      expect(incremental.has('a')).toBe(true);
      expect(incremental.has('b')).toBe(true);
    });
  });
});
