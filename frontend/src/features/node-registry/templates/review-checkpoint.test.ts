import { describe, it, expect } from 'vitest';
import {
  reviewCheckpointTemplate,
  ReviewCheckpointConfigSchema,
  type ReviewCheckpointConfig,
} from './review-checkpoint';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('reviewCheckpoint Node Template - AiModel-9wx.13', () => {
  const sampleScript: PortPayload = {
    value: {
      title: 'Test',
      hook: 'Hook line',
      beats: [{ timestamp: '0s', narration: 'Beat one', durationSeconds: 10 }],
      cta: 'Subscribe',
      totalDurationSeconds: 30,
    },
    status: 'success',
    schemaType: 'script',
  };

  it('should match plan metadata and ports', () => {
    expect(reviewCheckpointTemplate.type).toBe('reviewCheckpoint');
    expect(reviewCheckpointTemplate.category).toBe('utility');
    expect(reviewCheckpointTemplate.executable).toBe(true);
    expect(reviewCheckpointTemplate.inputs.map((p) => p.key)).toEqual([
      'script',
      'sceneList',
      'imageAssetList',
      'subtitleAsset',
      'videoAsset',
    ]);
    expect(reviewCheckpointTemplate.outputs.map((p) => p.key)).toEqual([
      'approvedScript',
      'approvedSceneList',
      'approvedImageAssetList',
      'approvedSubtitleAsset',
      'approvedVideoAsset',
      'reviewDecision',
    ]);
    expect(
      reviewCheckpointTemplate.outputs.find((o) => o.key === 'reviewDecision')?.required,
    ).toBe(true);
  });

  it('should validate config', () => {
    const cfg: ReviewCheckpointConfig = ReviewCheckpointConfigSchema.parse({
      reviewLabel: 'Gate',
      instructions: 'Check the payload.',
      blocking: false,
      reviewType: 'script',
    });
    expect(cfg.reviewType).toBe('script');
  });

  describe('buildPreview', () => {
    it('should return idle when active input missing', () => {
      const out = reviewCheckpointTemplate.buildPreview({
        config: { ...reviewCheckpointTemplate.defaultConfig, reviewType: 'script' },
        inputs: {},
      });
      expect(out.approvedScript.status).toBe('idle');
      expect(out.reviewDecision.status).toBe('idle');
    });

    it('should pass through script to approvedScript when reviewType matches', () => {
      const out = reviewCheckpointTemplate.buildPreview({
        config: { ...reviewCheckpointTemplate.defaultConfig, reviewType: 'script' },
        inputs: { script: sampleScript },
      });
      expect(out.approvedScript.status).toBe('ready');
      expect(out.approvedScript.value).toEqual(sampleScript.value);
      expect(out.reviewDecision.previewText).toContain('Pending');
    });
  });

  describe('mockExecute', () => {
    it('should auto-approve and emit reviewDecision', async () => {
      const out = await reviewCheckpointTemplate.mockExecute!({
        nodeId: 'n1',
        config: { ...reviewCheckpointTemplate.defaultConfig, reviewType: 'script' },
        inputs: { script: sampleScript },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.approvedScript.status).toBe('success');
      expect(out.reviewDecision.status).toBe('success');
      const decision = out.reviewDecision.value as { decision: string; reviewType: string };
      expect(decision.decision).toBe('approved');
      expect(decision.reviewType).toBe('script');
    });

    it('should error when required input missing for reviewType', async () => {
      const out = await reviewCheckpointTemplate.mockExecute!({
        nodeId: 'n1',
        config: { ...reviewCheckpointTemplate.defaultConfig, reviewType: 'script' },
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.approvedScript.status).toBe('error');
      expect(out.reviewDecision.status).toBe('error');
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = reviewCheckpointTemplate.mockExecute!({
        nodeId: 'n1',
        config: { ...reviewCheckpointTemplate.defaultConfig, reviewType: 'script' },
        inputs: { script: sampleScript },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(reviewCheckpointTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('fixtures should produce ready approved outputs in preview', () => {
      reviewCheckpointTemplate.fixtures.forEach((f) => {
        const merged = { ...reviewCheckpointTemplate.defaultConfig, ...f.config };
        expect(() => ReviewCheckpointConfigSchema.parse(merged)).not.toThrow();
        const result = reviewCheckpointTemplate.buildPreview({
          config: merged,
          inputs: f.previewInputs || {},
        });
        const approvedKey =
          f.config?.reviewType === 'sceneList'
            ? 'approvedSceneList'
            : 'approvedScript';
        expect(result[approvedKey].status).toBe('ready');
      });
    });
  });
});
