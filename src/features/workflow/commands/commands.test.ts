import { describe, it, expect } from 'vitest';
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';
import { createEmptyWorkflowDocument } from '@/features/workflow/store/workflow-store';
import { addNode } from './add-node';
import { connectPorts } from './connect-ports';
import { disconnectEdge } from './disconnect-edge';
import { deleteSelection } from './delete-selection';
import { duplicateNodes } from './duplicate-node';
import { updateNodeConfig } from './update-node-config';
import { insertNodeOnEdge } from './insert-node-on-edge';

function makeDoc(
  overrides?: Partial<WorkflowDocument>,
): WorkflowDocument {
  return { ...createEmptyWorkflowDocument('test-doc'), ...overrides };
}

// ============================================================
// addNode
// ============================================================

describe('addNode', () => {
  it('adds a node from a valid template type', () => {
    const doc = makeDoc();
    const result = addNode('userPrompt', { x: 100, y: 200 })(doc);
    expect(result.nodes).toHaveLength(1);
    expect(result.nodes[0].type).toBe('userPrompt');
    expect(result.nodes[0].position).toEqual({ x: 100, y: 200 });
    expect(result.nodes[0].label).toBe('User Prompt');
  });

  it('returns unchanged doc for unknown template', () => {
    const doc = makeDoc();
    const result = addNode('nonexistent', { x: 0, y: 0 })(doc);
    expect(result).toBe(doc);
  });

  it('generates a unique id', () => {
    const doc = makeDoc();
    const r1 = addNode('userPrompt', { x: 0, y: 0 })(doc);
    const r2 = addNode('userPrompt', { x: 50, y: 50 })(r1);
    expect(r2.nodes).toHaveLength(2);
    expect(r2.nodes[0].id).not.toBe(r2.nodes[1].id);
  });
});

// ============================================================
// connectPorts
// ============================================================

describe('connectPorts', () => {
  it('connects compatible ports', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: { prompt: '' } },
        { id: 'n2', type: 'scriptWriter', label: 'B', position: { x: 200, y: 0 }, config: { tone: 'neutral', lengthHint: 'medium', includeHooks: true } },
      ],
    });
    const result = connectPorts(
      { sourceNodeId: 'n1', sourcePortKey: 'prompt', targetNodeId: 'n2', targetPortKey: 'prompt' },
      doc,
    );
    expect(result.success).toBe(true);
    expect(result.recipe).toBeDefined();
    const updated = result.recipe!(doc);
    expect(updated.edges).toHaveLength(1);
    expect(updated.edges[0].sourceNodeId).toBe('n1');
    expect(updated.edges[0].targetNodeId).toBe('n2');
  });

  it('rejects self-connections', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'scriptWriter', label: 'A', position: { x: 0, y: 0 }, config: { tone: 'neutral', lengthHint: 'medium', includeHooks: true } },
      ],
    });
    const result = connectPorts(
      { sourceNodeId: 'n1', sourcePortKey: 'script', targetNodeId: 'n1', targetPortKey: 'prompt' },
      doc,
    );
    expect(result.success).toBe(false);
    expect(result.reason).toContain('itself');
  });

  it('rejects incompatible types', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: { prompt: '' } },
        { id: 'n2', type: 'videoComposer', label: 'B', position: { x: 200, y: 0 }, config: { resolution: '1080p', fps: 30, format: 'mp4', transitionStyle: 'crossfade', transitionDurationMs: 500, audioMixMode: 'layered' } },
      ],
    });
    // userPrompt outputs 'prompt' (DataType=prompt), videoComposer expects imageAssetList
    const result = connectPorts(
      { sourceNodeId: 'n1', sourcePortKey: 'prompt', targetNodeId: 'n2', targetPortKey: 'imageAssets' },
      doc,
    );
    expect(result.success).toBe(false);
  });
});

// ============================================================
// disconnectEdge
// ============================================================

describe('disconnectEdge', () => {
  it('removes the specified edge', () => {
    const doc = makeDoc({
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'out', targetNodeId: 'n2', targetPortKey: 'in' },
        { id: 'e2', sourceNodeId: 'n2', sourcePortKey: 'out', targetNodeId: 'n3', targetPortKey: 'in' },
      ],
    });
    const result = disconnectEdge('e1')(doc);
    expect(result.edges).toHaveLength(1);
    expect(result.edges[0].id).toBe('e2');
  });
});

// ============================================================
// deleteSelection
// ============================================================

describe('deleteSelection', () => {
  it('removes selected nodes and connected edges', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'scriptWriter', label: 'B', position: { x: 100, y: 0 }, config: {} },
        { id: 'n3', type: 'sceneSplitter', label: 'C', position: { x: 200, y: 0 }, config: {} },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'out', targetNodeId: 'n2', targetPortKey: 'in' },
        { id: 'e2', sourceNodeId: 'n2', sourcePortKey: 'out', targetNodeId: 'n3', targetPortKey: 'in' },
      ],
    });
    const result = deleteSelection(['n2'], null)(doc);
    expect(result.nodes).toHaveLength(2);
    expect(result.edges).toHaveLength(0); // both edges touch n2
  });

  it('removes a selected edge', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'out', targetNodeId: 'n2', targetPortKey: 'in' },
      ],
    });
    const result = deleteSelection([], 'e1')(doc);
    expect(result.edges).toHaveLength(0);
    expect(result.nodes).toHaveLength(1);
  });
});

