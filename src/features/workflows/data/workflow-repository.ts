/**
 * WorkflowRepository - AiModel-e0x.2
 * Persistence layer for workflow documents with autosave and memory fallback.
 * Per plan sections 12.2.2 and 12.3
 */

import type { WorkflowDocument, WorkflowSnapshot } from '@/features/workflows/domain/workflow-types';
import { WorkflowDexie, type StoredWorkflowRow } from './workflow-db';

// ============================================================
// Persistence mode
// ============================================================

export type PersistenceMode = 'indexeddb' | 'memory-fallback' | 'unavailable';

export interface RepositoryOpenResult {
  readonly mode: PersistenceMode;
  readonly reason?: string;
}

// ============================================================
// Repository interface (shared by IndexedDB and memory)
// ============================================================

export interface WorkflowRepository {
  readonly mode: PersistenceMode;

  /** Load a workflow by ID. Returns null if not found. */
  load(id: string): Promise<WorkflowDocument | null>;

  /** Save (create or update) a workflow document. */
  save(document: WorkflowDocument): Promise<void>;

  /** List all saved workflows (sorted by updatedAt desc). */
  list(): Promise<readonly StoredWorkflowRow[]>;

  /** Delete a workflow by ID. */
  delete(id: string): Promise<void>;

  /** Save a recovery snapshot. */
  saveSnapshot(snapshot: WorkflowSnapshot): Promise<void>;

  /** Load the latest recovery snapshot for a workflow. */
  loadLatestSnapshot(workflowId: string): Promise<WorkflowSnapshot | null>;

  /** Check if the repository is operational. */
  isAvailable(): boolean;
}

// ============================================================
// IndexedDB implementation
// ============================================================

class IndexedDbRepository implements WorkflowRepository {
  readonly mode: PersistenceMode = 'indexeddb';

  constructor(private readonly db: WorkflowDexie) {}

  async load(id: string): Promise<WorkflowDocument | null> {
    const row = await this.db.workflows.get(id);
    return row?.document ?? null;
  }

  async save(document: WorkflowDocument): Promise<void> {
    const row: StoredWorkflowRow = {
      id: document.id,
      name: document.name,
      updatedAt: document.updatedAt,
      basedOnTemplateId: document.basedOnTemplateId,
      tags: [...document.tags],
      document,
    };
    await this.db.workflows.put(row);
  }

  async list(): Promise<readonly StoredWorkflowRow[]> {
    return this.db.workflows
      .orderBy('updatedAt')
      .reverse()
      .toArray();
  }

  async delete(id: string): Promise<void> {
    await this.db.transaction('rw', [this.db.workflows, this.db.workflowSnapshots], async () => {
      await this.db.workflows.delete(id);
      await this.db.workflowSnapshots
        .where('workflowId')
        .equals(id)
        .delete();
    });
  }

  async saveSnapshot(snapshot: WorkflowSnapshot): Promise<void> {
    await this.db.workflowSnapshots.put(snapshot);
  }

  async loadLatestSnapshot(workflowId: string): Promise<WorkflowSnapshot | null> {
    const snapshots = await this.db.workflowSnapshots
      .where('workflowId')
      .equals(workflowId)
      .reverse()
      .sortBy('savedAt');
    return snapshots[0] ?? null;
  }

  isAvailable(): boolean {
    return this.db.isOpen();
  }
}

// ============================================================
// In-memory fallback implementation
// ============================================================

class MemoryRepository implements WorkflowRepository {
  readonly mode: PersistenceMode = 'memory-fallback';
  private workflows = new Map<string, StoredWorkflowRow>();
  private snapshots = new Map<string, WorkflowSnapshot[]>();

  async load(id: string): Promise<WorkflowDocument | null> {
    return this.workflows.get(id)?.document ?? null;
  }

  async save(document: WorkflowDocument): Promise<void> {
    this.workflows.set(document.id, {
      id: document.id,
      name: document.name,
      updatedAt: document.updatedAt,
      basedOnTemplateId: document.basedOnTemplateId,
      tags: [...document.tags],
      document,
    });
  }

  async list(): Promise<readonly StoredWorkflowRow[]> {
    return [...this.workflows.values()].sort(
      (a, b) => b.updatedAt.localeCompare(a.updatedAt),
    );
  }

  async delete(id: string): Promise<void> {
    this.workflows.delete(id);
    this.snapshots.delete(id);
  }

  async saveSnapshot(snapshot: WorkflowSnapshot): Promise<void> {
    const list = this.snapshots.get(snapshot.workflowId) ?? [];
    list.push(snapshot);
    this.snapshots.set(snapshot.workflowId, list);
  }

  async loadLatestSnapshot(workflowId: string): Promise<WorkflowSnapshot | null> {
    const list = this.snapshots.get(workflowId) ?? [];
    if (list.length === 0) return null;
    return list.sort((a, b) => b.savedAt.localeCompare(a.savedAt))[0];
  }

  isAvailable(): boolean {
    return true;
  }
}

// ============================================================
// Factory
// ============================================================

/**
 * Open the workflow repository. Tries IndexedDB first, falls back to memory.
 */
export async function openWorkflowRepository(): Promise<{
  readonly repository: WorkflowRepository;
  readonly mode: PersistenceMode;
  readonly reason?: string;
}> {
  if (typeof indexedDB === 'undefined') {
    return {
      repository: new MemoryRepository(),
      mode: 'memory-fallback',
      reason: 'IndexedDB API unavailable',
    };
  }

  try {
    const db = new WorkflowDexie();
    await db.open();
    return {
      repository: new IndexedDbRepository(db),
      mode: 'indexeddb',
    };
  } catch (error) {
    return {
      repository: new MemoryRepository(),
      mode: 'memory-fallback',
      reason: error instanceof Error ? error.message : 'Failed to open IndexedDB',
    };
  }
}

/**
 * Create a memory-only repository (for testing or unavailable IndexedDB).
 */
export function createMemoryRepository(): WorkflowRepository {
  return new MemoryRepository();
}
