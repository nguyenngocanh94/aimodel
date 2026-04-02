import { describe, it, expect, beforeEach } from 'vitest';
import { ReviewHandler } from './review-handler';

describe('ReviewHandler', () => {
  let handler: ReviewHandler;

  beforeEach(() => {
    handler = new ReviewHandler();
  });

  it('should start with no pending review', () => {
    expect(handler.hasPendingReview).toBe(false);
    expect(handler.pendingNodeId).toBeNull();
  });

  it('should track pending review after requestReview', () => {
    handler.requestReview('node-1', {});
    expect(handler.hasPendingReview).toBe(true);
    expect(handler.pendingNodeId).toBe('node-1');
  });

  it('should resolve with approved decision on approve', async () => {
    const promise = handler.requestReview('node-1', {});
    handler.approve('Looks good');
    const result = await promise;
    expect(result.decision).toBe('approved');
    expect(result.reviewerNotes).toBe('Looks good');
    expect(result.reviewedAt).toBeDefined();
    expect(handler.hasPendingReview).toBe(false);
  });

  it('should resolve with rejected decision on reject', async () => {
    const promise = handler.requestReview('node-1', {});
    handler.reject('Needs changes');
    const result = await promise;
    expect(result.decision).toBe('rejected');
    expect(result.reviewerNotes).toBe('Needs changes');
  });

  it('should resolve with rejected decision on cancel', async () => {
    const promise = handler.requestReview('node-1', {});
    handler.cancel();
    const result = await promise;
    expect(result.decision).toBe('rejected');
    expect(result.reviewerNotes).toContain('cancelled');
  });

  it('should no-op approve when no pending review', () => {
    handler.approve(); // should not throw
    expect(handler.hasPendingReview).toBe(false);
  });

  it('should expose pending input payloads', () => {
    const inputs = { script: { value: 'test', status: 'success' as const, schemaType: 'script' as const } };
    handler.requestReview('node-1', inputs);
    expect(handler.pendingInputPayloads).toBe(inputs);
  });

  it('should reset state', () => {
    handler.requestReview('node-1', {});
    handler.reset();
    expect(handler.hasPendingReview).toBe(false);
  });
});
