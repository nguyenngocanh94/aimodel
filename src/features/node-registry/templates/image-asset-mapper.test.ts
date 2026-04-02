import { describe, it, expect } from 'vitest';
import {
  imageAssetMapperTemplate,
  ImageAssetMapperConfigSchema,
  type ImageAssetMapperConfig,
} from './image-asset-mapper';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('imageAssetMapper Node Template - AiModel-9wx.9', () => {
  const sampleFrameList: PortPayload = {
    value: {
      frames: [
        {
          frameId: 'frame-0-0',
          sceneIndex: 0,
          prompt: 'Mountain landscape',
          placeholderUrl: 'placeholder://image/1024x1024/seed-1/frame-0.png',
          resolution: '1024x1024',
          seed: 100,
          stylePreset: 'cinematic',
        },
        {
          frameId: 'frame-1-1',
          sceneIndex: 1,
          prompt: 'Close-up flowers',
          placeholderUrl: 'placeholder://image/1024x1024/seed-2/frame-1.png',
          resolution: '1024x1024',
          seed: 200,
          stylePreset: 'cinematic',
        },
        {
          frameId: 'frame-2-2',
          sceneIndex: 2,
          prompt: 'Valley view',
          placeholderUrl: 'placeholder://image/1024x1024/seed-3/frame-2.png',
          resolution: '1024x1024',
          seed: 300,
          stylePreset: 'cinematic',
        },
      ],
      count: 3,
      resolution: '1024x1024',
    },
    status: 'success',
    schemaType: 'imageFrameList',
  };

  it('should match plan metadata and ports', () => {
    expect(imageAssetMapperTemplate.type).toBe('imageAssetMapper');
    expect(imageAssetMapperTemplate.category).toBe('utility');
    expect(imageAssetMapperTemplate.executable).toBe(false);
    expect(imageAssetMapperTemplate.mockExecute).toBeUndefined();
    expect(imageAssetMapperTemplate.inputs).toHaveLength(1);
    expect(imageAssetMapperTemplate.inputs[0].key).toBe('imageFrameList');
    expect(imageAssetMapperTemplate.inputs[0].dataType).toBe('imageFrameList');
    expect(imageAssetMapperTemplate.outputs).toHaveLength(1);
    expect(imageAssetMapperTemplate.outputs[0].key).toBe('imageAssetList');
    expect(imageAssetMapperTemplate.outputs[0].dataType).toBe('imageAssetList');
  });

  it('should validate config with Zod', () => {
    const cfg: ImageAssetMapperConfig = ImageAssetMapperConfigSchema.parse({
      assetRole: 'auto',
      namingPattern: 'layer-{index}',
    });
    expect(cfg.assetRole).toBe('auto');
    expect(cfg.namingPattern).toBe('layer-{index}');
  });

  it('should reject empty namingPattern', () => {
    const result = ImageAssetMapperConfigSchema.safeParse({
      assetRole: 'background',
      namingPattern: '',
    });
    expect(result.success).toBe(false);
  });

  it('buildPreview should emit idle when imageFrameList is missing', () => {
    const out = imageAssetMapperTemplate.buildPreview({
      config: imageAssetMapperTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.imageAssetList.status).toBe('idle');
    expect(out.imageAssetList.value).toBeNull();
  });

  it('buildPreview should map frames to imageAssetList', () => {
    const out = imageAssetMapperTemplate.buildPreview({
      config: imageAssetMapperTemplate.defaultConfig,
      inputs: { imageFrameList: sampleFrameList },
    });
    expect(out.imageAssetList.status).toBe('ready');
    expect(out.imageAssetList.schemaType).toBe('imageAssetList');
    const v = out.imageAssetList.value as {
      assets: readonly { sceneIndex: number; localFileName: string; metadata: { seed: number } }[];
      count: number;
      resolution: string;
    };
    expect(v.count).toBe(3);
    expect(v.assets.length).toBe(3);
    expect(v.resolution).toBe('1024x1024');
    expect(v.assets[0].localFileName).toBe('scene-0-asset.png');
    expect(v.assets[0].metadata.seed).toBe(100);
  });

  it('buildPreview should apply fixed assetRole when not auto', () => {
    const out = imageAssetMapperTemplate.buildPreview({
      config: { ...imageAssetMapperTemplate.defaultConfig, assetRole: 'overlay' },
      inputs: { imageFrameList: sampleFrameList },
    });
    const v = out.imageAssetList.value as { assets: readonly { role: string }[] };
    v.assets.forEach((a) => {
      expect(a.role).toBe('overlay');
    });
  });

  it('buildPreview should be deterministic for same inputs', () => {
    const a = imageAssetMapperTemplate.buildPreview({
      config: imageAssetMapperTemplate.defaultConfig,
      inputs: { imageFrameList: sampleFrameList },
    });
    const b = imageAssetMapperTemplate.buildPreview({
      config: imageAssetMapperTemplate.defaultConfig,
      inputs: { imageFrameList: sampleFrameList },
    });
    expect(a.imageAssetList.value).toEqual(b.imageAssetList.value);
  });

  it('should have at least two fixtures with valid merged configs', () => {
    expect(imageAssetMapperTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    imageAssetMapperTemplate.fixtures.forEach((f) => {
      const merged = { ...imageAssetMapperTemplate.defaultConfig, ...f.config };
      expect(() => ImageAssetMapperConfigSchema.parse(merged)).not.toThrow();
    });
  });

  it('fixtures should produce ready imageAssetList', () => {
    imageAssetMapperTemplate.fixtures.forEach((f) => {
      const result = imageAssetMapperTemplate.buildPreview({
        config: { ...imageAssetMapperTemplate.defaultConfig, ...f.config },
        inputs: f.previewInputs || {},
      });
      expect(result.imageAssetList.status).toBe('ready');
      expect(result.imageAssetList.value).not.toBeNull();
    });
  });

  it('fixtures should have unique IDs', () => {
    const ids = imageAssetMapperTemplate.fixtures.map((f) => f.id);
    expect(new Set(ids).size).toBe(ids.length);
  });
});
