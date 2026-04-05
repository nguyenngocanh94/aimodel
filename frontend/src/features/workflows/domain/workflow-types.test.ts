import { describe, it, expect } from 'vitest';
import type {
  DataType,
  PortDefinition,
  WorkflowNode,
  WorkflowEdge,
  WorkflowDocument,
  PortPayload,
  ValidationIssue,
  CompatibilityResult,
  ValidationSeverity,
} from './workflow-types';

describe('Core Workflow Types - AiModel-9wx.1', () => {
  it('should define all 17 semantic DataType variants', () => {
    const allTypes: DataType[] = [
      'text', 'textList', 'prompt', 'promptList', 'script',
      'scene', 'sceneList', 'imageFrame', 'imageFrameList',
      'imageAsset', 'imageAssetList', 'audioPlan', 'audioAsset',
      'subtitleAsset', 'videoAsset', 'reviewDecision', 'json',
    ];
    expect(allTypes).toHaveLength(17);
    // Verify no duplicates
    expect(new Set(allTypes).size).toBe(17);
  });

  it('should create valid PortDefinition', () => {
    const port: PortDefinition = {
      key: 'input',
      label: 'Input Text',
      direction: 'input',
      dataType: 'text',
      required: true,
      multiple: false,
      description: 'The input text to process',
    };
    
    expect(port.key).toBe('input');
    expect(port.direction).toBe('input');
    expect(port.required).toBe(true);
  });

  it('should create valid WorkflowNode', () => {
    const node: WorkflowNode = {
      id: 'node-1',
      type: 'userPrompt',
      label: 'User Prompt',
      position: { x: 100, y: 200 },
      config: { prompt: 'Hello world' },
      disabled: false,
      notes: 'This is a test node',
    };
    
    expect(node.id).toBe('node-1');
    expect(node.position.x).toBe(100);
    expect(node.position.y).toBe(200);
  });

  it('should create valid WorkflowEdge', () => {
    const edge: WorkflowEdge = {
      id: 'edge-1',
      sourceNodeId: 'node-1',
      sourcePortKey: 'output',
      targetNodeId: 'node-2',
      targetPortKey: 'input',
      targetOrder: 0,
    };
    
    expect(edge.sourceNodeId).toBe('node-1');
    expect(edge.targetNodeId).toBe('node-2');
  });

  it('should create valid WorkflowDocument', () => {
    const doc: WorkflowDocument = {
      id: 'wf-1',
      schemaVersion: 1,
      name: 'Test Workflow',
      description: 'A test workflow',
      tags: ['test', 'demo'],
      nodes: [],
      edges: [],
      viewport: { x: 0, y: 0, zoom: 1 },
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    
    expect(doc.schemaVersion).toBe(1);
    expect(doc.nodes).toHaveLength(0);
  });

  it('should create valid PortPayload with all optional fields', () => {
    const payload: PortPayload<string> = {
      value: 'test value',
      status: 'success',
      schemaType: 'text',
      producedAt: new Date().toISOString(),
      sourceNodeId: 'node-1',
      sourcePortKey: 'output',
      previewText: 'test val...',
      previewUrl: 'blob:preview',
      sizeBytesEstimate: 100,
      errorMessage: undefined,
    };

    expect(payload.status).toBe('success');
    expect(payload.schemaType).toBe('text');
    expect(payload.value).toBe('test value');
  });

  it('PortPayload value can be null', () => {
    const payload: PortPayload = {
      value: null,
      status: 'idle',
      schemaType: 'json',
    };
    expect(payload.value).toBeNull();
  });

  it('PortPayload accepts all 7 status values', () => {
    const statuses: PortPayload['status'][] = [
      'idle', 'ready', 'running', 'success', 'error', 'skipped', 'cancelled',
    ];
    expect(statuses).toHaveLength(7);
  });

  it('should create valid ValidationIssue', () => {
    const issue: ValidationIssue = {
      id: 'issue-1',
      severity: 'error' as ValidationSeverity,
      scope: 'node',
      message: 'Missing required input',
      nodeId: 'node-1',
      code: 'missingRequiredInput',
      suggestion: 'Connect an edge to the input port',
    };

    expect(issue.severity).toBe('error');
    expect(issue.code).toBe('missingRequiredInput');
  });

  it('ValidationIssue code covers all 8 variants', () => {
    const codes: ValidationIssue['code'][] = [
      'cycleDetected', 'missingRequiredInput', 'incompatiblePortTypes',
      'configInvalid', 'orphanNode', 'disabledNode',
      'coercionApplied', 'downstreamInvalidated',
    ];
    expect(codes).toHaveLength(8);
    expect(new Set(codes).size).toBe(8);
  });

  it('ValidationSeverity covers all 3 levels', () => {
    const severities: ValidationSeverity[] = ['error', 'warning', 'info'];
    expect(severities).toHaveLength(3);
  });

  it('should create valid CompatibilityResult', () => {
    const result: CompatibilityResult = {
      compatible: true,
      coercionApplied: false,
      severity: 'none',
    };
    
    expect(result.compatible).toBe(true);
    expect(result.coercionApplied).toBe(false);
  });

  it('should enforce readonly on WorkflowDocument', () => {
    const doc: WorkflowDocument = {
      id: 'wf-1',
      schemaVersion: 1,
      name: 'Test',
      description: '',
      tags: [],
      nodes: [],
      edges: [],
      viewport: { x: 0, y: 0, zoom: 1 },
      createdAt: '2024-01-01',
      updatedAt: '2024-01-01',
    };
    
    // TypeScript compile-time check: these should be readonly
    // @ts-expect-error - Testing readonly constraint
    doc.id = 'modified';
    // @ts-expect-error - Testing readonly constraint
    doc.schemaVersion = 2;
  });
});
