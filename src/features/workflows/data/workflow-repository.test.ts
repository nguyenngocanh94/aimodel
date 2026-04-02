import { describe, it, expect, beforeEach } from 'vitest';
import { createMemoryRepository, type WorkflowRepository } from './workflow-repository';
import type { WorkflowDocument, WorkflowSnapshot } from '@/features/workflows/domain/workflow-types';

function makeDocument(overrides: Partial<WorkflowDocument> = {}): WorkflowDocument {
  const now = new Date().toISOString();
  return {
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test Workflow',
    description: '',
    tags: ['test'],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
    ...overrides,
  };
}

function makeSnapshot(workflowId: string, savedAt: string): WorkflowSnapshot {
  return {
    id: `snap-${savedAt}`,
    workflowId,
    kind: 'autosave',
    savedAt,
    document: makeDocument({ id: workflowId }),
  };
}

describe('WorkflowRepository (memory)', () => {
  let repo: WorkflowRepository;

  beforeEach(() => {
    repo = createMemoryRepository();
  });

  it('should report memory-fallback mode', () => {
    expect(repo.mode).toBe('memory-fallback');
    expect(repo.isAvailable()).toBe(true);
  });

  it('should return null for non-existent workflow', async () => {
    const result = await repo.load('non-existent');
    expect(result).toBeNull();
  });

  it('should save and load a workflow', async () => {
    const doc = makeDocument();
    await repo.save(doc);

    const loaded = await repo.load('wf-1');
    expect(loaded).not.toBeNull();
    expect(loaded!.id).toBe('wf-1');
    expect(loaded!.name).toBe('Test Workflow');
  });

  it('should update an existing workflow', async () => {
    await repo.save(makeDocument());
    await repo.save(makeDocument({ name: 'Updated Workflow' }));

    const loaded = await repo.load('wf-1');
    expect(loaded!.name).toBe('Updated Workflow');
  });

  it('should list workflows sorted by updatedAt desc', async () => {
    await repo.save(makeDocument({ id: 'wf-old', updatedAt: '2025-01-01T00:00:00Z' }));
    await repo.save(makeDocument({ id: 'wf-new', updatedAt: '2025-06-01T00:00:00Z' }));
    await repo.save(makeDocument({ id: 'wf-mid', updatedAt: '2025-03-01T00:00:00Z' }));

    const list = await repo.list();
    expect(list).toHaveLength(3);
    expect(list[0].id).toBe('wf-new');
    expect(list[1].id).toBe('wf-mid');
    expect(list[2].id).toBe('wf-old');
  });

  it('should delete a workflow', async () => {
    await repo.save(makeDocument());
    await repo.delete('wf-1');

    const loaded = await repo.load('wf-1');
    expect(loaded).toBeNull();

    const list = await repo.list();
    expect(list).toHaveLength(0);
  });

  it('should save and load snapshots', async () => {
    await repo.saveSnapshot(makeSnapshot('wf-1', '2025-01-01T00:00:00Z'));
    await repo.saveSnapshot(makeSnapshot('wf-1', '2025-06-01T00:00:00Z'));

    const latest = await repo.loadLatestSnapshot('wf-1');
    expect(latest).not.toBeNull();
    expect(latest!.savedAt).toBe('2025-06-01T00:00:00Z');
  });

  it('should return null when no snapshots exist', async () => {
    const result = await repo.loadLatestSnapshot('wf-1');
    expect(result).toBeNull();
  });

  it('should delete snapshots when workflow is deleted', async () => {
    await repo.save(makeDocument());
    await repo.saveSnapshot(makeSnapshot('wf-1', '2025-01-01T00:00:00Z'));
    await repo.delete('wf-1');

    const snapshot = await repo.loadLatestSnapshot('wf-1');
    expect(snapshot).toBeNull();
  });
});
