import { describe, it, expect } from 'vitest';
import {
  wanVideoEditTemplate,
  WanVideoEditConfigSchema,
  type WanVideoEditConfig,
  type WanVideoEditPayload,
} from './wan-video-edit';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('wanVideoEdit Node Template', () => {
  const sampleSourceVideo: PortPayload = {
    value: {
      videoUrl: 'placeholder://video/r2v-output-001.mp4',
      durationSeconds: 5,
      aspectRatio: '9:16',
      resolution: '1080p',
      model: 'wan-r2v-default',
      seed: 42,
      prompt: 'Product showcase video',
      hasReferenceVideos: false,
      hasReferenceImages: true,
    },
    status: 'success',
    schemaType: 'videoAsset',
  };

  const sampleEditPrompt: PortPayload = {
    value: 'Warm golden hour color grade with soft highlights',
    status: 'success',
    schemaType: 'text',
  };

  it('should have correct type and category', () => {
    expect(wanVideoEditTemplate.type).toBe('wanVideoEdit');
    expect(wanVideoEditTemplate.category).toBe('video');
    expect(wanVideoEditTemplate.title).toBe('Wan Video Edit');
    expect(wanVideoEditTemplate.executable).toBe(true);
  });

  it('should define correct input port definitions', () => {
    expect(wanVideoEditTemplate.inputs.map((p) => p.key)).toEqual([
      'video',
      'prompt',
    ]);
    expect(wanVideoEditTemplate.inputs[0].dataType).toBe('videoAsset');
    expect(wanVideoEditTemplate.inputs[0].required).toBe(true);
    expect(wanVideoEditTemplate.inputs[1].dataType).toBe('text');
    expect(wanVideoEditTemplate.inputs[1].required).toBe(true);
  });

  it('should define correct output port definitions', () => {
    expect(wanVideoEditTemplate.outputs).toHaveLength(1);
    expect(wanVideoEditTemplate.outputs[0].key).toBe('editedVideo');
    expect(wanVideoEditTemplate.outputs[0].dataType).toBe('videoAsset');
  });

  it('should have correct default config values', () => {
    const cfg = wanVideoEditTemplate.defaultConfig;
    expect(cfg.provider).toBe('stub');
    expect(cfg.editMode).toBe('color-grade');
    expect(cfg.seed).toBe(0);
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: WanVideoEditConfig = WanVideoEditConfigSchema.parse({
        provider: 'stub',
        apiKey: 'test-key',
        model: 'wan-video-edit-default',
        editMode: 'color-grade',
        seed: 42,
      });
      expect(cfg.provider).toBe('stub');
      expect(cfg.editMode).toBe('color-grade');
      expect(cfg.seed).toBe(42);
    });

    it('should accept all valid edit modes', () => {
      const modes = ['color-grade', 'local-edit', 'global-edit', 'reshape', 'style-transfer'] as const;
      modes.forEach((mode) => {
        const cfg = WanVideoEditConfigSchema.parse({
          ...wanVideoEditTemplate.defaultConfig,
          editMode: mode,
        });
        expect(cfg.editMode).toBe(mode);
      });
    });

    it('should reject invalid edit mode', () => {
      expect(() =>
        WanVideoEditConfigSchema.parse({
          ...wanVideoEditTemplate.defaultConfig,
          editMode: 'invalid-mode',
        }),
      ).toThrow();
    });

    it('should reject negative seed', () => {
      expect(() =>
        WanVideoEditConfigSchema.parse({
          ...wanVideoEditTemplate.defaultConfig,
          seed: -1,
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should return idle when video is missing', () => {
      const out = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.editedVideo.status).toBe('idle');
      expect(out.editedVideo.value).toBeNull();
      expect(out.editedVideo.previewText).toContain('Waiting for source video');
    });

    it('should return idle when prompt is missing', () => {
      const out = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo },
      });
      expect(out.editedVideo.status).toBe('idle');
      expect(out.editedVideo.value).toBeNull();
      expect(out.editedVideo.previewText).toContain('Waiting for edit instruction');
    });

    it('should produce edited video payload with video and prompt', () => {
      const out = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
      });
      expect(out.editedVideo.status).toBe('ready');
      const v = out.editedVideo.value as WanVideoEditPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-video-edit/');
      expect(v.originalVideoUrl).toBe('placeholder://video/r2v-output-001.mp4');
      expect(v.editPrompt).toBe('Warm golden hour color grade with soft highlights');
      expect(v.editMode).toBe('color-grade');
    });

    it('should preserve original video properties', () => {
      const out = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
      });
      const v = out.editedVideo.value as WanVideoEditPayload;
      expect(v.durationSeconds).toBe(5);
      expect(v.aspectRatio).toBe('9:16');
      expect(v.resolution).toBe('1080p');
    });

    it('should include previewText with edit details', () => {
      const out = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
      });
      expect(out.editedVideo.previewText).toContain('color-grade');
      expect(out.editedVideo.previewText).toContain('wan-video-edit-default');
    });

    it('should be deterministic for same inputs', () => {
      const out1 = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
      });
      const out2 = wanVideoEditTemplate.buildPreview({
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
      });
      const v1 = out1.editedVideo.value as WanVideoEditPayload;
      const v2 = out2.editedVideo.value as WanVideoEditPayload;
      expect(v1.videoUrl).toBe(v2.videoUrl);
      expect(v1.seed).toBe(v2.seed);
    });
  });

  describe('mockExecute', () => {
    it('should return error when video is missing', async () => {
      const out = await wanVideoEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanVideoEditTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedVideo.status).toBe('error');
      expect(out.editedVideo.errorMessage).toContain('source video');
    });

    it('should return error when prompt is missing', async () => {
      const out = await wanVideoEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedVideo.status).toBe('error');
      expect(out.editedVideo.errorMessage).toContain('edit instruction');
    });

    it('should return success with edited video payload', async () => {
      const out = await wanVideoEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanVideoEditTemplate.defaultConfig,
        inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedVideo.status).toBe('success');
      const v = out.editedVideo.value as WanVideoEditPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-video-edit/');
      expect(v.originalVideoUrl).toBe('placeholder://video/r2v-output-001.mp4');
      expect(v.editMode).toBe('color-grade');
      expect(out.editedVideo.producedAt).toBeDefined();
    });

    it('should throw when aborted', async () => {
      const controller = new AbortController();
      controller.abort();
      await expect(
        wanVideoEditTemplate.mockExecute!({
          nodeId: 'n1',
          config: wanVideoEditTemplate.defaultConfig,
          inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
          signal: controller.signal,
          runId: 'run-1',
        }),
      ).rejects.toThrow('cancelled');
    });

    it('should handle different edit modes', async () => {
      const modes: WanVideoEditConfig['editMode'][] = ['local-edit', 'global-edit', 'reshape', 'style-transfer'];
      for (const mode of modes) {
        const cfg: WanVideoEditConfig = { ...wanVideoEditTemplate.defaultConfig, editMode: mode };
        const out = await wanVideoEditTemplate.mockExecute!({
          nodeId: 'n1',
          config: cfg,
          inputs: { video: sampleSourceVideo, prompt: sampleEditPrompt },
          signal: new AbortController().signal,
          runId: 'run-1',
        });
        const v = out.editedVideo.value as WanVideoEditPayload;
        expect(v.editMode).toBe(mode);
      }
    });
  });

  describe('fixtures', () => {
    it('should have at least 3 fixtures', () => {
      expect(wanVideoEditTemplate.fixtures.length).toBeGreaterThanOrEqual(3);
    });

    it('should have color-grade-warm fixture', () => {
      const f = wanVideoEditTemplate.fixtures.find((fx) => fx.id === 'color-grade-warm');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Warm Color Grading');
      expect(f!.config.editMode).toBe('color-grade');
    });

    it('should have local-edit-cleanup fixture', () => {
      const f = wanVideoEditTemplate.fixtures.find((fx) => fx.id === 'local-edit-cleanup');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Local Edit - Logo Removal');
      expect(f!.config.editMode).toBe('local-edit');
    });

    it('should have style-transfer-cinematic fixture', () => {
      const f = wanVideoEditTemplate.fixtures.find((fx) => fx.id === 'style-transfer-cinematic');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Cinematic Style Transfer');
      expect(f!.config.editMode).toBe('style-transfer');
    });
  });
});
