import { describe, it, expect } from 'vitest';
import {
  validateWorkflow,
  hasErrors,
  getValidationSummary,
} from './graph-validator';
import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  ValidationIssue,
} from './workflow-types';

// ============================================================
// Test helpers
// ============================================================

function makeDoc(
  overrides: {
    name?: string;
    nodes?: ReadonlyArray<Partial<WorkflowNode>>;
    edges?: ReadonlyArray<Partial<WorkflowEdge>>;
  } = {},
): WorkflowDocument {
  const now = '2026-04-02T00:00:00.000Z';
  return {
    id: 'test-wf',
    schemaVersion: 1,
    name: overrides.name ?? 'Test Workflow',
    description: 'Test',
    tags: [],
    nodes: (overrides.nodes ?? []).map((n, i) => ({
      id: n.id ?? `node-${i}`,
      type: n.type ?? 'userPrompt',
      label: n.label ?? `Node ${i}`,
      position: n.position ?? { x: 0, y: 0 },
      config: n.config ?? {},
      ...(n.disabled !== undefined ? { disabled: n.disabled } : {}),
    })) as readonly WorkflowNode[],
    edges: (overrides.edges ?? []).map((e, i) => ({
      id: e.id ?? `edge-${i}`,
      sourceNodeId: e.sourceNodeId ?? '',
      sourcePortKey: e.sourcePortKey ?? 'prompt',
      targetNodeId: e.targetNodeId ?? '',
      targetPortKey: e.targetPortKey ?? 'prompt',
    })) as readonly WorkflowEdge[],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
  };
}

/** Valid userPrompt config that passes schema validation. */
const VALID_USER_PROMPT_CONFIG = {
  topic: 'Test Topic',
  goal: 'Test Goal',
  audience: 'Test Audience',
  tone: 'educational' as const,
  durationSeconds: 120,
};

/** Valid scriptWriter config that passes schema validation. */
const VALID_SCRIPT_WRITER_CONFIG = {
  style: 'Clear narration',
  structure: 'three_act' as const,
  includeHook: true,
  includeCTA: true,
  targetDurationSeconds: 90,
};

/** Valid sceneSplitter config that passes schema validation. */
const VALID_SCENE_SPLITTER_CONFIG = {
  sceneCountTarget: 5,
  maxSceneDurationSeconds: 45,
  includeShotIntent: true,
  includeVisualPromptHints: true,
};

function findByCode(
  issues: readonly ValidationIssue[],
  code: ValidationIssue['code'],
): ValidationIssue | undefined {
  return issues.find((i) => i.code === code);
}



// ============================================================
// Tests
// ============================================================

