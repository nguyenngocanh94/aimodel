import { describe, it, expect } from 'vitest';
import {
  indexIncomingEdges,
  indexOutgoingEdges,
  collectUpstreamNodeIds,
  collectDownstreamNodeIds,
  topologicallySortSubgraph,
  planExecution,
} from './run-planner';
import type {
  WorkflowDocument,
  WorkflowEdge,
  WorkflowNode,
} from '@/features/workflows/domain/workflow-types';

// ============================================================
// Test helpers
// ============================================================

function makeNode(id: string, type = 'scriptWriter', disabled?: boolean): WorkflowNode {
  return {
    id,
    type,
    label: id,
    position: { x: 0, y: 0 },
    config: {},
    disabled,
  };
}

function makeEdge(
  sourceNodeId: string,
  targetNodeId: string,
  sourcePortKey = 'output',
  targetPortKey = 'input',
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
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test',
    description: '',
    tags: [],
    nodes,
    edges,
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}

// ============================================================
// Edge indexing
// ============================================================

describe('indexIncomingEdges', () => {
  it('should group edges by target node', () => {
    const edges = [
      makeEdge('a', 'b'),
      makeEdge('a', 'c'),
      makeEdge('b', 'c'),
    ];
    const incoming = indexIncomingEdges(edges);
    expect(incoming.get('b')).toHaveLength(1);
    expect(incoming.get('c')).toHaveLength(2);
    expect(incoming.get('a')).toBeUndefined();
  });
});

describe('indexOutgoingEdges', () => {
  it('should group edges by source node', () => {
    const edges = [
      makeEdge('a', 'b'),
      makeEdge('a', 'c'),
      makeEdge('b', 'c'),
    ];
    const outgoing = indexOutgoingEdges(edges);
    expect(outgoing.get('a')).toHaveLength(2);
    expect(outgoing.get('b')).toHaveLength(1);
    expect(outgoing.get('c')).toBeUndefined();
  });
});

// ============================================================
// Graph traversal
// ============================================================

describe('collectUpstreamNodeIds', () => {
  it('should collect all upstream nodes', () => {
    const edges = [makeEdge('a', 'b'), makeEdge('b', 'c'), makeEdge('d', 'c')];
    const incoming = indexIncomingEdges(edges);
    const upstream = collectUpstreamNodeIds('c', incoming);
    expect(upstream).toEqual(new Set(['a', 'b', 'd']));
  });

  it('should return empty set for source node', () => {
    const edges = [makeEdge('a', 'b')];
    const incoming = indexIncomingEdges(edges);
    const upstream = collectUpstreamNodeIds('a', incoming);
    expect(upstream.size).toBe(0);
  });
});

describe('collectDownstreamNodeIds', () => {
  it('should collect all downstream nodes', () => {
    const edges = [makeEdge('a', 'b'), makeEdge('b', 'c'), makeEdge('b', 'd')];
    const outgoing = indexOutgoingEdges(edges);
    const downstream = collectDownstreamNodeIds('a', outgoing);
    expect(downstream).toEqual(new Set(['b', 'c', 'd']));
  });

  it('should return empty set for leaf node', () => {
    const edges = [makeEdge('a', 'b')];
    const outgoing = indexOutgoingEdges(edges);
    const downstream = collectDownstreamNodeIds('b', outgoing);
    expect(downstream.size).toBe(0);
  });
});

// ============================================================
// Topological sort
// ============================================================

describe('topologicallySortSubgraph', () => {
  it('should produce valid topological order', () => {
    const edges = [makeEdge('a', 'b'), makeEdge('b', 'c'), makeEdge('a', 'c')];
    const result = topologicallySortSubgraph({
      nodeIds: new Set(['a', 'b', 'c']),
      edges,
    });
    expect(result).toHaveLength(3);
    expect(result.indexOf('a')).toBeLessThan(result.indexOf('b'));
    expect(result.indexOf('b')).toBeLessThan(result.indexOf('c'));
  });

  it('should throw on cyclic subgraph', () => {
    const edges = [makeEdge('a', 'b'), makeEdge('b', 'a')];
    expect(() =>
      topologicallySortSubgraph({
        nodeIds: new Set(['a', 'b']),
        edges,
      }),
    ).toThrow('Cannot plan execution for cyclic subgraph');
  });

  it('should handle independent nodes', () => {
    const result = topologicallySortSubgraph({
      nodeIds: new Set(['a', 'b', 'c']),
      edges: [],
    });
    expect(result).toHaveLength(3);
    expect(new Set(result)).toEqual(new Set(['a', 'b', 'c']));
  });

  it('should ignore edges outside the subgraph', () => {
    const edges = [makeEdge('a', 'b'), makeEdge('b', 'c'), makeEdge('c', 'd')];
    const result = topologicallySortSubgraph({
      nodeIds: new Set(['a', 'b']),
      edges,
    });
    expect(result).toEqual(['a', 'b']);
  });
});

// ============================================================
// planExecution
// ============================================================