// ============================================================
// duplicateNodes
// ============================================================

describe('duplicateNodes', () => {
  it('creates copies with new ids at offset positions', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: { prompt: '' } },
      ],
    });
    const { recipe, getNewIds } = duplicateNodes(['n1']);
    const result = recipe(doc);
    expect(result.nodes).toHaveLength(2);
    const newIds = getNewIds(doc);
    expect(newIds).toHaveLength(1);
    const copy = result.nodes.find((n) => n.id === newIds[0]);
    expect(copy).toBeDefined();
    expect(copy!.position.x).toBe(30);
    expect(copy!.position.y).toBe(30);
    expect(copy!.type).toBe('userPrompt');
  });
});

// ============================================================
// updateNodeConfig
// ============================================================

describe('updateNodeConfig', () => {
  const validConfig = {
    topic: 'AI overview',
    goal: 'Educate viewers',
    audience: 'Developers',
    tone: 'educational' as const,
    durationSeconds: 60,
  };

  it('validates and applies valid config', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: validConfig },
      ],
    });
    const updated = { ...validConfig, topic: 'Updated topic' };
    const result = updateNodeConfig('n1', updated, doc);
    expect(result.success).toBe(true);
    const applied = result.recipe!(doc);
    expect((applied.nodes[0].config as typeof validConfig).topic).toBe('Updated topic');
  });

  it('rejects invalid config', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: validConfig },
      ],
    });
    // Missing required fields
    const result = updateNodeConfig('n1', { topic: '' }, doc);
    expect(result.success).toBe(false);
    expect(result.errors).toBeDefined();
    expect(result.errors!.length).toBeGreaterThan(0);
  });

  it('returns error for missing node', () => {
    const doc = makeDoc();
    const result = updateNodeConfig('nonexistent', {}, doc);
    expect(result.success).toBe(false);
  });
});

// ============================================================
// insertNodeOnEdge
// ============================================================

describe('insertNodeOnEdge', () => {
  it('inserts a compatible node onto an edge', () => {
    // scriptWriter outputs script, ttsVoiceoverPlanner inputs script
    // sceneSplitter inputs script (exact match) and outputs sceneList (coercion to script via matrix)
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'scriptWriter', label: 'SW', position: { x: 0, y: 0 }, config: { tone: 'neutral', lengthHint: 'medium', includeHooks: true } },
        { id: 'n2', type: 'ttsVoiceoverPlanner', label: 'TTS', position: { x: 200, y: 0 }, config: { voice: 'alloy', speedMultiplier: 1.0, chunkStrategy: 'sentence' } },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'script', targetNodeId: 'n2', targetPortKey: 'script' },
      ],
    });

    // sceneSplitter: input script (exact), output sceneList (sceneList->script is coercion-compatible)
    const result = insertNodeOnEdge(
      { edgeId: 'e1', newNodeType: 'sceneSplitter' },
      doc,
    );

    expect(result.status).toBe('inserted');
    if (result.status === 'inserted') {
      expect(result.recipe).toBeDefined();
      const updated = result.recipe!(doc);
      // Original edge removed, two new edges added
      expect(updated.edges.find((e) => e.id === 'e1')).toBeUndefined();
      expect(updated.edges).toHaveLength(2);
      // New node at midpoint
      const newNode = updated.nodes.find((n) => n.id === result.newNodeId);
      expect(newNode).toBeDefined();
      expect(newNode!.position.x).toBe(100);
      expect(newNode!.position.y).toBe(0);
    }
  });

  it('returns incompatible for non-matching types', () => {
    const doc = makeDoc({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'A', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'scriptWriter', label: 'B', position: { x: 200, y: 0 }, config: { tone: 'neutral', lengthHint: 'medium', includeHooks: true } },
      ],
      edges: [
        { id: 'e1', sourceNodeId: 'n1', sourcePortKey: 'prompt', targetNodeId: 'n2', targetPortKey: 'prompt' },
      ],
    });

    // videoComposer has no prompt-compatible input or output
    const result = insertNodeOnEdge(
      { edgeId: 'e1', newNodeType: 'videoComposer' },
      doc,
    );
    expect(result.status).toBe('incompatible');
  });

  it('returns incompatible for missing edge', () => {
    const doc = makeDoc();
    const result = insertNodeOnEdge(
      { edgeId: 'nonexistent', newNodeType: 'promptRefiner' },
      doc,
    );
    expect(result.status).toBe('incompatible');
  });
});
