import { describe, it, expect } from 'vitest';
import {
  wanR2VTemplate,
  WanR2VConfigSchema,
  type WanR2VConfig,
  type WanR2VVideoPayload,
} from './wan-r2v';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('wanR2V Node Template', () => {
  const samplePrompt: PortPayload = {
    value: 'A dynamic product showcase video',
    status: 'success',
    schemaType: 'text',
  };

  const sampleReferenceVideos: PortPayload = {
    value: ['https://example.com/ref1.mp4', 'https://example.com/ref2.mp4'],
    status: 'success',
    schemaType: 'videoUrlList',
  };

  const sampleReferenceImages: PortPayload = {
    value: {
      assets: [
        {
          assetId: 'img-0',
          sceneIndex: 0,
          role: 'reference',
          placeholderUrl: 'placeholder://image/1024x1024/seed-100/ref-0.png',
          localFileName: 'ref-0.png',
          resolution: '1024x1024',
          metadata: { prompt: 'Product shot', seed: 100, stylePreset: 'photo' },
        },
      ],
      count: 1,
      resolution: '1024x1024',
    },
    status: 'success',
    schemaType: 'imageAssetList',
  };

  it('should have correct type and category', () => {
    expect(wanR2VTemplate.type).toBe('wanR2V');
    expect(wanR2VTemplate.category).toBe('video');
    expect(wanR2VTemplate.title).toBe('Wan R2V');
    expect(wanR2VTemplate.executable).toBe(true);
  });

  it('should define correct input port definitions', () => {
    expect(wanR2VTemplate.inputs.map((p) => p.key)).toEqual([
      'prompt',
      'referenceVideos',
      'referenceImages',
    ]);
    expect(wanR2VTemplate.inputs[0].dataType).toBe('text');
    expect(wanR2VTemplate.inputs[0].required).toBe(true);
    expect(wanR2VTemplate.inputs[1].dataType).toBe('videoUrlList');
    expect(wanR2VTemplate.inputs[1].required).toBe(false);
    expect(wanR2VTemplate.inputs[2].dataType).toBe('imageAssetList');
    expect(wanR2VTemplate.inputs[2].required).toBe(false);
  });

  it('should define correct output port definitions', () => {
    expect(wanR2VTemplate.outputs).toHaveLength(1);
    expect(wanR2VTemplate.outputs[0].key).toBe('video');
    expect(wanR2VTemplate.outputs[0].dataType).toBe('videoAsset');
  });

  it('should have correct default config values', () => {
    const cfg = wanR2VTemplate.defaultConfig;
    expect(cfg.provider).toBe('stub');
    expect(cfg.aspectRatio).toBe('9:16');
    expect(cfg.resolution).toBe('1080p');
    expect(cfg.duration).toBe('5');
    expect(cfg.multiShots).toBe(false);
    expect(cfg.seed).toBe(0);
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: WanR2VConfig = WanR2VConfigSchema.parse({
        provider: 'stub',
        apiKey: 'test-key',
        model: 'wan-r2v-default',
        aspectRatio: '9:16',
        resolution: '1080p',
        duration: '5',
        multiShots: false,
        seed: 42,
      });
      expect(cfg.provider).toBe('stub');
      expect(cfg.seed).toBe(42);
    });

    it('should reject invalid aspect ratio', () => {
      expect(() =>
        WanR2VConfigSchema.parse({
          ...wanR2VTemplate.defaultConfig,
          aspectRatio: '3:2',
        }),
      ).toThrow();
    });

    it('should reject invalid resolution', () => {
      expect(() =>
        WanR2VConfigSchema.parse({
          ...wanR2VTemplate.defaultConfig,
          resolution: '480p',
        }),
      ).toThrow();
    });

    it('should reject invalid duration', () => {
      expect(() =>
        WanR2VConfigSchema.parse({
          ...wanR2VTemplate.defaultConfig,
          duration: '7',
        }),
      ).toThrow();
    });

    it('should reject negative seed', () => {
      expect(() =>
        WanR2VConfigSchema.parse({
          ...wanR2VTemplate.defaultConfig,
          seed: -1,
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should return idle when prompt is missing', () => {
      const out = wanR2VTemplate.buildPreview({
        config: wanR2VTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.video.status).toBe('idle');
      expect(out.video.value).toBeNull();
    });

    it('should produce video payload with prompt only', () => {
      const out = wanR2VTemplate.buildPreview({
        config: wanR2VTemplate.defaultConfig,
        inputs: { prompt: samplePrompt },
      });
      expect(out.video.status).toBe('ready');
      const v = out.video.value as WanR2VVideoPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-r2v/');
      expect(v.durationSeconds).toBe(5);
      expect(v.aspectRatio).toBe('9:16');
      expect(v.resolution).toBe('1080p');
      expect(v.hasReferenceVideos).toBe(false);
      expect(v.hasReferenceImages).toBe(false);
    });

    it('should reflect reference inputs in payload', () => {
      const out = wanR2VTemplate.buildPreview({
        config: wanR2VTemplate.defaultConfig,
        inputs: {
          prompt: samplePrompt,
          referenceVideos: sampleReferenceVideos,
          referenceImages: sampleReferenceImages,
        },
      });
      const v = out.video.value as WanR2VVideoPayload;
      expect(v.hasReferenceVideos).toBe(true);
      expect(v.hasReferenceImages).toBe(true);
    });
  });

  describe('mockExecute', () => {
    it('should return success with prompt', async () => {
      const out = await wanR2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanR2VTemplate.defaultConfig,
        inputs: { prompt: samplePrompt },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.video.status).toBe('success');
      const v = out.video.value as WanR2VVideoPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-r2v/');
    });

    it('should return error when prompt is missing', async () => {
      const out = await wanR2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanR2VTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.video.status).toBe('error');
      expect(out.video.errorMessage).toBeDefined();
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = wanR2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanR2VTemplate.defaultConfig,
        inputs: { prompt: samplePrompt },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });

    it('should be deterministic for same inputs', async () => {
      const config = wanR2VTemplate.defaultConfig;
      const inputs = { prompt: samplePrompt };
      const a = await wanR2VTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'r1',
      });
      const b = await wanR2VTemplate.mockExecute!({
        nodeId: 'n2',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'r2',
      });
      const va = a.video.value as WanR2VVideoPayload;
      const vb = b.video.value as WanR2VVideoPayload;
      expect(va.videoUrl).toBe(vb.videoUrl);
      expect(va.durationSeconds).toBe(vb.durationSeconds);
    });
  });

  describe('Fixtures', () => {
    it('should have at least one fixture', () => {
      expect(wanR2VTemplate.fixtures.length).toBeGreaterThanOrEqual(1);
    });

    it('should have unique fixture IDs', () => {
      const ids = wanR2VTemplate.fixtures.map((f) => f.id);
      expect(new Set(ids).size).toBe(ids.length);
    });

    it('should include the TikTok TVC Demo fixture', () => {
      const fixture = wanR2VTemplate.fixtures.find((f) => f.id === 'tiktok-tvc-demo');
      expect(fixture).toBeDefined();
      expect(fixture!.label).toBe('TikTok TVC Demo');
    });

    it('fixtures should produce ready video output', () => {
      wanR2VTemplate.fixtures.forEach((f) => {
        const merged = { ...wanR2VTemplate.defaultConfig, ...f.config } as WanR2VConfig;
        expect(() => WanR2VConfigSchema.parse(merged)).not.toThrow();
        const result = wanR2VTemplate.buildPreview({
          config: merged,
          inputs: f.previewInputs || {},
        });
        expect(result.video.status).toBe('ready');
        expect(result.video.value).not.toBeNull();
      });
    });
  });
});