describe('Graph Validator - AiModel-1n1.1', () => {
  // ----------------------------------------------------------
  // Valid workflow
  // ----------------------------------------------------------
  describe('Valid workflow', () => {
    it('should produce zero errors for a well-formed workflow', () => {
      const doc = makeDoc({
        name: 'My Valid Workflow',
        nodes: [
          {
            id: 'up',
            type: 'userPrompt',
            label: 'Prompt',
            config: VALID_USER_PROMPT_CONFIG,
          },
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'up',
            sourcePortKey: 'prompt',
            targetNodeId: 'sw',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const errors = issues.filter((i) => i.severity === 'error');
      expect(errors).toHaveLength(0);
    });
  });

  // ----------------------------------------------------------
  // Workflow-Level Validation (16.1)
  // ----------------------------------------------------------
  describe('Workflow-level validation (16.1)', () => {
    it('should warn when workflow has no name (empty string)', () => {
      const doc = makeDoc({
        name: '',
        nodes: [{ id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG }],
      });
      const issues = validateWorkflow(doc);
      const issue = issues.find(
        (i) => i.code === 'configInvalid' && i.scope === 'workflow',
      );
      expect(issue).toBeDefined();
      expect(issue!.severity).toBe('warning');
    });

    it('should warn when workflow has whitespace-only name', () => {
      const doc = makeDoc({
        name: '   ',
        nodes: [{ id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG }],
      });
      const issues = validateWorkflow(doc);
      const issue = issues.find(
        (i) => i.code === 'configInvalid' && i.scope === 'workflow',
      );
      expect(issue).toBeDefined();
    });

    it('should error on duplicate node IDs', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'dup', type: 'userPrompt' },
          { id: 'dup', type: 'scriptWriter' },
        ],
      });
      const issues = validateWorkflow(doc);
      const error = issues.find((i) => i.message.includes('Duplicate node ID'));
      expect(error).toBeDefined();
      expect(error!.severity).toBe('error');
      expect(error!.scope).toBe('workflow');
    });

    it('should error on duplicate edge IDs', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG },
          { id: 'n2', type: 'scriptWriter', config: VALID_SCRIPT_WRITER_CONFIG },
        ],
        edges: [
          {
            id: 'dup-e',
            sourceNodeId: 'n1',
            sourcePortKey: 'prompt',
            targetNodeId: 'n2',
            targetPortKey: 'prompt',
          },
          {
            id: 'dup-e',
            sourceNodeId: 'n1',
            sourcePortKey: 'prompt',
            targetNodeId: 'n2',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const error = issues.find((i) => i.message.includes('Duplicate edge ID'));
      expect(error).toBeDefined();
      expect(error!.severity).toBe('error');
      expect(error!.scope).toBe('workflow');
    });

    it('should error when edge references non-existent source node', () => {
      const doc = makeDoc({
        nodes: [{ id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG }],
        edges: [
          {
            sourceNodeId: 'missing',
            sourcePortKey: 'prompt',
            targetNodeId: 'n1',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const error = issues.find((i) => i.message.includes('non-existent source'));
      expect(error).toBeDefined();
      expect(error!.severity).toBe('error');
      expect(error!.scope).toBe('edge');
    });

    it('should error when edge references non-existent target node', () => {
      const doc = makeDoc({
        nodes: [{ id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG }],
        edges: [
          {
            sourceNodeId: 'n1',
            sourcePortKey: 'prompt',
            targetNodeId: 'missing',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const error = issues.find((i) => i.message.includes('non-existent target'));
      expect(error).toBeDefined();
      expect(error!.severity).toBe('error');
      expect(error!.scope).toBe('edge');
    });

    it('should detect a cycle: A -> B -> C -> A', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'a', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG },
          { id: 'b', type: 'scriptWriter', config: VALID_SCRIPT_WRITER_CONFIG },
          { id: 'c', type: 'sceneSplitter', config: VALID_SCENE_SPLITTER_CONFIG },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'a',
            sourcePortKey: 'prompt',
            targetNodeId: 'b',
            targetPortKey: 'prompt',
          },
          {
            id: 'e2',
            sourceNodeId: 'b',
            sourcePortKey: 'script',
            targetNodeId: 'c',
            targetPortKey: 'script',
          },
          {
            id: 'e3',
            sourceNodeId: 'c',
            sourcePortKey: 'scenes',
            targetNodeId: 'a',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const cycleIssue = findByCode(issues, 'cycleDetected');
      expect(cycleIssue).toBeDefined();
      expect(cycleIssue!.severity).toBe('error');
      expect(cycleIssue!.scope).toBe('workflow');
      // Message should mention the involved nodes
      expect(cycleIssue!.message).toContain('a');
      expect(cycleIssue!.message).toContain('b');
      expect(cycleIssue!.message).toContain('c');
    });

    it('should warn about disabled node with downstream connections', () => {
      const doc = makeDoc({
        nodes: [
          {
            id: 'n1',
            type: 'userPrompt',
            label: 'Prompt Node',
            config: VALID_USER_PROMPT_CONFIG,
            disabled: true,
          },
          {
            id: 'n2',
            type: 'scriptWriter',
            label: 'Writer Node',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'n1',
            sourcePortKey: 'prompt',
            targetNodeId: 'n2',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const disabledIssue = findByCode(issues, 'disabledNode');
      expect(disabledIssue).toBeDefined();
      expect(disabledIssue!.severity).toBe('warning');
      expect(disabledIssue!.scope).toBe('node');
    });
  });

  // ----------------------------------------------------------
  // Node-Level Validation (16.2)
  // ----------------------------------------------------------
  describe('Node-level validation (16.2)', () => {
    it('should error for config validation failure (invalid config)', () => {
      // userPrompt requires topic, goal, audience, tone, durationSeconds
      const doc = makeDoc({
        nodes: [
          {
            id: 'n1',
            type: 'userPrompt',
            label: 'Bad Config',
            config: { topic: '' }, // empty topic fails min(1), missing fields
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const configErr = issues.find(
        (i) => i.code === 'configInvalid' && i.scope === 'config',
      );
      expect(configErr).toBeDefined();
      expect(configErr!.severity).toBe('error');
      expect(configErr!.nodeId).toBe('n1');
    });

    it('should error when required inputs are not connected', () => {
      // scriptWriter requires a 'prompt' input
      const doc = makeDoc({
        nodes: [
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [], // no edges => required input not connected
      });
      const issues = validateWorkflow(doc);
      const missing = findByCode(issues, 'missingRequiredInput');
      expect(missing).toBeDefined();
      expect(missing!.severity).toBe('error');
      expect(missing!.scope).toBe('port');
      expect(missing!.nodeId).toBe('sw');
      expect(missing!.portKey).toBe('prompt');
    });

    it('should warn about orphan nodes (no connections)', () => {
      const doc = makeDoc({
        nodes: [
          {
            id: 'orphan',
            type: 'scriptWriter',
            label: 'Lonely Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [],
      });
      const issues = validateWorkflow(doc);
      const orphan = findByCode(issues, 'orphanNode');
      expect(orphan).toBeDefined();
      expect(orphan!.severity).toBe('warning');
      expect(orphan!.scope).toBe('node');
      expect(orphan!.nodeId).toBe('orphan');
    });

    it('should error for unknown node types', () => {
      const doc = makeDoc({
        nodes: [{ id: 'x', type: 'totallyFakeNode', label: 'Mystery' }],
      });
      const issues = validateWorkflow(doc);
      const error = issues.find((i) => i.message.includes('unknown type'));
      expect(error).toBeDefined();
      expect(error!.severity).toBe('error');
    });
  });

  // ----------------------------------------------------------
  // Edge-Level Validation (16.3)
  // ----------------------------------------------------------
  describe('Edge-level validation (16.3)', () => {
    it('should detect self-loop edges', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'n1', type: 'userPrompt', label: 'Self-Loop', config: VALID_USER_PROMPT_CONFIG },
        ],
        edges: [
          {
            id: 'loop',
            sourceNodeId: 'n1',
            sourcePortKey: 'prompt',
            targetNodeId: 'n1',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const selfLoop = issues.find((i) => i.message.includes('Self-loop'));
      expect(selfLoop).toBeDefined();
      expect(selfLoop!.severity).toBe('error');
      expect(selfLoop!.scope).toBe('edge');
      expect(selfLoop!.code).toBe('configInvalid');
      expect(selfLoop!.edgeId).toBe('loop');
    });

    it('should error on incompatible port types (text -> prompt)', () => {
      // We need a node that outputs 'text'. reviewDecision outputs reviewDecision,
      // subtitleAsset outputs text in the sense that subtitleAsset->text is coercion.
      // Let's use two nodes where the output type is truly incompatible with input type.
      // scriptWriter outputs 'script', sceneSplitter expects 'script' input.
      // promptRefiner expects 'sceneList', scriptWriter outputs 'script'.
      // script -> sceneList is coercion (compatible with warning), not error.
      // Let's use userPrompt (prompt output) -> sceneSplitter (script input) => incompatible
      const doc = makeDoc({
        nodes: [
          { id: 'up', type: 'userPrompt', label: 'Prompt', config: VALID_USER_PROMPT_CONFIG },
          {
            id: 'ss',
            type: 'sceneSplitter',
            label: 'Splitter',
            config: VALID_SCENE_SPLITTER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'up',
            sourcePortKey: 'prompt',
            targetNodeId: 'ss',
            targetPortKey: 'script',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const incompatible = findByCode(issues, 'incompatiblePortTypes');
      expect(incompatible).toBeDefined();
      expect(incompatible!.severity).toBe('error');
      expect(incompatible!.scope).toBe('edge');
      expect(incompatible!.message).toContain('prompt');
      expect(incompatible!.message).toContain('script');
    });

    it('should warn when coercion is applied (text -> textList)', () => {
      // We need a source that outputs text and a target that accepts textList.
      // subtitleAsset -> text is available from subtitleFormatter output.
      // But simpler: use two nodes where one outputs 'text' and the other accepts 'textList'.
      // Actually, let's look at what outputs text. reviewDecision outputs reviewDecision.
      // The subtitleFormatter outputs subtitleAsset. We need text -> textList.
      // Since we may not have a node that outputs plain 'text', let's test
      // with script -> sceneList which IS a coercion case (compatible with warning).
      const doc = makeDoc({
        nodes: [
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
          {
            id: 'pr',
            type: 'promptRefiner',
            label: 'Refiner',
            config: {
              visualStyle: 'photorealistic',
              cameraLanguage: 'standard',
              aspectRatio: '16:9',
            },
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'sw',
            sourcePortKey: 'script',
            targetNodeId: 'pr',
            targetPortKey: 'sceneList',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      // script -> sceneList: compatible: true, coercionApplied: true per matrix
      const coercion = findByCode(issues, 'coercionApplied');
      expect(coercion).toBeDefined();
      expect(coercion!.severity).toBe('warning');
      expect(coercion!.scope).toBe('edge');
      expect(coercion!.edgeId).toBe('e1');
    });

    it('should error on duplicate edges to single-value (multiple:false) port', () => {
      // scriptWriter 'prompt' input has multiple:false
      const doc = makeDoc({
        nodes: [
          { id: 'up1', type: 'userPrompt', label: 'Prompt 1', config: VALID_USER_PROMPT_CONFIG },
          { id: 'up2', type: 'userPrompt', label: 'Prompt 2', config: VALID_USER_PROMPT_CONFIG },
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'up1',
            sourcePortKey: 'prompt',
            targetNodeId: 'sw',
            targetPortKey: 'prompt',
          },
          {
            id: 'e2',
            sourceNodeId: 'up2',
            sourcePortKey: 'prompt',
            targetNodeId: 'sw',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const dup = issues.find((i) => i.message.includes('multiple connections'));
      expect(dup).toBeDefined();
      expect(dup!.severity).toBe('error');
    });

    it('should error when source port does not exist on template', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'up', type: 'userPrompt', label: 'Prompt', config: VALID_USER_PROMPT_CONFIG },
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'up',
            sourcePortKey: 'nonExistentPort',
            targetNodeId: 'sw',
            targetPortKey: 'prompt',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const portErr = issues.find(
        (i) => i.message.includes('nonExistentPort') && i.message.includes('does not exist'),
      );
      expect(portErr).toBeDefined();
      expect(portErr!.severity).toBe('error');
      expect(portErr!.scope).toBe('edge');
    });

    it('should error when target port does not exist on template', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'up', type: 'userPrompt', label: 'Prompt', config: VALID_USER_PROMPT_CONFIG },
          {
            id: 'sw',
            type: 'scriptWriter',
            label: 'Writer',
            config: VALID_SCRIPT_WRITER_CONFIG,
          },
        ],
        edges: [
          {
            id: 'e1',
            sourceNodeId: 'up',
            sourcePortKey: 'prompt',
            targetNodeId: 'sw',
            targetPortKey: 'nonExistentPort',
          },
        ],
      });
      const issues = validateWorkflow(doc);
      const portErr = issues.find(
        (i) => i.message.includes('nonExistentPort') && i.message.includes('does not exist'),
      );
      expect(portErr).toBeDefined();
      expect(portErr!.severity).toBe('error');
      expect(portErr!.scope).toBe('edge');
    });
  });

  // ----------------------------------------------------------
  // Result ordering
  // ----------------------------------------------------------
  describe('Issue ordering', () => {
    it('should sort errors before warnings', () => {
      // Produce both errors and warnings: duplicate IDs (error) + no name (warning)
      const doc = makeDoc({
        name: '',
        nodes: [
          { id: 'dup', type: 'userPrompt' },
          { id: 'dup', type: 'scriptWriter' },
        ],
      });
      const issues = validateWorkflow(doc);
      expect(issues.length).toBeGreaterThan(1);

      const errors = issues.filter((i) => i.severity === 'error');
      const warnings = issues.filter((i) => i.severity === 'warning');
      expect(errors.length).toBeGreaterThan(0);
      expect(warnings.length).toBeGreaterThan(0);

      // All errors should appear before all warnings
      let lastErrorIdx = -1;
      for (let idx = issues.length - 1; idx >= 0; idx--) {
        if (issues[idx].severity === 'error') {
          lastErrorIdx = idx;
          break;
        }
      }
      const firstWarningIdx = issues.findIndex((i) => i.severity === 'warning');
      expect(lastErrorIdx).toBeLessThan(firstWarningIdx);
    });
  });

  // ----------------------------------------------------------
  // Unique issue IDs
  // ----------------------------------------------------------
  describe('Issue IDs', () => {
    it('should assign unique IDs to all issues', () => {
      const doc = makeDoc({
        name: '',
        nodes: [
          { id: 'dup', type: 'userPrompt' },
          { id: 'dup', type: 'scriptWriter' },
        ],
      });
      const issues = validateWorkflow(doc);
      const ids = issues.map((i) => i.id);
      const uniqueIds = new Set(ids);
      expect(uniqueIds.size).toBe(ids.length);
    });

    it('should produce independent counters between calls', () => {
      const doc = makeDoc({
        nodes: [{ id: 'n1', type: 'userPrompt', config: VALID_USER_PROMPT_CONFIG }],
      });
      const issues1 = validateWorkflow(doc);
      const issues2 = validateWorkflow(doc);
      // Both calls should produce the same set of IDs (counter resets per call)
      expect(issues1.map((i) => i.id)).toEqual(issues2.map((i) => i.id));
    });
  });

  // ----------------------------------------------------------
  // Helper functions
  // ----------------------------------------------------------
  describe('Helper functions', () => {
    it('hasErrors returns true when there are errors', () => {
      const doc = makeDoc({
        nodes: [
          { id: 'dup', type: 'userPrompt' },
          { id: 'dup', type: 'scriptWriter' },
        ],
      });
      expect(hasErrors(doc)).toBe(true);
    });

    it('hasErrors returns false for a valid workflow', () => {
      const doc = makeDoc({
        name: 'Valid',
        nodes: [
          {
            id: 'up',
            type: 'userPrompt',
            label: 'Prompt',
            config: VALID_USER_PROMPT_CONFIG,
          },
        ],
      });
      expect(hasErrors(doc)).toBe(false);
    });

    it('getValidationSummary returns correct counts', () => {
      // Duplicate node IDs produce errors; empty name produces warning
      const doc = makeDoc({
        name: '',
        nodes: [
          { id: 'dup', type: 'userPrompt' },
          { id: 'dup', type: 'scriptWriter' },
        ],
      });
      const summary = getValidationSummary(doc);
      expect(summary.errorCount).toBeGreaterThan(0);
      expect(summary.warningCount).toBeGreaterThan(0);
      expect(summary.isValid).toBe(false);
    });

    it('getValidationSummary reports valid for clean workflow', () => {
      const doc = makeDoc({
        name: 'Clean',
        nodes: [
          {
            id: 'up',
            type: 'userPrompt',
            label: 'Prompt',
            config: VALID_USER_PROMPT_CONFIG,
          },
        ],
      });
      const summary = getValidationSummary(doc);
      expect(summary.errorCount).toBe(0);
      expect(summary.isValid).toBe(true);
    });
  });
});