describe('planExecution', () => {
  // A → B → C pipeline (all executable scriptWriter)
  const pipeline = makeWorkflow(
    [makeNode('a'), makeNode('b'), makeNode('c')],
    [makeEdge('a', 'b', 'script', 'script'), makeEdge('b', 'c', 'script', 'script')],
  );

  describe('runWorkflow', () => {
    it('should include all non-disabled nodes', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runWorkflow',
      });
      expect(plan.scopeNodeIds).toHaveLength(3);
      expect(plan.orderedNodeIds.indexOf('a')).toBeLessThan(plan.orderedNodeIds.indexOf('b'));
      expect(plan.orderedNodeIds.indexOf('b')).toBeLessThan(plan.orderedNodeIds.indexOf('c'));
      expect(plan.trigger).toBe('runWorkflow');
    });

    it('should exclude disabled nodes', () => {
      const wf = makeWorkflow(
        [makeNode('a'), makeNode('b', 'scriptWriter', true), makeNode('c')],
        [makeEdge('a', 'b'), makeEdge('b', 'c')],
      );
      const plan = planExecution({ workflow: wf, trigger: 'runWorkflow' });
      expect(plan.scopeNodeIds).not.toContain('b');
      expect(plan.skippedNodeIds).toContain('b');
    });
  });

  describe('runNode', () => {
    it('should include target and upstream', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runNode',
        targetNodeId: 'c',
      });
      expect(plan.scopeNodeIds).toContain('c');
      expect(plan.scopeNodeIds).toContain('a');
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.targetNodeId).toBe('c');
    });

    it('should throw without targetNodeId', () => {
      expect(() =>
        planExecution({ workflow: pipeline, trigger: 'runNode' }),
      ).toThrow('runNode requires a targetNodeId');
    });
  });

  describe('runFromHere', () => {
    it('should include target and all downstream', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runFromHere',
        targetNodeId: 'a',
      });
      expect(plan.scopeNodeIds).toContain('a');
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.scopeNodeIds).toContain('c');
    });

    it('should only include target and downstream, not upstream', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runFromHere',
        targetNodeId: 'b',
      });
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.scopeNodeIds).toContain('c');
      expect(plan.scopeNodeIds).not.toContain('a');
    });

    it('should throw without targetNodeId', () => {
      expect(() =>
        planExecution({ workflow: pipeline, trigger: 'runFromHere' }),
      ).toThrow('runFromHere requires a targetNodeId');
    });
  });

  describe('runUpToHere', () => {
    it('should include target and all upstream', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runUpToHere',
        targetNodeId: 'c',
      });
      expect(plan.scopeNodeIds).toContain('a');
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.scopeNodeIds).toContain('c');
    });

    it('should not include downstream', () => {
      const plan = planExecution({
        workflow: pipeline,
        trigger: 'runUpToHere',
        targetNodeId: 'b',
      });
      expect(plan.scopeNodeIds).toContain('a');
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.scopeNodeIds).not.toContain('c');
    });

    it('should throw without targetNodeId', () => {
      expect(() =>
        planExecution({ workflow: pipeline, trigger: 'runUpToHere' }),
      ).toThrow('runUpToHere requires a targetNodeId');
    });
  });

  describe('diamond graph', () => {
    //   a
    //  / \
    // b   c
    //  \ /
    //   d
    const diamond = makeWorkflow(
      [makeNode('a'), makeNode('b'), makeNode('c'), makeNode('d')],
      [
        makeEdge('a', 'b'),
        makeEdge('a', 'c'),
        makeEdge('b', 'd'),
        makeEdge('c', 'd'),
      ],
    );

    it('runWorkflow should order correctly', () => {
      const plan = planExecution({ workflow: diamond, trigger: 'runWorkflow' });
      expect(plan.orderedNodeIds.indexOf('a')).toBeLessThan(plan.orderedNodeIds.indexOf('b'));
      expect(plan.orderedNodeIds.indexOf('a')).toBeLessThan(plan.orderedNodeIds.indexOf('c'));
      expect(plan.orderedNodeIds.indexOf('b')).toBeLessThan(plan.orderedNodeIds.indexOf('d'));
      expect(plan.orderedNodeIds.indexOf('c')).toBeLessThan(plan.orderedNodeIds.indexOf('d'));
    });

    it('runFromHere from b should include b and d', () => {
      const plan = planExecution({
        workflow: diamond,
        trigger: 'runFromHere',
        targetNodeId: 'b',
      });
      expect(plan.scopeNodeIds).toContain('b');
      expect(plan.scopeNodeIds).toContain('d');
      expect(plan.scopeNodeIds).not.toContain('a');
      expect(plan.scopeNodeIds).not.toContain('c');
    });

    it('runUpToHere to d should include all upstream and d', () => {
      const plan = planExecution({
        workflow: diamond,
        trigger: 'runUpToHere',
        targetNodeId: 'd',
      });
      expect(new Set(plan.scopeNodeIds)).toEqual(new Set(['a', 'b', 'c', 'd']));
    });
  });
});
