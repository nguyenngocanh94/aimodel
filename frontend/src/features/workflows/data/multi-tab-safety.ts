/**
 * Multi-tab safety - AiModel-e0x.8
 * Detects concurrent editing of the same workflow across browser tabs.
 * Per plan section 12.1.1
 *
 * Soft lock: last writer wins, but risk is made explicit via warning banner.
 */

// ============================================================
// Configuration
// ============================================================

const CHANNEL_NAME = 'aimodel:workflow-sessions'
const HEARTBEAT_INTERVAL_MS = 5_000
const SESSION_EXPIRY_MS = 15_000

// ============================================================
// Types
// ============================================================

export interface SessionAnnouncement {
  readonly type: 'heartbeat' | 'close'
  readonly sessionId: string
  readonly workflowId: string
  readonly timestamp: number
}

export interface ActiveSession {
  readonly sessionId: string
  readonly workflowId: string
  readonly lastSeen: number
}

export type ConflictCallback = (sessions: readonly ActiveSession[]) => void

// ============================================================
// Session ID
// ============================================================

let currentSessionId: string | null = null

export function getSessionId(): string {
  if (!currentSessionId) {
    currentSessionId = `session-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  }
  return currentSessionId
}

/** For testing only */
export function resetSessionId(): void {
  currentSessionId = null
}

// ============================================================
// Multi-tab coordinator
// ============================================================

export class MultiTabCoordinator {
  private channel: BroadcastChannel | null = null
  private heartbeatTimer: ReturnType<typeof setInterval> | null = null
  private cleanupTimer: ReturnType<typeof setInterval> | null = null
  private readonly sessions = new Map<string, ActiveSession>()
  private readonly sessionId: string
  private workflowId: string | null = null
  private onConflict: ConflictCallback | null = null

  constructor(sessionId?: string) {
    this.sessionId = sessionId ?? getSessionId()
  }

  /**
   * Start monitoring for a specific workflow.
   * Returns a cleanup function.
   */
  start(workflowId: string, onConflict: ConflictCallback): () => void {
    this.stop()
    this.workflowId = workflowId
    this.onConflict = onConflict

    // Try to open BroadcastChannel
    if (typeof BroadcastChannel !== 'undefined') {
      try {
        this.channel = new BroadcastChannel(CHANNEL_NAME)
        this.channel.onmessage = (event: MessageEvent<SessionAnnouncement>) => {
          this.handleMessage(event.data)
        }
      } catch {
        // BroadcastChannel unavailable — single-tab mode
        this.channel = null
      }
    }

    // Send initial heartbeat
    this.sendHeartbeat()

    // Start heartbeat interval
    this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), HEARTBEAT_INTERVAL_MS)

    // Start cleanup interval (check for expired sessions)
    this.cleanupTimer = setInterval(() => this.cleanupExpiredSessions(), HEARTBEAT_INTERVAL_MS)

    return () => this.stop()
  }

  /**
   * Stop monitoring. Sends a close announcement.
   */
  stop(): void {
    if (this.workflowId && this.channel) {
      this.broadcast({
        type: 'close',
        sessionId: this.sessionId,
        workflowId: this.workflowId,
        timestamp: Date.now(),
      })
    }

    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer)
      this.heartbeatTimer = null
    }
    if (this.cleanupTimer) {
      clearInterval(this.cleanupTimer)
      this.cleanupTimer = null
    }
    if (this.channel) {
      this.channel.close()
      this.channel = null
    }

    this.sessions.clear()
    this.workflowId = null
    this.onConflict = null
  }

  /**
   * Update the workflow being edited (e.g., when switching workflows).
   */
  switchWorkflow(workflowId: string): void {
    if (this.workflowId === workflowId) return

    // Close the old workflow session
    if (this.workflowId && this.channel) {
      this.broadcast({
        type: 'close',
        sessionId: this.sessionId,
        workflowId: this.workflowId,
        timestamp: Date.now(),
      })
    }

    this.sessions.clear()
    this.workflowId = workflowId
    this.sendHeartbeat()
  }

  /**
   * Get currently active conflicting sessions.
   */
  getConflictingSessions(): readonly ActiveSession[] {
    if (!this.workflowId) return []
    const now = Date.now()
    const conflicts: ActiveSession[] = []
    for (const session of this.sessions.values()) {
      if (
        session.sessionId !== this.sessionId &&
        session.workflowId === this.workflowId &&
        now - session.lastSeen < SESSION_EXPIRY_MS
      ) {
        conflicts.push(session)
      }
    }
    return conflicts
  }

  // ---- Private ----

  private handleMessage(msg: SessionAnnouncement): void {
    // Ignore own announcements
    if (msg.sessionId === this.sessionId) return

    if (msg.type === 'close') {
      this.sessions.delete(msg.sessionId)
      this.notifyConflict()
      return
    }

    // Heartbeat
    this.sessions.set(msg.sessionId, {
      sessionId: msg.sessionId,
      workflowId: msg.workflowId,
      lastSeen: msg.timestamp,
    })

    this.notifyConflict()
  }

  private sendHeartbeat(): void {
    if (!this.workflowId) return
    this.broadcast({
      type: 'heartbeat',
      sessionId: this.sessionId,
      workflowId: this.workflowId,
      timestamp: Date.now(),
    })
  }

  private broadcast(msg: SessionAnnouncement): void {
    try {
      this.channel?.postMessage(msg)
    } catch {
      // Channel may be closed
    }
  }

  private cleanupExpiredSessions(): void {
    const now = Date.now()
    let changed = false
    for (const [id, session] of this.sessions.entries()) {
      if (now - session.lastSeen >= SESSION_EXPIRY_MS) {
        this.sessions.delete(id)
        changed = true
      }
    }
    if (changed) {
      this.notifyConflict()
    }
  }

  private notifyConflict(): void {
    const conflicts = this.getConflictingSessions()
    this.onConflict?.(conflicts)
  }
}

// ============================================================
// Singleton
// ============================================================

export const multiTabCoordinator = new MultiTabCoordinator()
