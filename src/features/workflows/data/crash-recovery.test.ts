import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  buildRecoverySnapshot,
  checkForRecovery,
  restoreFromSnapshot,
  dismissRecovery,
  installUnloadHandler,
  removeUnloadHandler,
  writeRecoveryOnUnload,
} from './crash-recovery'
import { createMemoryRepository, type WorkflowRepository } from './workflow-repository'
import type { WorkflowDocument, WorkflowSnapshot } from '@/features/workflows/domain/workflow-types'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import { useRunStore } from '@/features/execution/store/run-store'

function makeDocument(id = 'wf-1', updatedAt = '2025-06-01T00:00:00Z'): WorkflowDocument {
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
    updatedAt,
  }
}

describe('buildRecoverySnapshot', () => {
  it('should return null when document is not dirty and no active run', () => {
    const result = buildRecoverySnapshot(makeDocument(), false, undefined)
    expect(result).toBeNull()
  })

  it('should create snapshot when document is dirty', () => {
    const doc = makeDocument()
    const result = buildRecoverySnapshot(doc, true, undefined)
    expect(result).not.toBeNull()
    expect(result!.kind).toBe('recovery')
    expect(result!.workflowId).toBe('wf-1')
    expect(result!.document).toBe(doc)
    expect(result!.interruptedRunId).toBeUndefined()
  })

  it('should create snapshot when active run exists', () => {
    const doc = makeDocument()
    const result = buildRecoverySnapshot(doc, false, 'run-123')
    expect(result).not.toBeNull()
    expect(result!.interruptedRunId).toBe('run-123')
  })

  it('should create snapshot when both dirty and active run', () => {
    const doc = makeDocument()
    const result = buildRecoverySnapshot(doc, true, 'run-456')
    expect(result).not.toBeNull()
    expect(result!.interruptedRunId).toBe('run-456')
  })

  it('should generate unique IDs', () => {
    const doc = makeDocument()
    const s1 = buildRecoverySnapshot(doc, true)
    const s2 = buildRecoverySnapshot(doc, true)
    expect(s1!.id).not.toBe(s2!.id)
  })
})

describe('checkForRecovery', () => {
  let repo: WorkflowRepository

  beforeEach(() => {
    repo = createMemoryRepository()
  })

  it('should return no recovery when no snapshot exists', async () => {
    await repo.save(makeDocument())
    const result = await checkForRecovery(repo, 'wf-1')
    expect(result.hasRecovery).toBe(false)
    expect(result.snapshot).toBeNull()
    expect(result.savedDocument).not.toBeNull()
  })

  it('should detect recovery snapshot newer than saved doc', async () => {
    const savedDoc = makeDocument('wf-1', '2025-06-01T00:00:00Z')
    await repo.save(savedDoc)

    const snapshot: WorkflowSnapshot = {
      id: 'snap-1',
      workflowId: 'wf-1',
      kind: 'recovery',
      savedAt: '2025-06-02T00:00:00Z',
      document: makeDocument('wf-1', '2025-06-01T12:00:00Z'),
    }
    await repo.saveSnapshot(snapshot)

    const result = await checkForRecovery(repo, 'wf-1')
    expect(result.hasRecovery).toBe(true)
    expect(result.snapshotIsNewer).toBe(true)
    expect(result.snapshot).not.toBeNull()
  })

  it('should detect recovery snapshot older than saved doc', async () => {
    const savedDoc = makeDocument('wf-1', '2025-06-03T00:00:00Z')
    await repo.save(savedDoc)

    const snapshot: WorkflowSnapshot = {
      id: 'snap-1',
      workflowId: 'wf-1',
      kind: 'recovery',
      savedAt: '2025-06-01T00:00:00Z',
      document: makeDocument('wf-1', '2025-05-30T00:00:00Z'),
    }
    await repo.saveSnapshot(snapshot)

    const result = await checkForRecovery(repo, 'wf-1')
    expect(result.hasRecovery).toBe(true)
    expect(result.snapshotIsNewer).toBe(false)
  })

  it('should not treat autosave snapshots as recovery', async () => {
    const snapshot: WorkflowSnapshot = {
      id: 'snap-1',
      workflowId: 'wf-1',
      kind: 'autosave',
      savedAt: '2025-06-02T00:00:00Z',
      document: makeDocument('wf-1'),
    }
    await repo.saveSnapshot(snapshot)

    const result = await checkForRecovery(repo, 'wf-1')
    expect(result.hasRecovery).toBe(false)
  })

  it('should handle missing saved document', async () => {
    const snapshot: WorkflowSnapshot = {
      id: 'snap-1',
      workflowId: 'wf-1',
      kind: 'recovery',
      savedAt: '2025-06-01T00:00:00Z',
      document: makeDocument('wf-1'),
    }
    await repo.saveSnapshot(snapshot)

    const result = await checkForRecovery(repo, 'wf-1')
    expect(result.hasRecovery).toBe(true)
    expect(result.savedDocument).toBeNull()
    expect(result.snapshotIsNewer).toBe(true)
  })
})

