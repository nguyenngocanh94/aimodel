/**
 * ReviewHandler - AiModel-ecs.7
 * Manages the human-in-the-loop review flow for reviewCheckpoint nodes.
 * Per plan section 11.10
 */

import type { PortPayload } from '@/features/workflows/domain/workflow-types';

export type ReviewDecision = 'approved' | 'rejected';

export interface ReviewResult {
  readonly decision: ReviewDecision;
  readonly reviewerNotes: string;
  readonly reviewedAt: string;
}

interface PendingReview {
  readonly nodeId: string;
  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
  readonly resolve: (result: ReviewResult) => void;
}

/**
 * ReviewHandler manages pending review requests.
 * When a blocking reviewCheckpoint is reached, the executor calls `requestReview()`
 * which returns a promise that resolves when the user approves or rejects.
 */
export class ReviewHandler {
  private pendingReview: PendingReview | null = null;

  /** Check if there is a pending review. */
  get hasPendingReview(): boolean {
    return this.pendingReview !== null;
  }

  /** Get the node ID of the pending review, if any. */
  get pendingNodeId(): string | null {
    return this.pendingReview?.nodeId ?? null;
  }

  /** Get the input payloads of the pending review. */
  get pendingInputPayloads(): Readonly<Record<string, PortPayload>> | null {
    return this.pendingReview?.inputPayloads ?? null;
  }

  /**
   * Request a review decision from the user.
   * Called by the executor when a blocking reviewCheckpoint is reached.
   * Returns a promise that resolves when the user acts.
   */
  requestReview(
    nodeId: string,
    inputPayloads: Readonly<Record<string, PortPayload>>,
  ): Promise<ReviewResult> {
    return new Promise<ReviewResult>((resolve) => {
      this.pendingReview = { nodeId, inputPayloads, resolve };
    });
  }

  /** Approve the current review with optional notes. */
  approve(notes = ''): void {
    if (!this.pendingReview) return;
    this.pendingReview.resolve({
      decision: 'approved',
      reviewerNotes: notes,
      reviewedAt: new Date().toISOString(),
    });
    this.pendingReview = null;
  }

  /** Reject the current review with optional notes. */
  reject(notes = ''): void {
    if (!this.pendingReview) return;
    this.pendingReview.resolve({
      decision: 'rejected',
      reviewerNotes: notes,
      reviewedAt: new Date().toISOString(),
    });
    this.pendingReview = null;
  }

  /** Cancel any pending review (e.g. on run abort). */
  cancel(): void {
    if (!this.pendingReview) return;
    this.pendingReview.resolve({
      decision: 'rejected',
      reviewerNotes: 'Review cancelled due to run cancellation',
      reviewedAt: new Date().toISOString(),
    });
    this.pendingReview = null;
  }

  /** Reset state. */
  reset(): void {
    this.pendingReview = null;
  }
}

/** Singleton review handler for the app. */
export const reviewHandler = new ReviewHandler();
