import { describe, it, expect } from 'vitest';
import {
  wanImageEditTemplate,
  WanImageEditConfigSchema,
  type WanImageEditConfig,
  type WanImageEditPayload,
} from './wan-image-edit';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('wanImageEdit Node Template', () => {
  const sampleImage: PortPayload = {
    value: {
      assetId: 'product-dress-001',
      role: 'foreground',
      placeholderUrl: 'placeholder://image/dress-001.jpg',
      localFileName: 'dress-001.jpg',
      resolution: '1024x1024',
      metadata: {
        prompt: 'Red summer dress product photo',
        seed: 42,
        stylePreset: 'default',
      },
    },
    status: 'success',
    schemaType: 'imageAsset',
  };

  const sampleEditPrompt: PortPayload = {
    value: 'Put this dress on a slim Asian virtual model, professional studio lighting',
    status: 'success',
    schemaType: 'text',
  };

  it('should have correct type and category', () => {
    expect(wanImageEditTemplate.type).toBe('wanImageEdit');
    expect(wanImageEditTemplate.category).toBe('visuals');
    expect(wanImageEditTemplate.title).toBe('Wan Image Edit');
    expect(wanImageEditTemplate.executable).toBe(true);
  });

  it('should define correct input port definitions', () => {
    expect(wanImageEditTemplate.inputs.map((p) => p.key)).toEqual([
      'image',
      'prompt',
    ]);
    expect(wanImageEditTemplate.inputs[0].dataType).toBe('imageAsset');
    expect(wanImageEditTemplate.inputs[0].required).toBe(true);
    expect(wanImageEditTemplate.inputs[1].dataType).toBe('text');
    expect(wanImageEditTemplate.inputs[1].required).toBe(true);
  });

  it('should define correct output port definitions', () => {
    expect(wanImageEditTemplate.outputs).toHaveLength(1);
    expect(wanImageEditTemplate.outputs[0].key).toBe('editedImage');
    expect(wanImageEditTemplate.outputs[0].dataType).toBe('imageAsset');
  });

  it('should have correct default config values', () => {
    const cfg = wanImageEditTemplate.defaultConfig;
    expect(cfg.provider).toBe('stub');
    expect(cfg.preserveProduct).toBe(true);
    expect(cfg.seed).toBe(0);
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: WanImageEditConfig = WanImageEditConfigSchema.parse({
        provider: 'stub',
        apiKey: 'test-key',
        model: 'wan-image-edit-default',
        preserveProduct: true,
        seed: 42,
      });
      expect(cfg.provider).toBe('stub');
      expect(cfg.preserveProduct).toBe(true);
      expect(cfg.seed).toBe(42);
    });

    it('should reject negative seed', () => {
      expect(() =>
        WanImageEditConfigSchema.parse({
          ...wanImageEditTemplate.defaultConfig,
          seed: -1,
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should return idle when image is missing', () => {
      const out = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.editedImage.status).toBe('idle');
      expect(out.editedImage.value).toBeNull();
      expect(out.editedImage.previewText).toContain('Waiting for source image');
    });

    it('should return idle when prompt is missing', () => {
      const out = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage },
      });
      expect(out.editedImage.status).toBe('idle');
      expect(out.editedImage.value).toBeNull();
      expect(out.editedImage.previewText).toContain('Waiting for edit instruction');
    });

    it('should produce edited image payload with image and prompt', () => {
      const out = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
      });
      expect(out.editedImage.status).toBe('ready');
      const img = out.editedImage.value as WanImageEditPayload;
      expect(img.placeholderUrl).toContain('placeholder://image/wan-image-edit/');
      expect(img.metadata.originalAssetId).toBe('product-dress-001');
      expect(img.metadata.editPrompt).toBe('Put this dress on a slim Asian virtual model, professional studio lighting');
      expect(img.metadata.preserveProduct).toBe(true);
    });

    it('should include previewText with edit details', () => {
      const out = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
      });
      expect(out.editedImage.previewText).toContain('wan-image-edit-default');
      expect(out.editedImage.previewText).toContain('preserve-product');
    });

    it('should be deterministic for same inputs', () => {
      const out1 = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
      });
      const out2 = wanImageEditTemplate.buildPreview({
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
      });
      const img1 = out1.editedImage.value as WanImageEditPayload;
      const img2 = out2.editedImage.value as WanImageEditPayload;
      expect(img1.placeholderUrl).toBe(img2.placeholderUrl);
      expect(img1.metadata.seed).toBe(img2.metadata.seed);
    });

    it('should handle preserveProduct=false config', () => {
      const cfg: WanImageEditConfig = { ...wanImageEditTemplate.defaultConfig, preserveProduct: false };
      const out = wanImageEditTemplate.buildPreview({
        config: cfg,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
      });
      const img = out.editedImage.value as WanImageEditPayload;
      expect(img.metadata.preserveProduct).toBe(false);
    });
  });

  describe('mockExecute', () => {
    it('should return error when image is missing', async () => {
      const out = await wanImageEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanImageEditTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedImage.status).toBe('error');
      expect(out.editedImage.errorMessage).toContain('source image');
    });

    it('should return error when prompt is missing', async () => {
      const out = await wanImageEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedImage.status).toBe('error');
      expect(out.editedImage.errorMessage).toContain('edit instruction');
    });

    it('should return success with edited image payload', async () => {
      const out = await wanImageEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: wanImageEditTemplate.defaultConfig,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.editedImage.status).toBe('success');
      const img = out.editedImage.value as WanImageEditPayload;
      expect(img.placeholderUrl).toContain('placeholder://image/wan-image-edit/');
      expect(img.metadata.originalAssetId).toBe('product-dress-001');
      expect(img.metadata.editPrompt).toContain('virtual model');
      expect(out.editedImage.producedAt).toBeDefined();
    });

    it('should throw when aborted', async () => {
      const controller = new AbortController();
      controller.abort();
      await expect(
        wanImageEditTemplate.mockExecute!({
          nodeId: 'n1',
          config: wanImageEditTemplate.defaultConfig,
          inputs: { image: sampleImage, prompt: sampleEditPrompt },
          signal: controller.signal,
          runId: 'run-1',
        }),
      ).rejects.toThrow('cancelled');
    });

    it('should handle preserveProduct=false in execution', async () => {
      const cfg: WanImageEditConfig = { ...wanImageEditTemplate.defaultConfig, preserveProduct: false };
      const out = await wanImageEditTemplate.mockExecute!({
        nodeId: 'n1',
        config: cfg,
        inputs: { image: sampleImage, prompt: sampleEditPrompt },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      const img = out.editedImage.value as WanImageEditPayload;
      expect(img.metadata.preserveProduct).toBe(false);
    });
  });

  describe('fixtures', () => {
    it('should have at least 3 fixtures', () => {
      expect(wanImageEditTemplate.fixtures.length).toBeGreaterThanOrEqual(3);
    });

    it('should have virtual-model fixture', () => {
      const f = wanImageEditTemplate.fixtures.find((fx) => fx.id === 'virtual-model');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Virtual Model Placement');
      expect(f!.config.preserveProduct).toBe(true);
    });

    it('should have background-swap fixture', () => {
      const f = wanImageEditTemplate.fixtures.find((fx) => fx.id === 'background-swap');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Background Replacement');
    });

    it('should have style-transfer fixture with preserveProduct=false', () => {
      const f = wanImageEditTemplate.fixtures.find((fx) => fx.id === 'style-transfer');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Style Transfer (Free Edit)');
      expect(f!.config.preserveProduct).toBe(false);
    });
  });
});
