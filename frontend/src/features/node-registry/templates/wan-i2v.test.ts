import { describe, it, expect } from 'vitest';
import {
  wanI2VTemplate,
  WanI2VConfigSchema,
  type WanI2VConfig,
  type WanI2VVideoPayload,
} from './wan-i2v';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('wanI2V Node Template', () => {
  const sampleImage: PortPayload = {
    value: {
      assetId: 'product-photo-001',
      role: 'background',
      placeholderUrl: 'placeholder://image/product-001.jpg',
      localFileName: 'product-001.jpg',
      resolution: '1080x1920',
      metadata: {
        prompt: 'Product photo on white background',
        seed: 42,
        stylePreset: 'default',
      },
    },
    status: 'success',
    schemaType: 'imageAsset',
  };

  const sampleMotionPrompt: PortPayload = {
    value: 'Gentle 360 rotation with soft lighting reveal',
    status: 'success',
    schemaType: 'text',
  };

  it('should have correct type and category', () => {
    expect(wanI2VTemplate.type).toBe('wanI2V');
    expect(wanI2VTemplate.category).toBe('video');
    expect(wanI2VTemplate.title).toBe('Wan I2V');
    expect(wanI2VTemplate.executable).toBe(true);
  });

  it('should define correct input port definitions', () => {
    expect(wanI2VTemplate.inputs.map((p) => p.key)).toEqual([
      'image',
      'prompt',
    ]);
    expect(wanI2VTemplate.inputs[0].dataType).toBe('imageAsset');
    expect(wanI2VTemplate.inputs[0].required).toBe(true);
    expect(wanI2VTemplate.inputs[1].dataType).toBe('text');
    expect(wanI2VTemplate.inputs[1].required).toBe(false);
  });

  it('should define correct output port definitions', () => {
    expect(wanI2VTemplate.outputs).toHaveLength(1);
    expect(wanI2VTemplate.outputs[0].key).toBe('video');
    expect(wanI2VTemplate.outputs[0].dataType).toBe('videoAsset');
  });

  it('should have correct default config values', () => {
    const cfg = wanI2VTemplate.defaultConfig;
    expect(cfg.provider).toBe('stub');
    expect(cfg.aspectRatio).toBe('9:16');
    expect(cfg.resolution).toBe('1080p');
    expect(cfg.duration).toBe('5');
    expect(cfg.seed).toBe(0);
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: WanI2VConfig = WanI2VConfigSchema.parse({
        provider: 'stub',
        apiKey: 'test-key',
        model: 'wan-i2v-default',
        aspectRatio: '9:16',
        resolution: '1080p',
        duration: '5',
        seed: 42,
      });
      expect(cfg.provider).toBe('stub');
      expect(cfg.seed).toBe(42);
    });

    it('should reject invalid aspect ratio', () => {
      expect(() =>
        WanI2VConfigSchema.parse({
          ...wanI2VTemplate.defaultConfig,
          aspectRatio: '3:2',
        }),
      ).toThrow();
    });

    it('should reject invalid resolution', () => {
      expect(() =>
        WanI2VConfigSchema.parse({
          ...wanI2VTemplate.defaultConfig,
          resolution: '480p',
        }),
      ).toThrow();
    });

    it('should reject invalid duration', () => {
      expect(() =>
        WanI2VConfigSchema.parse({
          ...wanI2VTemplate.defaultConfig,
          duration: '7',
        }),
      ).toThrow();
    });

    it('should reject negative seed', () => {
      expect(() =>
        WanI2VConfigSchema.parse({
          ...wanI2VTemplate.defaultConfig,
          seed: -1,
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should return idle when image is missing', () => {
      const out = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.video.status).toBe('idle');
      expect(out.video.value).toBeNull();
    });

    it('should produce video payload with image only', () => {
      const out = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage },
      });
      expect(out.video.status).toBe('ready');
      const v = out.video.value as WanI2VVideoPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-i2v/');
      expect(v.durationSeconds).toBe(5);
      expect(v.aspectRatio).toBe('9:16');
      expect(v.sourceImageAssetId).toBe('product-photo-001');
      expect(v.motionPrompt).toBe('default animation');
    });

    it('should produce video payload with image and prompt', () => {
      const out = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleMotionPrompt },
      });
      expect(out.video.status).toBe('ready');
      const v = out.video.value as WanI2VVideoPayload;
      expect(v.sourceImageAssetId).toBe('product-photo-001');
      expect(v.motionPrompt).toBe('Gentle 360 rotation with soft lighting reveal');
    });

    it('should include previewText with video details', () => {
      const out = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleMotionPrompt },
      });
      expect(out.video.previewText).toContain('5s');
      expect(out.video.previewText).toContain('9:16');
      expect(out.video.previewText).toContain('wan-i2v-default');
      expect(out.video.previewText).toContain('custom-motion');
    });

    it('should be deterministic for same inputs', () => {
      const out1 = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleMotionPrompt },
      });
      const out2 = wanI2VTemplate.buildPreview({
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleMotionPrompt },
      });
      const v1 = out1.video.value as WanI2VVideoPayload;
      const v2 = out2.video.value as WanI2VVideoPayload;
      expect(v1.videoUrl).toBe(v2.videoUrl);
      expect(v1.seed).toBe(v2.seed);
    });
  });

  describe('mockExecute', () => {
    it('should return error when image is missing', async () => {
      const out = await wanI2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanI2VTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.video.status).toBe('error');
      expect(out.video.errorMessage).toContain('source image');
    });

    it('should return success with video payload', async () => {
      const out = await wanI2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanI2VTemplate.defaultConfig,
        inputs: { image: sampleImage },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.video.status).toBe('success');
      const v = out.video.value as WanI2VVideoPayload;
      expect(v.videoUrl).toContain('placeholder://video/wan-i2v/');
      expect(v.durationSeconds).toBe(5);
      expect(v.sourceImageAssetId).toBe('product-photo-001');
      expect(out.video.producedAt).toBeDefined();
    });

    it('should throw when aborted', async () => {
      const controller = new AbortController();
      controller.abort();
      await expect(
        wanI2VTemplate.mockExecute!({
          nodeId: 'n1',
          config: wanI2VTemplate.defaultConfig,
          inputs: { image: sampleImage },
          signal: controller.signal,
          runId: 'run-1',
        }),
      ).rejects.toThrow('cancelled');
    });

    it('should handle custom duration config', async () => {
      const cfg: WanI2VConfig = { ...wanI2VTemplate.defaultConfig, duration: '10' };
      const out = await wanI2VTemplate.mockExecute!({
        nodeId: 'n1',
        config: cfg,
        inputs: { image: sampleImage },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      const v = out.video.value as WanI2VVideoPayload;
      expect(v.durationSeconds).toBe(10);
    });
  });

  describe('fixtures', () => {
    it('should have at least 2 fixtures', () => {
      expect(wanI2VTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have product-unboxing fixture', () => {
      const f = wanI2VTemplate.fixtures.find((fx) => fx.id === 'product-unboxing');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Product Unboxing Animation');
      expect(f!.config.seed).toBe(42);
    });

    it('should have product-showcase fixture', () => {
      const f = wanI2VTemplate.fixtures.find((fx) => fx.id === 'product-showcase');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Product Showcase Close-up');
      expect(f!.config.duration).toBe('3');
    });
  });
});
