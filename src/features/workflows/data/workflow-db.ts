/**
 * Dexie database schema for the AI Video Workflow Builder — AiModel-e0x.1
 * Defines WorkflowDexie class with all tables, indexes, and version migration.
 */

import Dexie, { type Table } from 'dexie';
import type {
  WorkflowDocument,
  WorkflowSnapshot,
  ExecutionRun,
  NodeRunRecord,
  PortPayload,
} from '@/features/workflows/domain/workflow-types';

// ============================================================
// Row interfaces
// ============================================================

export interface StoredWorkflowRow {
  readonly id: string;
  readonly name: string;
  readonly updatedAt: string;
  readonly basedOnTemplateId?: string;
  readonly tags: readonly string[];
  readonly document: WorkflowDocument;
}

export interface NodeRunRecordRow extends NodeRunRecord {
  readonly id: string;
  readonly workflowId: string;
}

export interface RunCacheEntry {
  readonly id: string;
  readonly workflowId: string;
  readonly nodeId: string;
  readonly cacheKey: string;
  readonly nodeType: string;
  readonly nodeTemplateVersion: string;
  readonly createdAt: string;
  readonly lastAccessedAt: string;
  readonly expiresAt?: string;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
}

export interface AppPreferenceRow {
  readonly key: string;
  readonly value: unknown;
}

// ============================================================
// Database class
// ============================================================

export class WorkflowDexie extends Dexie {
  workflows!: Table<StoredWorkflowRow, string>;
  workflowSnapshots!: Table<WorkflowSnapshot, string>;
  executionRuns!: Table<ExecutionRun, string>;
  nodeRunRecords!: Table<NodeRunRecordRow, string>;
  runCacheEntries!: Table<RunCacheEntry, string>;
  appPreferences!: Table<AppPreferenceRow, string>;

  constructor() {
    super('ai-video-builder');

    this.version(1).stores({
      workflows: 'id, updatedAt, name, *tags',
      workflowSnapshots: 'id, workflowId, kind, savedAt',
      executionRuns: 'id, workflowId, status, startedAt',
      nodeRunRecords: 'id, runId, workflowId, nodeId, status',
      runCacheEntries: 'id, workflowId, nodeId, cacheKey, createdAt, lastAccessedAt',
      appPreferences: 'key',
    });

    this.version(2).stores({
      workflows: 'id, updatedAt, name, basedOnTemplateId, *tags',
      workflowSnapshots: 'id, workflowId, kind, savedAt, interruptedRunId',
      executionRuns: 'id, workflowId, status, trigger, startedAt',
      nodeRunRecords: 'id, runId, workflowId, nodeId, status, completedAt',
      runCacheEntries: 'id, workflowId, nodeId, cacheKey, nodeType, lastAccessedAt, expiresAt',
      appPreferences: 'key',
    }).upgrade(async (tx) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await tx.table('workflows').toCollection().modify((row: any) => {
        if (!row.basedOnTemplateId && row.document?.basedOnTemplateId) {
          row.basedOnTemplateId = row.document.basedOnTemplateId;
        }
      });
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      await tx.table('runCacheEntries').toCollection().modify((row: any) => {
        if (!row.lastAccessedAt) {
          row.lastAccessedAt = row.createdAt;
        }
      });
    });
  }
}

// ============================================================
// Singleton factory
// ============================================================

let dbInstance: WorkflowDexie | null = null;

export function getDatabase(): WorkflowDexie {
  if (!dbInstance) {
    dbInstance = new WorkflowDexie();
  }
  return dbInstance;
}

/** For testing — reset the singleton and close the connection. */
export function resetDatabase(): void {
  if (dbInstance) {
    dbInstance.close();
    dbInstance = null;
  }
}
