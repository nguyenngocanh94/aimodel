import { describe, it, expect } from 'vitest';
import {
  imageGeneratorTemplate,
  ImageGeneratorConfigSchema,
} from './image-generator';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('imageGenerator Node Template - AiModel-9wx.8', () => {
  const sampleSceneList: PortPayload = {
    value: {
      scenes: [
        { sequenceIndex: 0, visualDescription: 'Mountain landscape at sunrise', durationSeconds: 15 },
        { sequenceIndex: 1, visualDescription: 'Close-up of mountain flowers', durationSeconds: 10 },
        { sequenceIndex: 2, visualDescription: 'Wide valley view', durationSeconds: 20 },
      ],
    },
    status: 'success',
    schemaType: 'sceneList',
  };

  const samplePromptList: PortPayload = {
    value: {
      prompts: [
        { sceneIndex: 0, prompt: 'Photorealistic mountain at sunrise', aspectRatio: '16:9' },
        { sceneIndex: 1, prompt: 'Detailed macro shot of alpine flowers', aspectRatio: '16:9' },
        { sceneIndex: 2, prompt: 'Panoramic valley vista', aspectRatio: '16:9' },
      ],
      count: 3,
      visualStyle: 'photorealistic',
      aspectRatio: '16:9',
      generatedAt: new Date().toISOString(),
    },
    status: 'success',
    schemaType: 'promptList',
  };

  it('should match plan metadata and category', () => {
    expect(imageGeneratorTemplate.type).toBe('imageGenerator');
    expect(imageGeneratorTemplate.category).toBe('visuals');
    expect(imageGeneratorTemplate.executable).toBe(true);
  });

  it('should have both input ports (sceneList and promptList)', () => {
    const inputKeys = imageGeneratorTemplate.inputs.map(p => p.key);
    expect(inputKeys).toContain('sceneList');
    expect(inputKeys).toContain('promptList');
    expect(imageGeneratorTemplate.inputs).toHaveLength(2);
  });

  it('should have both output ports (imageFrameList and imageAssetList)', () => {
    const outputKeys = imageGeneratorTemplate.outputs.map(p => p.key);
    expect(outputKeys).toContain('imageFrameList');
    expect(outputKeys).toContain('imageAssetList');
    expect(imageGeneratorTemplate.outputs).toHaveLength(2);
  });

  describe('Config Schema', () => {
    it('should validate valid configuration', () => {
      const validConfig = {
        inputMode: 'scenes',
        outputMode: 'frames',
        stylePreset: 'cinematic',
        resolution: '1024x1024',
        seedStrategy: 'deterministic',
      };
      const result = ImageGeneratorConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should accept all inputMode variants', () => {
      ['scenes', 'prompts'].forEach(mode => {
        const result = ImageGeneratorConfigSchema.safeParse({
          inputMode: mode,
          outputMode: 'frames',
          stylePreset: 'cinematic',
          resolution: '1024x1024',
          seedStrategy: 'deterministic',
        });
        expect(result.success).toBe(true);
      });
    });

    it('should accept all outputMode variants', () => {
      ['frames', 'assets'].forEach(mode => {
        const result = ImageGeneratorConfigSchema.safeParse({
          inputMode: 'scenes',
          outputMode: mode,
          stylePreset: 'cinematic',
          resolution: '1024x1024',
          seedStrategy: 'deterministic',
        });
        expect(result.success).toBe(true);
      });
    });

    it('should accept all stylePreset variants', () => {
      ['default', 'cinematic', 'vivid', 'subdued'].forEach(preset => {
        const result = ImageGeneratorConfigSchema.safeParse({
          inputMode: 'scenes',
          outputMode: 'frames',
          stylePreset: preset,
          resolution: '1024x1024',
          seedStrategy: 'deterministic',
        });
        expect(result.success).toBe(true);
      });
    });

    it('should accept all resolution variants', () => {
      ['512x512', '1024x1024', '1024x1536', '1536x1024'].forEach(res => {
        const result = ImageGeneratorConfigSchema.safeParse({
          inputMode: 'scenes',
          outputMode: 'frames',
          stylePreset: 'cinematic',
          resolution: res,
          seedStrategy: 'deterministic',
        });
        expect(result.success).toBe(true);
      });
    });

    it('should reject invalid inputMode', () => {
      const result = ImageGeneratorConfigSchema.safeParse({
        inputMode: 'invalid',
        outputMode: 'frames',
        stylePreset: 'cinematic',
        resolution: '1024x1024',
        seedStrategy: 'deterministic',
      });
      expect(result.success).toBe(false);
    });

    it('should reject invalid resolution', () => {
      const result = ImageGeneratorConfigSchema.safeParse({
        inputMode: 'scenes',
        outputMode: 'frames',
        stylePreset: 'cinematic',
        resolution: '1920x1080',
        seedStrategy: 'deterministic',
      });
      expect(result.success).toBe(false);
    });
  });

  describe('buildPreview with scenes input', () => {
    it('should produce imageFrameList when inputMode=scenes and outputMode=frames', () => {
      const result = imageGeneratorTemplate.buildPreview({
        config: { ...imageGeneratorTemplate.defaultConfig, inputMode: 'scenes', outputMode: 'frames' },
        inputs: { sceneList: sampleSceneList },
      });
      expect(result.imageFrameList).toBeDefined();
      expect(result.imageFrameList.status).toBe('ready');
      expect(result.imageFrameList.schemaType).toBe('imageFrameList');
    });

    it('should produce imageAssetList when inputMode=scenes and outputMode=assets', () => {
      const result = imageGeneratorTemplate.buildPreview({
        config: { ...imageGeneratorTemplate.defaultConfig, inputMode: 'scenes', outputMode: 'assets' },
        inputs: { sceneList: sampleSceneList },
      });
      expect(result.imageAssetList).toBeDefined();
      expect(result.imageAssetList.status).toBe('ready');
      expect(result.imageAssetList.schemaType).toBe('imageAssetList');
    });

    it('should return idle when required input is missing', () => {
      const result = imageGeneratorTemplate.buildPreview({
        config: { ...imageGeneratorTemplate.defaultConfig, inputMode: 'scenes', outputMode: 'frames' },
        inputs: {},
      });
      expect(result.imageFrameList.status).toBe('idle');
      expect(result.imageFrameList.value).toBeNull();
    });
  });

  describe('buildPreview with prompts input', () => {
    it('should produce imageFrameList when inputMode=prompts and outputMode=frames', () => {
      const result = imageGeneratorTemplate.buildPreview({
        config: { ...imageGeneratorTemplate.defaultConfig, inputMode: 'prompts', outputMode: 'frames' },
        inputs: { promptList: samplePromptList },
      });
      expect(result.imageFrameList).toBeDefined();
      expect(result.imageFrameList.status).toBe('ready');
    });

    it('should produce imageAssetList when inputMode=prompts and outputMode=assets', () => {
      const result = imageGeneratorTemplate.buildPreview({
        config: { ...imageGeneratorTemplate.defaultConfig, inputMode: 'prompts', outputMode: 'assets' },
        inputs: { promptList: samplePromptList },
      });
      expect(result.imageAssetList).toBeDefined();
      expect(result.imageAssetList.status).toBe('ready');
    });
  });

  it('should generate correct number of outputs matching input count', () => {
    const result = imageGeneratorTemplate.buildPreview({
      config: imageGeneratorTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneList },
    });
    const frameList = result.imageFrameList.value as { frames: readonly unknown[]; count: number };
    expect(frameList.count).toBe(3);
    expect(frameList.frames.length).toBe(3);
  });

  it('should include resolution in output metadata', () => {
    const result = imageGeneratorTemplate.buildPreview({
      config: { ...imageGeneratorTemplate.defaultConfig, resolution: '1536x1024' },
      inputs: { sceneList: sampleSceneList },
    });
    const frameList = result.imageFrameList.value as { resolution: string };
    expect(frameList.resolution).toBe('1536x1024');
  });

  it('should generate frames with correct structure', () => {
    const result = imageGeneratorTemplate.buildPreview({
      config: imageGeneratorTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneList },
    });
    const frames = (result.imageFrameList.value as { frames: readonly {
      frameId: string;
      sceneIndex: number;
      prompt: string;
      placeholderUrl: string;
      resolution: string;
      seed: number;
      stylePreset: string;
    }[] }).frames;

    frames.forEach((frame, index) => {
      expect(frame.frameId).toBeDefined();
      expect(frame.sceneIndex).toBe(index);
      expect(frame.prompt).toBeDefined();
      expect(frame.placeholderUrl).toContain('placeholder://');
      expect(frame.resolution).toBeDefined();
      expect(frame.seed).toBeGreaterThanOrEqual(0);
      expect(frame.stylePreset).toBe('cinematic');
    });
  });

  it('should generate assets with correct structure', () => {
    const result = imageGeneratorTemplate.buildPreview({
      config: { ...imageGeneratorTemplate.defaultConfig, outputMode: 'assets' },
      inputs: { sceneList: sampleSceneList },
    });
    const assets = (result.imageAssetList.value as { assets: readonly {
      assetId: string;
      sceneIndex: number;
      role: string;
      localFileName: string;
      metadata: { prompt: string; seed: number; stylePreset: string };
    }[] }).assets;

    assets.forEach((asset, index) => {
      expect(asset.assetId).toBeDefined();
      expect(asset.sceneIndex).toBe(index);
      expect(asset.role).toBe('background');
      expect(asset.localFileName).toContain('.png');
      expect(asset.metadata.prompt).toBeDefined();
      expect(asset.metadata.seed).toBeDefined();
    });
  });

  describe('mockExecute', () => {
    it('should return success status with valid input', async () => {
      const result = await imageGeneratorTemplate.mockExecute!({
        nodeId: 'n1',
        config: imageGeneratorTemplate.defaultConfig,
        inputs: { sceneList: sampleSceneList },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.imageFrameList.status).toBe('success');
    });

    it('should return error when required input is missing', async () => {
      const result = await imageGeneratorTemplate.mockExecute!({
        nodeId: 'n1',
        config: imageGeneratorTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.imageFrameList.status).toBe('error');
      expect(result.imageFrameList.errorMessage).toBeDefined();
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = imageGeneratorTemplate.mockExecute!({
        nodeId: 'n1',
        config: imageGeneratorTemplate.defaultConfig,
        inputs: { sceneList: sampleSceneList },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });

    it('should produce deterministic output with deterministic seed strategy', async () => {
      const config = { ...imageGeneratorTemplate.defaultConfig, seedStrategy: 'deterministic' as const };
      
      const result1 = await imageGeneratorTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs: { sceneList: sampleSceneList },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      
      const result2 = await imageGeneratorTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs: { sceneList: sampleSceneList },
        signal: new AbortController().signal,
        runId: 'run-b',
      });

      const frames1 = (result1.imageFrameList.value as { frames: readonly { seed: number }[] }).frames;
      const frames2 = (result2.imageFrameList.value as { frames: readonly { seed: number }[] }).frames;
      
      frames1.forEach((frame, index) => {
        expect(frame.seed).toBe(frames2[index].seed);
      });
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(imageGeneratorTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have unique fixture IDs', () => {
      const ids = imageGeneratorTemplate.fixtures.map(f => f.id);
      expect(new Set(ids).size).toBe(ids.length);
    });

    it('should have valid fixture configurations', () => {
      imageGeneratorTemplate.fixtures.forEach(f => {
        const merged = { ...imageGeneratorTemplate.defaultConfig, ...f.config };
        expect(() => ImageGeneratorConfigSchema.parse(merged)).not.toThrow();
      });
    });

    it('fixtures should produce valid outputs', () => {
      imageGeneratorTemplate.fixtures.forEach(f => {
        const result = imageGeneratorTemplate.buildPreview({
          config: { ...imageGeneratorTemplate.defaultConfig, ...f.config },
          inputs: f.previewInputs || {},
        });
        
        const activeOutputKey = f.config?.outputMode === 'assets' ? 'imageAssetList' : 'imageFrameList';
        expect(result[activeOutputKey]).toBeDefined();
        expect(result[activeOutputKey].status).toBe('ready');
      });
    });
  });
});
