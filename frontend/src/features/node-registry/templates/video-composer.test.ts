import { describe, it, expect } from 'vitest';
import {
  videoComposerTemplate,
  VideoComposerConfigSchema,
  type VideoComposerConfig,
  type VideoAssetPayload,
} from './video-composer';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('videoComposer Node Template - AiModel-9wx.12', () => {
  const sampleVisualAssets: PortPayload = {
    value: {
      assets: [
        {
          assetId: 'asset-0-0',
          sceneIndex: 0,
          role: 'background',
          placeholderUrl: 'placeholder://image/1024x1024/seed-100/frame-0.png',
          localFileName: 'scene-0-asset.png',
          resolution: '1024x1024',
          metadata: { prompt: 'Mountain landscape', seed: 100, stylePreset: 'cinematic' },
        },
        {
          assetId: 'asset-1-1',
          sceneIndex: 1,
          role: 'foreground',
          placeholderUrl: 'placeholder://image/1024x1024/seed-200/frame-1.png',
          localFileName: 'scene-1-asset.png',
          resolution: '1024x1024',
          metadata: { prompt: 'Flowers', seed: 200, stylePreset: 'cinematic' },
        },
      ],
      count: 2,
      resolution: '1024x1024',
    },
    status: 'success',
    schemaType: 'imageAssetList',
  };

  const sampleAudioPlan: PortPayload = {
    value: {
      segments: [
        { segmentId: 'seg-0', text: 'Hello', startTimeSeconds: 0, durationSeconds: 2, wordCount: 1, pauseAfterSeconds: 0 },
      ],
      totalDurationSeconds: 12,
      totalWordCount: 1,
      voiceStyle: 'warm',
      pace: 'normal',
      estimatedWordsPerMinute: 150,
      placeholderUrl: 'placeholder://audio/x.mp3',
      generatedAt: '2026-01-01T00:00:00.000Z',
    },
    status: 'success',
    schemaType: 'audioPlan',
  };

  const sampleSubtitleAsset: PortPayload = {
    value: {
      cues: [{ index: 0, startTimeSeconds: 0, endTimeSeconds: 2, text: 'Hello' }],
      totalCues: 1,
      format: 'srt',
    },
    status: 'success',
    schemaType: 'subtitleAsset',
  };

  it('should match plan metadata and ports', () => {
    expect(videoComposerTemplate.type).toBe('videoComposer');
    expect(videoComposerTemplate.category).toBe('video');
    expect(videoComposerTemplate.executable).toBe(true);
    expect(videoComposerTemplate.inputs.map((p) => p.key)).toEqual([
      'visualAssets',
      'audioPlan',
      'subtitleAsset',
    ]);
    expect(videoComposerTemplate.inputs[0].required).toBe(true);
    expect(videoComposerTemplate.inputs[1].required).toBe(false);
    expect(videoComposerTemplate.inputs[2].required).toBe(false);
    expect(videoComposerTemplate.outputs[0].key).toBe('videoAsset');
    expect(videoComposerTemplate.outputs[0].dataType).toBe('videoAsset');
  });

  it('should validate config with Zod', () => {
    const cfg: VideoComposerConfig = VideoComposerConfigSchema.parse({
      aspectRatio: '16:9',
      transitionStyle: 'fade',
      fps: 30,
      includeTitleCard: true,
      musicBed: 'none',
    });
    expect(cfg.fps).toBe(30);
  });

  describe('buildPreview', () => {
    it('should return idle when visualAssets is missing', () => {
      const out = videoComposerTemplate.buildPreview({
        config: videoComposerTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.videoAsset.status).toBe('idle');
      expect(out.videoAsset.value).toBeNull();
    });

    it('should produce videoAsset with storyboard metadata', () => {
      const out = videoComposerTemplate.buildPreview({
        config: videoComposerTemplate.defaultConfig,
        inputs: { visualAssets: sampleVisualAssets },
      });
      expect(out.videoAsset.status).toBe('ready');
      const v = out.videoAsset.value as VideoAssetPayload;
      expect(v.timeline.length).toBeGreaterThan(0);
      expect(v.posterFrameUrl).toContain('placeholder://video/poster/');
      expect(v.storyboardPreview.frameCount).toBeGreaterThan(0);
      expect(v.storyboardPreview.frameDurationMs).toBeGreaterThan(0);
      expect(v.storyboardPreview.transitionStyle).toBe(
        videoComposerTemplate.defaultConfig.transitionStyle,
      );
      expect(v.hasAudio).toBe(false);
      expect(v.hasSubtitles).toBe(false);
    });

    it('should set hasAudio and hasSubtitles when optional inputs present', () => {
      const out = videoComposerTemplate.buildPreview({
        config: videoComposerTemplate.defaultConfig,
        inputs: {
          visualAssets: sampleVisualAssets,
          audioPlan: sampleAudioPlan,
          subtitleAsset: sampleSubtitleAsset,
        },
      });
      const v = out.videoAsset.value as VideoAssetPayload;
      expect(v.hasAudio).toBe(true);
      expect(v.hasSubtitles).toBe(true);
    });
  });

  describe('mockExecute', () => {
    it('should return success with visualAssets only', async () => {
      const out = await videoComposerTemplate.mockExecute!({
        nodeId: 'n1',
        config: videoComposerTemplate.defaultConfig,
        inputs: { visualAssets: sampleVisualAssets },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.videoAsset.status).toBe('success');
      expect((out.videoAsset.value as VideoAssetPayload).storyboardPreview.frameCount).toBeGreaterThan(0);
    });

    it('should return error when visualAssets missing', async () => {
      const out = await videoComposerTemplate.mockExecute!({
        nodeId: 'n1',
        config: videoComposerTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.videoAsset.status).toBe('error');
      expect(out.videoAsset.errorMessage).toBeDefined();
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = videoComposerTemplate.mockExecute!({
        nodeId: 'n1',
        config: videoComposerTemplate.defaultConfig,
        inputs: { visualAssets: sampleVisualAssets },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });

    it('should be deterministic for same inputs', async () => {
      const config = videoComposerTemplate.defaultConfig;
      const inputs = { visualAssets: sampleVisualAssets };
      const a = await videoComposerTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'r1',
      });
      const b = await videoComposerTemplate.mockExecute!({
        nodeId: 'n2',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'r2',
      });
      const va = a.videoAsset.value as VideoAssetPayload;
      const vb = b.videoAsset.value as VideoAssetPayload;
      expect(va.timeline).toEqual(vb.timeline);
      expect(va.totalDurationSeconds).toBe(vb.totalDurationSeconds);
      expect(va.posterFrameUrl).toBe(vb.posterFrameUrl);
      expect(va.storyboardPreview).toEqual(vb.storyboardPreview);
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(videoComposerTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have unique fixture IDs', () => {
      const ids = videoComposerTemplate.fixtures.map((f) => f.id);
      expect(new Set(ids).size).toBe(ids.length);
    });

    it('fixtures should produce ready videoAssets', () => {
      videoComposerTemplate.fixtures.forEach((f) => {
        const merged = { ...videoComposerTemplate.defaultConfig, ...f.config } as VideoComposerConfig;
        expect(() => VideoComposerConfigSchema.parse(merged)).not.toThrow();
        const result = videoComposerTemplate.buildPreview({
          config: merged,
          inputs: f.previewInputs || {},
        });
        expect(result.videoAsset.status).toBe('ready');
        expect(result.videoAsset.value).not.toBeNull();
      });
    });
  });
});
