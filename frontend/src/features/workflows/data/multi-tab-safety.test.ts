import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  MultiTabCoordinator,
  getSessionId,
  resetSessionId,
  type SessionAnnouncement,
  type ConflictCallback,
} from './multi-tab-safety'

// Mock BroadcastChannel for tests
class MockBroadcastChannel {
  static instances: MockBroadcastChannel[] = []
  onmessage: ((event: MessageEvent) => void) | null = null
  closed = false

  constructor(public name: string) {
    MockBroadcastChannel.instances.push(this)
  }

  postMessage(msg: unknown): void {
    if (this.closed) return
    // Deliver to all OTHER instances of same channel
    for (const instance of MockBroadcastChannel.instances) {
      if (instance !== this && !instance.closed && instance.name === this.name) {
        instance.onmessage?.({ data: msg } as MessageEvent)
      }
    }
  }

  close(): void {
    this.closed = true
    const idx = MockBroadcastChannel.instances.indexOf(this)
    if (idx !== -1) MockBroadcastChannel.instances.splice(idx, 1)
  }

  static reset(): void {
    MockBroadcastChannel.instances = []
  }
}

// Install mock globally
beforeEach(() => {
  MockBroadcastChannel.reset()
  vi.stubGlobal('BroadcastChannel', MockBroadcastChannel)
  resetSessionId()
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('getSessionId', () => {
  it('should generate a session ID', () => {
    const id = getSessionId()
    expect(id).toMatch(/^session-/)
  })

  it('should return the same ID on subsequent calls', () => {
    const id1 = getSessionId()
    const id2 = getSessionId()
    expect(id1).toBe(id2)
  })

  it('should generate new ID after reset', () => {
    const id1 = getSessionId()
    resetSessionId()
    const id2 = getSessionId()
    expect(id1).not.toBe(id2)
  })
})

describe('MultiTabCoordinator', () => {
  let coord1: MultiTabCoordinator
  let coord2: MultiTabCoordinator
  let onConflict1: ConflictCallback
  let onConflict2: ConflictCallback

  beforeEach(() => {
    coord1 = new MultiTabCoordinator('tab-1')
    coord2 = new MultiTabCoordinator('tab-2')
    onConflict1 = vi.fn()
    onConflict2 = vi.fn()
  })

  afterEach(() => {
    coord1.stop()
    coord2.stop()
  })

  it('should detect conflict when two tabs open same workflow', () => {
    coord1.start('wf-1', onConflict1)
    coord2.start('wf-1', onConflict2)

    // coord2 starting sends a heartbeat to coord1
    expect(onConflict1).toHaveBeenCalled()
    const lastCall1 = (onConflict1 as ReturnType<typeof vi.fn>).mock.calls.at(-1)?.[0]
    expect(lastCall1).toHaveLength(1)
    expect(lastCall1[0].sessionId).toBe('tab-2')
  })

  it('should not detect conflict for different workflows', () => {
    coord1.start('wf-1', onConflict1)
    coord2.start('wf-2', onConflict2)

    // No conflicts for different workflows
    const conflicts1 = coord1.getConflictingSessions()
    const conflicts2 = coord2.getConflictingSessions()
    expect(conflicts1).toHaveLength(0)
    expect(conflicts2).toHaveLength(0)
  })

  it('should clear conflict when tab closes', () => {
    coord1.start('wf-1', onConflict1)
    coord2.start('wf-1', onConflict2)

    // Both see conflict
    expect(coord1.getConflictingSessions()).toHaveLength(1)

    // Tab 2 closes
    coord2.stop()

    // coord1 should receive close message and clear conflict
    expect(coord1.getConflictingSessions()).toHaveLength(0)
  })

  it('should expire sessions after timeout', () => {
    vi.useFakeTimers()

    coord1.start('wf-1', onConflict1)

    // Simulate receiving a heartbeat from a now-crashed tab
    // by directly posting a message to coord1's channel
    const fakeHeartbeat: SessionAnnouncement = {
      type: 'heartbeat',
      sessionId: 'tab-crashed',
      workflowId: 'wf-1',
      timestamp: Date.now(),
    }
    // Deliver to coord1 via the mock channel
    for (const instance of MockBroadcastChannel.instances) {
      if (!instance.closed) {
        instance.onmessage?.({ data: fakeHeartbeat } as MessageEvent)
      }
    }

    expect(coord1.getConflictingSessions()).toHaveLength(1)
    expect(coord1.getConflictingSessions()[0].sessionId).toBe('tab-crashed')

    // Advance past session expiry (15s) + cleanup interval (5s)
    vi.advanceTimersByTime(20_000)

    // After cleanup, expired session should be removed
    expect(coord1.getConflictingSessions()).toHaveLength(0)

    vi.useRealTimers()
  })

  it('should handle switchWorkflow', () => {
    coord1.start('wf-1', onConflict1)
    coord2.start('wf-1', onConflict2)

    expect(coord1.getConflictingSessions()).toHaveLength(1)

    // Coord2 switches to different workflow
    coord2.switchWorkflow('wf-2')

    // coord1 should receive close for wf-1 and then heartbeat for wf-2
    // After close, no conflict for coord1
    expect(coord1.getConflictingSessions()).toHaveLength(0)
  })

  it('should ignore own session announcements', () => {
    // A coordinator should not count itself as a conflict
    coord1.start('wf-1', onConflict1)
    expect(coord1.getConflictingSessions()).toHaveLength(0)
  })

  it('should work without BroadcastChannel', () => {
    vi.stubGlobal('BroadcastChannel', undefined)

    const coord = new MultiTabCoordinator('tab-solo')
    const onConflict = vi.fn()

    // Should not throw
    const cleanup = coord.start('wf-1', onConflict)
    expect(coord.getConflictingSessions()).toHaveLength(0)
    cleanup()
  })

  it('should return cleanup function from start', () => {
    const cleanup = coord1.start('wf-1', onConflict1)
    coord2.start('wf-1', onConflict2)

    expect(coord1.getConflictingSessions()).toHaveLength(1)

    cleanup()
    expect(coord1.getConflictingSessions()).toHaveLength(0)
  })
})