describe('restoreFromSnapshot', () => {
  it('should return the snapshot document', () => {
    const doc = makeDocument()
    const snapshot: WorkflowSnapshot = {
      id: 'snap-1',
      workflowId: 'wf-1',
      kind: 'recovery',
      savedAt: '2025-06-01T00:00:00Z',
      document: doc,
    }
    expect(restoreFromSnapshot(snapshot)).toBe(doc)
  })
})

describe('dismissRecovery', () => {
  it('should return the saved document', async () => {
    const repo = createMemoryRepository()
    const doc = makeDocument()
    await repo.save(doc)

    const result = await dismissRecovery(repo, 'wf-1')
    expect(result).not.toBeNull()
    expect(result!.id).toBe('wf-1')
  })

  it('should return null if no saved document exists', async () => {
    const repo = createMemoryRepository()
    const result = await dismissRecovery(repo, 'wf-1')
    expect(result).toBeNull()
  })
})

describe('writeRecoveryOnUnload', () => {
  let repo: WorkflowRepository

  beforeEach(() => {
    repo = createMemoryRepository()
  })

  it('should write snapshot when document is dirty', async () => {
    const doc = makeDocument()
    useWorkflowStore.setState({ document: doc, dirty: true })
    useRunStore.setState({ activeRun: null })

    const saveSpy = vi.spyOn(repo, 'saveSnapshot')
    writeRecoveryOnUnload(repo)

    expect(saveSpy).toHaveBeenCalledOnce()
    const snapshot = saveSpy.mock.calls[0][0]
    expect(snapshot.kind).toBe('recovery')
    expect(snapshot.workflowId).toBe('wf-1')
  })

  it('should not write snapshot when clean and no run', () => {
    const doc = makeDocument()
    useWorkflowStore.setState({ document: doc, dirty: false })
    useRunStore.setState({ activeRun: null })

    const saveSpy = vi.spyOn(repo, 'saveSnapshot')
    writeRecoveryOnUnload(repo)

    expect(saveSpy).not.toHaveBeenCalled()
  })
})

describe('installUnloadHandler / removeUnloadHandler', () => {
  afterEach(() => {
    removeUnloadHandler()
  })

  it('should add beforeunload listener', () => {
    const repo = createMemoryRepository()
    const addSpy = vi.spyOn(window, 'addEventListener')
    installUnloadHandler(repo)
    expect(addSpy).toHaveBeenCalledWith('beforeunload', expect.any(Function))
    addSpy.mockRestore()
  })

  it('should remove listener on cleanup', () => {
    const repo = createMemoryRepository()
    const removeSpy = vi.spyOn(window, 'removeEventListener')
    const cleanup = installUnloadHandler(repo)
    cleanup()
    expect(removeSpy).toHaveBeenCalledWith('beforeunload', expect.any(Function))
    removeSpy.mockRestore()
  })
})
