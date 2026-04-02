import { describe, it, expect, vi, beforeEach } from 'vitest'
import {
  RETENTION_DEFAULTS,
  selectSnapshotsToPrune,
  pruneSnapshots,
  runGarbageCollection,
  persistWithQuotaRecovery,
  type RetentionLimits,
} from './retention-gc'
import { createMemoryRepository, type WorkflowRepository } from './workflow-repository'
import type { WorkflowDocument, WorkflowSnapshot } from '@/features/workflows/domain/workflow-types'

function makeDocument(id = 'wf-1'): WorkflowDocument {
  return {
    id,
    schemaVersion: 1,
    name: 'Test',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00Z',
    updatedAt: '2025-01-01T00:00:00Z',
  }
}

function makeSnapshot(
  id: string,
  workflowId: string,
  savedAt: string,
  kind: 'autosave' | 'recovery' = 'autosave',
): WorkflowSnapshot {
  return {
    id,
    workflowId,
    kind,
    savedAt,
    document: makeDocument(workflowId),
  }
}

describe('RETENTION_DEFAULTS', () => {
  it('should have expected default values', () => {
    expect(RETENTION_DEFAULTS.maxAutosaveSnapshots).toBe(20)
    expect(RETENTION_DEFAULTS.maxExecutionRuns).toBe(10)
    expect(RETENTION_DEFAULTS.maxCacheEntriesPerFamily).toBe(3)
  })
})

describe('selectSnapshotsToPrune', () => {
  const limits: RetentionLimits = { maxAutosaveSnapshots: 3 }

  it('should keep all snapshots when under limit', () => {
    const snapshots = [
      makeSnapshot('s1', 'wf-1', '2025-01-01T00:00:00Z'),
      makeSnapshot('s2', 'wf-1', '2025-01-02T00:00:00Z'),
    ]
    const result = selectSnapshotsToPrune(snapshots, limits)
    expect(result.toPrune).toHaveLength(0)
    expect(result.toKeep).toHaveLength(2)
  })

  it('should prune oldest autosaves when over limit', () => {
    const snapshots = [
      makeSnapshot('s1', 'wf-1', '2025-01-01T00:00:00Z'),
      makeSnapshot('s2', 'wf-1', '2025-01-02T00:00:00Z'),
      makeSnapshot('s3', 'wf-1', '2025-01-03T00:00:00Z'),
      makeSnapshot('s4', 'wf-1', '2025-01-04T00:00:00Z'),
      makeSnapshot('s5', 'wf-1', '2025-01-05T00:00:00Z'),
    ]
    const result = selectSnapshotsToPrune(snapshots, limits)
    expect(result.toPrune).toEqual(['s2', 's1'])
    expect(result.toKeep).toEqual(['s5', 's4', 's3'])
  })

  it('should never prune the latest recovery snapshot', () => {
    const snapshots = [
      makeSnapshot('s1', 'wf-1', '2025-01-01T00:00:00Z'),
      makeSnapshot('s2', 'wf-1', '2025-01-02T00:00:00Z'),
      makeSnapshot('s3', 'wf-1', '2025-01-03T00:00:00Z'),
      makeSnapshot('s4', 'wf-1', '2025-01-04T00:00:00Z'),
      makeSnapshot('r1', 'wf-1', '2025-01-05T00:00:00Z', 'recovery'),
    ]
    const result = selectSnapshotsToPrune(snapshots, limits)
    expect(result.toPrune).toContain('s1')
    expect(result.toPrune).not.toContain('r1')
    expect(result.protectedIds).toContain('r1')
  })

  it('should prune old recovery snapshots but keep the latest', () => {
    const snapshots = [
      makeSnapshot('r1', 'wf-1', '2025-01-01T00:00:00Z', 'recovery'),
      makeSnapshot('r2', 'wf-1', '2025-01-05T00:00:00Z', 'recovery'),
    ]
    const result = selectSnapshotsToPrune(snapshots, limits)
    expect(result.toPrune).toContain('r1')
    expect(result.toPrune).not.toContain('r2')
    expect(result.protectedIds).toContain('r2')
  })

  it('should not count recovery snapshots against autosave limit', () => {
    const snapshots = [
      makeSnapshot('s1', 'wf-1', '2025-01-01T00:00:00Z'),
      makeSnapshot('s2', 'wf-1', '2025-01-02T00:00:00Z'),
      makeSnapshot('s3', 'wf-1', '2025-01-03T00:00:00Z'),
      makeSnapshot('r1', 'wf-1', '2025-01-04T00:00:00Z', 'recovery'),
    ]
    const result = selectSnapshotsToPrune(snapshots, limits)
    // 3 autosaves exactly at limit, plus 1 recovery — nothing pruned
    expect(result.toPrune).toHaveLength(0)
  })

  it('should handle empty snapshot list', () => {
    const result = selectSnapshotsToPrune([], limits)
    expect(result.toPrune).toHaveLength(0)
    expect(result.toKeep).toHaveLength(0)
  })
})

