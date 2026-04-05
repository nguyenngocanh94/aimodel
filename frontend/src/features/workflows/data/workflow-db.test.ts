import 'fake-indexeddb/auto';
import Dexie from 'dexie';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  WorkflowDexie,
  getDatabase,
  resetDatabase,
  type StoredWorkflowRow,
  type AppPreferenceRow,
  type RunCacheEntry,
} from './workflow-db';
import type { WorkflowDocument, PortPayload } from '@/features/workflows/domain/workflow-types';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeMinimalDocument(overrides: Partial<WorkflowDocument> = {}): WorkflowDocument {
  return {
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test Workflow',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00.000Z',
    updatedAt: '2025-01-01T00:00:00.000Z',
    ...overrides,
  };
}

function makePortPayload(overrides: Partial<PortPayload> = {}): PortPayload {
  return {
    value: 'test',
    status: 'success',
    schemaType: 'text',
    ...overrides,
  };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('WorkflowDexie — AiModel-e0x.1', () => {
  let db: WorkflowDexie;

  beforeEach(() => {
    db = new WorkflowDexie();
  });

  afterEach(async () => {
    db.close();
    await Dexie.delete('ai-video-builder');
    resetDatabase();
  });

  // -----------------------------------------------------------------------
  // Instantiation
  // -----------------------------------------------------------------------

  it('can be created without errors', () => {
    expect(db).toBeInstanceOf(WorkflowDexie);
    expect(db).toBeInstanceOf(Dexie);
  });

  it('has all 6 tables on the instance', () => {
    expect(db.workflows).toBeDefined();
    expect(db.workflowSnapshots).toBeDefined();
    expect(db.executionRuns).toBeDefined();
    expect(db.nodeRunRecords).toBeDefined();
    expect(db.runCacheEntries).toBeDefined();
    expect(db.appPreferences).toBeDefined();
  });

  it('table names match expected list', () => {
    const tableNames = db.tables.map((t) => t.name).sort();
    const expected = [
      'appPreferences',
      'executionRuns',
      'nodeRunRecords',
      'runCacheEntries',
      'workflowSnapshots',
      'workflows',
    ];
    expect(tableNames).toEqual(expected);
  });

  // -----------------------------------------------------------------------
  // StoredWorkflowRow CRUD
  // -----------------------------------------------------------------------

  it('can put and get a StoredWorkflowRow', async () => {
    const row: StoredWorkflowRow = {
      id: 'wf-1',
      name: 'My Workflow',
      updatedAt: '2025-06-01T12:00:00.000Z',
      tags: ['demo', 'test'],
      document: makeMinimalDocument({ name: 'My Workflow' }),
    };

    await db.workflows.put(row);
    const fetched = await db.workflows.get('wf-1');

    expect(fetched).toBeDefined();
    expect(fetched!.id).toBe('wf-1');
    expect(fetched!.name).toBe('My Workflow');
    expect(fetched!.tags).toEqual(['demo', 'test']);
    expect(fetched!.document.schemaVersion).toBe(1);
  });

  // -----------------------------------------------------------------------
  // AppPreferenceRow CRUD
  // -----------------------------------------------------------------------

  it('can put and get an AppPreferenceRow', async () => {
    const pref: AppPreferenceRow = {
      key: 'theme',
      value: 'dark',
    };

    await db.appPreferences.put(pref);
    const fetched = await db.appPreferences.get('theme');

    expect(fetched).toBeDefined();
    expect(fetched!.key).toBe('theme');
    expect(fetched!.value).toBe('dark');
  });

  it('AppPreferenceRow value can be an object', async () => {
    const pref: AppPreferenceRow = {
      key: 'canvas-settings',
      value: { snapToGrid: true, gridSize: 16 },
    };

    await db.appPreferences.put(pref);
    const fetched = await db.appPreferences.get('canvas-settings');

    expect(fetched).toBeDefined();
    expect(fetched!.value).toEqual({ snapToGrid: true, gridSize: 16 });
  });

  // -----------------------------------------------------------------------
  // RunCacheEntry CRUD & query by cacheKey
  // -----------------------------------------------------------------------

  it('can store and query RunCacheEntry by cacheKey', async () => {
    const entry: RunCacheEntry = {
      id: 'cache-1',
      workflowId: 'wf-1',
      nodeId: 'node-img-gen',
      cacheKey: 'abc123hash',
      nodeType: 'imageGenerator',
      nodeTemplateVersion: '1.0.0',
      createdAt: '2025-06-01T10:00:00.000Z',
      lastAccessedAt: '2025-06-01T12:00:00.000Z',
      outputPayloads: {
        image: makePortPayload({ schemaType: 'imageAsset' }),
      },
    };

    await db.runCacheEntries.put(entry);

    // Query by cacheKey index
    const results = await db.runCacheEntries
      .where('cacheKey')
      .equals('abc123hash')
      .toArray();

    expect(results).toHaveLength(1);
    expect(results[0].id).toBe('cache-1');
    expect(results[0].nodeType).toBe('imageGenerator');
    expect(results[0].outputPayloads.image.schemaType).toBe('imageAsset');
  });

  it('RunCacheEntry expiresAt is optional', async () => {
    const withExpiry: RunCacheEntry = {
      id: 'cache-2',
      workflowId: 'wf-1',
      nodeId: 'node-1',
      cacheKey: 'key-with-expiry',
      nodeType: 'scriptWriter',
      nodeTemplateVersion: '1.0.0',
      createdAt: '2025-06-01T10:00:00.000Z',
      lastAccessedAt: '2025-06-01T10:00:00.000Z',
      expiresAt: '2025-07-01T10:00:00.000Z',
      outputPayloads: {},
    };

    const withoutExpiry: RunCacheEntry = {
      id: 'cache-3',
      workflowId: 'wf-1',
      nodeId: 'node-2',
      cacheKey: 'key-without-expiry',
      nodeType: 'promptRefiner',
      nodeTemplateVersion: '1.0.0',
      createdAt: '2025-06-01T10:00:00.000Z',
      lastAccessedAt: '2025-06-01T10:00:00.000Z',
      outputPayloads: {},
    };

    await db.runCacheEntries.bulkPut([withExpiry, withoutExpiry]);

    const all = await db.runCacheEntries.toArray();
    expect(all).toHaveLength(2);
    expect(all.find((e) => e.id === 'cache-2')!.expiresAt).toBe('2025-07-01T10:00:00.000Z');
    expect(all.find((e) => e.id === 'cache-3')!.expiresAt).toBeUndefined();
  });

  // -----------------------------------------------------------------------
  // Singleton factory
  // -----------------------------------------------------------------------

  it('getDatabase returns the same instance on repeated calls', () => {
    resetDatabase();
    const a = getDatabase();
    const b = getDatabase();
    expect(a).toBe(b);
    // Clean up
    resetDatabase();
  });

  it('resetDatabase closes and nulls the singleton', () => {
    resetDatabase();
    const first = getDatabase();
    resetDatabase();
    const second = getDatabase();
    expect(first).not.toBe(second);
    resetDatabase();
  });
});
