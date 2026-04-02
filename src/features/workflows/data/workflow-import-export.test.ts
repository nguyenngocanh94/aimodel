import { describe, it, expect } from 'vitest';
import {
  exportWorkflow,
  importWorkflow,
  type ExportEnvelope,
} from './workflow-import-export';
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types';

function makeDocument(overrides: Partial<WorkflowDocument> = {}): WorkflowDocument {
  return {
    id: 'wf-test',
    schemaVersion: 1,
    name: 'Test Workflow',
    description: 'A test',
    tags: ['test'],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00Z',
    updatedAt: '2025-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('exportWorkflow', () => {
  it('should produce valid JSON with envelope', () => {
    const doc = makeDocument();
    const json = exportWorkflow(doc);
    const parsed = JSON.parse(json) as ExportEnvelope;

    expect(parsed.format).toBe('ai-video-workflow');
    expect(parsed.version).toBe(1);
    expect(parsed.exportedAt).toBeDefined();
    expect(parsed.document.id).toBe('wf-test');
  });
});

describe('importWorkflow', () => {
  it('should round-trip export/import preserving all data', () => {
    const doc = makeDocument({
      nodes: [{
        id: 'n1',
        type: 'userPrompt',
        label: 'Prompt',
        position: { x: 0, y: 0 },
        config: {
          topic: 'Test',
          goal: 'Goal',
          audience: 'All',
          tone: 'educational',
          durationSeconds: 30,
        },
      }],
    });
    const json = exportWorkflow(doc);
    const result = importWorkflow(json);

    expect(result.status).toBe('success');
    expect(result.document).not.toBeNull();
    expect(result.document!.id).toBe('wf-test');
    expect(result.document!.nodes).toHaveLength(1);
    expect(result.document!.nodes[0].id).toBe('n1');
    expect(result.migrated).toBe(false);
  });

  it('should accept raw document format (no envelope)', () => {
    const doc = makeDocument();
    const json = JSON.stringify(doc);
    const result = importWorkflow(json);

    expect(result.status).toBe('success');
    expect(result.document).not.toBeNull();
  });

  it('should reject invalid JSON', () => {
    const result = importWorkflow('not json{');
    expect(result.status).toBe('errors');
    expect(result.document).toBeNull();
    expect(result.issues[0].message).toContain('Invalid JSON');
  });

  it('should reject non-object input', () => {
    const result = importWorkflow('"string"');
    expect(result.status).toBe('errors');
    expect(result.issues[0].message).toContain('expected an object');
  });

  it('should reject missing required fields', () => {
    const result = importWorkflow('{"foo": "bar"}');
    expect(result.status).toBe('errors');
    expect(result.issues[0].message).toContain('missing required fields');
  });

  it('should reject unsupported schema version', () => {
    const doc = makeDocument({ schemaVersion: 999 });
    const result = importWorkflow(JSON.stringify(doc));
    expect(result.status).toBe('errors');
    expect(result.issues[0].message).toContain('Unsupported schema version');
  });

  it('should warn on unknown node types', () => {
    const doc = makeDocument({
      nodes: [{
        id: 'n1',
        type: 'unknownNodeType',
        label: 'Unknown',
        position: { x: 0, y: 0 },
        config: {},
      }],
    });
    const result = importWorkflow(JSON.stringify(doc));
    expect(result.status).toBe('warnings');
    expect(result.issues.some((i) => i.message.includes('Unknown node type'))).toBe(true);
  });

  it('should error on broken edge references', () => {
    const doc = makeDocument({
      nodes: [{ id: 'n1', type: 'userPrompt', label: 'P', position: { x: 0, y: 0 }, config: {} }],
      edges: [{
        id: 'e1',
        sourceNodeId: 'n1',
        sourcePortKey: 'prompt',
        targetNodeId: 'nonexistent',
        targetPortKey: 'prompt',
      }],
    });
    const result = importWorkflow(JSON.stringify(doc));
    expect(result.status).toBe('errors');
    expect(result.issues.some((i) => i.message.includes('non-existent'))).toBe(true);
  });

  it('should detect incompatible edge types', () => {
    const doc = makeDocument({
      nodes: [
        { id: 'n1', type: 'userPrompt', label: 'Prompt', position: { x: 0, y: 0 }, config: {} },
        { id: 'n2', type: 'videoComposer', label: 'Composer', position: { x: 200, y: 0 }, config: {} },
      ],
      edges: [{
        id: 'e1',
        sourceNodeId: 'n1',
        sourcePortKey: 'prompt',
        targetNodeId: 'n2',
        targetPortKey: 'visualAssets',
      }],
    });
    const result = importWorkflow(JSON.stringify(doc));
    // prompt → imageAssetList is incompatible
    const incompatIssue = result.issues.find((i) => i.message.includes('Incompatible'));
    expect(incompatIssue).toBeDefined();
  });

  it('should warn on invalid node configs', () => {
    const doc = makeDocument({
      nodes: [{
        id: 'n1',
        type: 'userPrompt',
        label: 'Prompt',
        position: { x: 0, y: 0 },
        config: { topic: '' }, // Missing required fields
      }],
    });
    const result = importWorkflow(JSON.stringify(doc));
    const configIssue = result.issues.find((i) => i.message.includes('Config validation'));
    expect(configIssue).toBeDefined();
  });
});
