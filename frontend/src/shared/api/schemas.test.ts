import { describe, it, expect } from 'vitest';
import {
  PortPayloadSchema,
  NodeRunRecordSchema,
  ExecutionRunSchema,
  ExecutionRunListSchema,
  WorkflowSchema,
  WorkflowListSchema,
  WorkflowDocumentSchema,
  ArtifactSchema,
  ApiErrorSchema,
} from './schemas';

describe('API Schemas', () => {
  describe('PortPayloadSchema', () => {
    const validPortPayload = {
      value: 'test value',
      status: 'success' as const,
      schemaType: 'text',
      producedAt: '2026-01-15T10:30:00Z',
      sourceNodeId: 'node-1',
      sourcePortKey: 'out',
      previewText: 'test',
    };

    it('should validate valid port payload', () => {
      const result = PortPayloadSchema.safeParse(validPortPayload);
      expect(result.success).toBe(true);
    });

    it('should accept null value', () => {
      const result = PortPayloadSchema.safeParse({
        ...validPortPayload,
        value: null,
      });
      expect(result.success).toBe(true);
    });

    it('should accept all valid statuses', () => {
      const statuses = ['idle', 'ready', 'running', 'success', 'error', 'skipped', 'cancelled'];
      
      for (const status of statuses) {
        const result = PortPayloadSchema.safeParse({
          ...validPortPayload,
          status,
        });
        expect(result.success).toBe(true);
      }
    });

    it('should reject invalid status', () => {
      const result = PortPayloadSchema.safeParse({
        ...validPortPayload,
        status: 'invalid',
      });
      expect(result.success).toBe(false);
    });

    it('should make optional fields truly optional', () => {
      const minimal = {
        value: 'test',
        status: 'success',
        schemaType: 'text',
      };
      
      const result = PortPayloadSchema.safeParse(minimal);
      expect(result.success).toBe(true);
    });
  });

  describe('NodeRunRecordSchema', () => {
    const validRecord = {
      id: '550e8400-e29b-41d4-a716-446655440000',
      runId: '550e8400-e29b-41d4-a716-446655440001',
      nodeId: 'node-1',
      status: 'success' as const,
      inputPayloads: {},
      outputPayloads: {},
      usedCache: true,
      durationMs: 1500,
    };

    it('should validate valid node run record', () => {
      const result = NodeRunRecordSchema.safeParse(validRecord);
      expect(result.success).toBe(true);
    });

    it('should accept awaitingReview status', () => {
      const result = NodeRunRecordSchema.safeParse({
        ...validRecord,
        status: 'awaitingReview',
      });
      expect(result.success).toBe(true);
    });

    it('should reject invalid UUID', () => {
      const result = NodeRunRecordSchema.safeParse({
        ...validRecord,
        id: 'not-a-uuid',
      });
      expect(result.success).toBe(false);
    });
  });

  describe('ExecutionRunSchema', () => {
    const validRun = {
      id: '550e8400-e29b-41d4-a716-446655440000',
      workflowId: '550e8400-e29b-41d4-a716-446655440001',
      trigger: 'runWorkflow' as const,
      status: 'success' as const,
      plannedNodeIds: ['node-1', 'node-2'],
      startedAt: '2026-01-15T10:30:00Z',
      completedAt: '2026-01-15T10:35:00Z',
      terminationReason: undefined,
      nodeRunRecords: [],
    };

    it('should validate valid execution run', () => {
      const result = ExecutionRunSchema.safeParse(validRun);
      expect(result.success).toBe(true);
    });

    it('should accept all valid triggers', () => {
      const triggers = ['runWorkflow', 'runNode', 'runFromHere', 'runUpToHere'];
      
      for (const trigger of triggers) {
        const result = ExecutionRunSchema.safeParse({
          ...validRun,
          trigger,
        });
        expect(result.success).toBe(true);
      }
    });

    it('should accept all valid statuses', () => {
      const statuses = ['pending', 'running', 'success', 'error', 'cancelled', 'awaitingReview', 'interrupted'];
      
      for (const status of statuses) {
        const result = ExecutionRunSchema.safeParse({
          ...validRun,
          status,
        });
        expect(result.success).toBe(true);
      }
    });

    it('should reject invalid trigger', () => {
      const result = ExecutionRunSchema.safeParse({
        ...validRun,
        trigger: 'invalidTrigger',
      });
      expect(result.success).toBe(false);
    });
  });

  describe('ExecutionRunListSchema', () => {
    const validList = {
      data: [
        {
          id: '550e8400-e29b-41d4-a716-446655440000',
          workflowId: '550e8400-e29b-41d4-a716-446655440001',
          trigger: 'runWorkflow',
          status: 'success',
        },
      ],
      meta: {
        currentPage: 1,
        lastPage: 5,
        perPage: 20,
        total: 100,
      },
    };

    it('should validate valid run list', () => {
      const result = ExecutionRunListSchema.safeParse(validList);
      expect(result.success).toBe(true);
    });

    it('should reject missing meta', () => {
      const result = ExecutionRunListSchema.safeParse({
        data: validList.data,
      });
      expect(result.success).toBe(false);
    });
  });

  describe('WorkflowDocumentSchema', () => {
    const validDocument = {
      id: '550e8400-e29b-41d4-a716-446655440000',
      schemaVersion: 1,
      name: 'Test Workflow',
      description: 'A test workflow',
      tags: ['test', 'demo'],
      nodes: [
        {
          id: 'node-1',
          type: 'userPrompt',
          label: 'Input',
          position: { x: 100, y: 100 },
          config: {},
        },
      ],
      edges: [],
      viewport: { x: 0, y: 0, zoom: 1 },
      createdAt: '2026-01-15T10:30:00Z',
      updatedAt: '2026-01-15T10:30:00Z',
    };

    it('should validate valid workflow document', () => {
      const result = WorkflowDocumentSchema.safeParse(validDocument);
      expect(result.success).toBe(true);
    });

    it('should reject invalid schema version type', () => {
      const result = WorkflowDocumentSchema.safeParse({
        ...validDocument,
        schemaVersion: '1',
      });
      expect(result.success).toBe(false);
    });
  });

  describe('WorkflowSchema', () => {
    const validWorkflow = {
      id: '550e8400-e29b-41d4-a716-446655440000',
      name: 'Test Workflow',
      description: 'A test workflow',
      schemaVersion: 1,
      tags: ['test'],
      createdAt: '2026-01-15T10:30:00Z',
      updatedAt: '2026-01-15T10:30:00Z',
    };

    it('should validate valid workflow', () => {
      const result = WorkflowSchema.safeParse(validWorkflow);
      expect(result.success).toBe(true);
    });

    it('should accept workflow with optional document', () => {
      const result = WorkflowSchema.safeParse({
        ...validWorkflow,
        document: {
          id: '550e8400-e29b-41d4-a716-446655440000',
          schemaVersion: 1,
          name: 'Test',
          description: '',
          tags: [],
          nodes: [],
          edges: [],
          viewport: { x: 0, y: 0, zoom: 1 },
          createdAt: '2026-01-15T10:30:00Z',
          updatedAt: '2026-01-15T10:30:00Z',
        },
      });
      expect(result.success).toBe(true);
    });
  });

  describe('WorkflowListSchema', () => {
    const validList = {
      data: [
        {
          id: '550e8400-e29b-41d4-a716-446655440000',
          name: 'Test',
          description: '',
          schemaVersion: 1,
          tags: [],
          createdAt: '2026-01-15T10:30:00Z',
          updatedAt: '2026-01-15T10:30:00Z',
        },
      ],
      meta: {
        currentPage: 1,
        lastPage: 5,
        perPage: 20,
        total: 100,
      },
    };

    it('should validate valid workflow list', () => {
      const result = WorkflowListSchema.safeParse(validList);
      expect(result.success).toBe(true);
    });
  });

  describe('ArtifactSchema', () => {
    const validArtifact = {
      id: '550e8400-e29b-41d4-a716-446655440000',
      runId: '550e8400-e29b-41d4-a716-446655440001',
      nodeId: 'node-1',
      portKey: 'out',
      mimeType: 'image/png',
      sizeBytes: 1024,
      path: '/artifacts/123.png',
      createdAt: '2026-01-15T10:30:00Z',
    };

    it('should validate valid artifact', () => {
      const result = ArtifactSchema.safeParse(validArtifact);
      expect(result.success).toBe(true);
    });
  });

  describe('ApiErrorSchema', () => {
    it('should validate error response', () => {
      const error = {
        error: 'Not Found',
        message: 'Workflow not found',
        errors: {
          name: ['Name is required'],
        },
      };

      const result = ApiErrorSchema.safeParse(error);
      expect(result.success).toBe(true);
    });

    it('should accept minimal error', () => {
      const result = ApiErrorSchema.safeParse({
        error: 'Internal Server Error',
      });
      expect(result.success).toBe(true);
    });
  });

  describe('schema rejection of malformed data', () => {
    it('should reject workflow with missing required fields', () => {
      const result = WorkflowSchema.safeParse({
        id: '550e8400-e29b-41d4-a716-446655440000',
        // missing name, description, schemaVersion, tags
      });
      expect(result.success).toBe(false);
    });

    it('should reject execution run with invalid UUIDs', () => {
      const result = ExecutionRunSchema.safeParse({
        id: 'not-a-uuid',
        workflowId: 'also-not-a-uuid',
        trigger: 'runWorkflow',
        status: 'success',
      });
      expect(result.success).toBe(false);
    });

    it('should reject invalid datetime format', () => {
      const result = WorkflowSchema.safeParse({
        id: '550e8400-e29b-41d4-a716-446655440000',
        name: 'Test',
        description: '',
        schemaVersion: 1,
        tags: [],
        createdAt: '2026-01-15', // invalid format
        updatedAt: '2026-01-15',
      });
      expect(result.success).toBe(false);
    });
  });
});
