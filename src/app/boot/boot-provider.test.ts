import { describe, it, expect, vi, beforeEach } from 'vitest'
import { runBootSequence, type BootSequenceArgs, type BootState } from './boot-provider'
import { createMemoryRepository } from '@/features/workflows/data/workflow-repository'
import type { WorkflowDocument } from '@/features/workflows/domain/workflow-types'

function makeDocument(id = 'wf-1'): WorkflowDocument {
  const now = new Date().toISOString()
  return {
    id,
    schemaVersion: 1,
    name: 'Test Workflow',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
  }
}

function makeArgs(overrides: Partial<BootSequenceArgs> = {}): BootSequenceArgs {
  return {
    repository: createMemoryRepository(),
    mode: 'indexeddb',
    hydrateDocument: vi.fn(),
    getLastWorkflowId: () => null,
    ...overrides,
  }
}

describe('runBootSequence', () => {
  it('should return ready when no last workflow exists', async () => {
    const result = await runBootSequence(makeArgs())
    expect(result.status).toBe('ready')
    expect('initialWorkflowId' in result && result.initialWorkflowId).toBeFalsy()
  })

  it('should call onCheckingRecovery', async () => {
    const onCheckingRecovery = vi.fn()
    await runBootSequence(makeArgs({ onCheckingRecovery }))
    expect(onCheckingRecovery).toHaveBeenCalledOnce()
  })

  it('should load and hydrate last-opened workflow', async () => {
    const repo = createMemoryRepository()
    const doc = makeDocument('wf-saved')
    await repo.save(doc)

    const hydrateDocument = vi.fn()
    const result = await runBootSequence(
      makeArgs({
        repository: repo,
        hydrateDocument,
        getLastWorkflowId: () => 'wf-saved',
      }),
    )

    expect(result.status).toBe('ready')
    expect((result as Extract<BootState, { status: 'ready' }>).initialWorkflowId).toBe('wf-saved')
    expect(hydrateDocument).toHaveBeenCalledOnce()
    expect(hydrateDocument.mock.calls[0][0].id).toBe('wf-saved')
  })

  it('should fall through to empty state if last workflow not found', async () => {
    const hydrateDocument = vi.fn()
    const result = await runBootSequence(
      makeArgs({
        hydrateDocument,
        getLastWorkflowId: () => 'wf-missing',
      }),
    )

    expect(result.status).toBe('ready')
    expect('initialWorkflowId' in result && result.initialWorkflowId).toBeFalsy()
    expect(hydrateDocument).not.toHaveBeenCalled()
  })

  it('should return degraded when mode is memory-fallback', async () => {
    const result = await runBootSequence(
      makeArgs({
        mode: 'memory-fallback',
        reason: 'IndexedDB blocked by browser',
      }),
    )

    expect(result.status).toBe('degraded')
    expect((result as Extract<BootState, { status: 'degraded' }>).reason).toBe(
      'IndexedDB blocked by browser',
    )
  })

  it('should hydrate document and return degraded when mode is memory-fallback with saved workflow', async () => {
    const repo = createMemoryRepository()
    const doc = makeDocument('wf-saved')
    await repo.save(doc)

    const hydrateDocument = vi.fn()
    const result = await runBootSequence(
      makeArgs({
        repository: repo,
        mode: 'memory-fallback',
        reason: 'Fallback',
        hydrateDocument,
        getLastWorkflowId: () => 'wf-saved',
      }),
    )

    expect(result.status).toBe('degraded')
    expect(hydrateDocument).toHaveBeenCalledOnce()
  })

  it('should return fatal when repository.load throws', async () => {
    const badRepo = createMemoryRepository()
    vi.spyOn(badRepo, 'load').mockRejectedValue(new Error('Disk corrupted'))

    const result = await runBootSequence(
      makeArgs({
        repository: badRepo,
        getLastWorkflowId: () => 'wf-1',
      }),
    )

    expect(result.status).toBe('fatal')
    expect((result as Extract<BootState, { status: 'fatal' }>).message).toBe('Disk corrupted')
  })

  it('should provide default degraded reason', async () => {
    const result = await runBootSequence(
      makeArgs({
        mode: 'memory-fallback',
      }),
    )

    expect(result.status).toBe('degraded')
    expect((result as Extract<BootState, { status: 'degraded' }>).reason).toBe(
      'Using in-memory storage',
    )
  })
})