describe('pruneSnapshots', () => {
  let repo: WorkflowRepository

  beforeEach(() => {
    repo = createMemoryRepository()
  })

  it('should delete old snapshots from repository', async () => {
    for (let i = 0; i < 5; i++) {
      await repo.saveSnapshot(
        makeSnapshot(`s${i}`, 'wf-1', `2025-01-0${i + 1}T00:00:00Z`),
      )
    }

    const result = await pruneSnapshots(repo, 'wf-1', { maxAutosaveSnapshots: 2 })
    expect(result.pruned).toBe(3)
    expect(result.kept).toBe(2)

    const remaining = await repo.listSnapshots('wf-1')
    expect(remaining).toHaveLength(2)
    expect(remaining[0].id).toBe('s4')
    expect(remaining[1].id).toBe('s3')
  })

  it('should protect recovery snapshots during pruning', async () => {
    await repo.saveSnapshot(makeSnapshot('s1', 'wf-1', '2025-01-01T00:00:00Z'))
    await repo.saveSnapshot(makeSnapshot('r1', 'wf-1', '2025-01-02T00:00:00Z', 'recovery'))

    const result = await pruneSnapshots(repo, 'wf-1', { maxAutosaveSnapshots: 1 })
    expect(result.pruned).toBe(0)
    expect(result.protectedIds).toContain('r1')

    const remaining = await repo.listSnapshots('wf-1')
    expect(remaining).toHaveLength(2)
  })
})

describe('runGarbageCollection', () => {
  it('should return combined GC results', async () => {
    const repo = createMemoryRepository()
    for (let i = 0; i < 5; i++) {
      await repo.saveSnapshot(
        makeSnapshot(`s${i}`, 'wf-1', `2025-01-0${i + 1}T00:00:00Z`),
      )
    }

    const result = await runGarbageCollection(repo, 'wf-1', { maxAutosaveSnapshots: 2 })
    expect(result.snapshotsPruned).toBe(3)
    expect(result.snapshotsKept).toBe(2)
  })
})

describe('persistWithQuotaRecovery', () => {
  it('should return result on successful write', async () => {
    const result = await persistWithQuotaRecovery(
      async () => 'ok',
      async () => {},
    )
    expect(result).toEqual({ result: 'ok', retried: false })
  })

  it('should prune and retry on QuotaExceededError', async () => {
    let attempt = 0
    const write = async () => {
      attempt++
      if (attempt === 1) {
        const err = new DOMException('Quota exceeded', 'QuotaExceededError')
        throw err
      }
      return 'ok after retry'
    }
    const prune = vi.fn(async () => {})

    const result = await persistWithQuotaRecovery(write, prune)
    expect(prune).toHaveBeenCalledOnce()
    expect(result).toEqual({ result: 'ok after retry', retried: true })
  })

  it('should return quota error when retry also fails', async () => {
    const quotaError = new DOMException('Quota exceeded', 'QuotaExceededError')
    const write = async () => {
      throw quotaError
    }
    const prune = vi.fn(async () => {})

    const result = await persistWithQuotaRecovery(write, prune)
    expect(prune).toHaveBeenCalledOnce()
    expect('quotaExceeded' in result && result.quotaExceeded).toBe(true)
  })

  it('should re-throw non-quota errors', async () => {
    const write = async () => {
      throw new Error('Network failure')
    }

    await expect(
      persistWithQuotaRecovery(write, async () => {}),
    ).rejects.toThrow('Network failure')
  })

  it('should detect Dexie-wrapped quota errors', async () => {
    let attempt = 0
    const write = async () => {
      attempt++
      if (attempt === 1) {
        throw new Error('QuotaExceeded: storage full')
      }
      return 'recovered'
    }

    const result = await persistWithQuotaRecovery(write, async () => {})
    expect(result).toEqual({ result: 'recovered', retried: true })
  })
})
