import { describe, it, expect } from 'vitest';
import {
  promptRefinerTemplate,
  PromptRefinerConfigSchema,
  type PromptRefinerConfig,
} from './prompt-refiner';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('promptRefiner Node Template - AiModel-9wx.7', () => {
  const sampleSceneListPayload: PortPayload = {
    value: {
      scenes: [
        {
          sequenceIndex: 0,
          summary: 'Opening scene',
          startTimeSeconds: 0,
          durationSeconds: 15,
          narration: 'Introduction to the topic',
          visualDescription: 'Wide shot of a beautiful landscape',
          shotIntent: 'Establishing shot',
          visualPromptHint: 'Cinematic wide shot of mountains at golden hour',
        },
        {
          sequenceIndex: 1,
          summary: 'Detail shot',
          startTimeSeconds: 15,
          durationSeconds: 20,
          narration: 'Focus on the details',
          visualDescription: 'Close-up of intricate patterns',
          shotIntent: 'Macro shot',
          visualPromptHint: 'Detailed macro shot showing texture and pattern',
        },
        {
          sequenceIndex: 2,
          summary: 'Conclusion',
          startTimeSeconds: 35,
          durationSeconds: 10,
          narration: 'Wrapping up',
          visualDescription: 'Peaceful sunset scene',
          shotIntent: 'Wide scenic shot',
          visualPromptHint: 'Serene sunset over calm waters',
        },
      ],
      totalDurationSeconds: 45,
      sceneCount: 3,
    },
    status: 'success',
    schemaType: 'sceneList',
  };

  it('should match plan metadata and ports', () => {
    expect(promptRefinerTemplate.type).toBe('promptRefiner');
    expect(promptRefinerTemplate.category).toBe('visuals');
    expect(promptRefinerTemplate.executable).toBe(true);
    expect(promptRefinerTemplate.inputs).toHaveLength(1);
    expect(promptRefinerTemplate.inputs[0].key).toBe('sceneList');
    expect(promptRefinerTemplate.inputs[0].dataType).toBe('sceneList');
    expect(promptRefinerTemplate.outputs).toHaveLength(1);
    expect(promptRefinerTemplate.outputs[0].key).toBe('promptList');
    expect(promptRefinerTemplate.outputs[0].dataType).toBe('promptList');
  });

  it('should validate config with Zod', () => {
    const cfg: PromptRefinerConfig = PromptRefinerConfigSchema.parse({
      visualStyle: 'cinematic',
      cameraLanguage: 'dramatic',
      aspectRatio: '16:9',
      consistencyNotes: 'Warm tones throughout',
      negativePromptEnabled: true,
    });
    expect(cfg.visualStyle).toBe('cinematic');
    expect(cfg.aspectRatio).toBe('16:9');
  });

  it('should accept all visual style variants', () => {
    const styles = ['photorealistic', 'cinematic', 'illustrated', '3d-rendered', 'anime'];
    styles.forEach(style => {
      const result = PromptRefinerConfigSchema.safeParse({
        visualStyle: style,
        cameraLanguage: 'standard',
        aspectRatio: '16:9',
        consistencyNotes: '',
        negativePromptEnabled: true,
      });
      expect(result.success).toBe(true);
    });
  });

  it('should accept all camera language variants', () => {
    const languages = ['standard', 'dramatic', 'intimate', 'epic'];
    languages.forEach(lang => {
      const result = PromptRefinerConfigSchema.safeParse({
        visualStyle: 'cinematic',
        cameraLanguage: lang,
        aspectRatio: '16:9',
        consistencyNotes: '',
        negativePromptEnabled: true,
      });
      expect(result.success).toBe(true);
    });
  });

  it('should accept all aspect ratio variants', () => {
    const ratios = ['16:9', '9:16', '1:1', '4:3'];
    ratios.forEach(ratio => {
      const result = PromptRefinerConfigSchema.safeParse({
        visualStyle: 'cinematic',
        cameraLanguage: 'standard',
        aspectRatio: ratio,
        consistencyNotes: '',
        negativePromptEnabled: true,
      });
      expect(result.success).toBe(true);
    });
  });

  it('should reject invalid visual style', () => {
    const result = PromptRefinerConfigSchema.safeParse({
      visualStyle: 'watercolor',
      cameraLanguage: 'standard',
      aspectRatio: '16:9',
      consistencyNotes: '',
      negativePromptEnabled: true,
    });
    expect(result.success).toBe(false);
  });

  it('should enforce consistencyNotes max length', () => {
    const tooLong = PromptRefinerConfigSchema.safeParse({
      visualStyle: 'cinematic',
      cameraLanguage: 'standard',
      aspectRatio: '16:9',
      consistencyNotes: 'a'.repeat(501),
      negativePromptEnabled: true,
    });
    expect(tooLong.success).toBe(false);

    const valid = PromptRefinerConfigSchema.safeParse({
      visualStyle: 'cinematic',
      cameraLanguage: 'standard',
      aspectRatio: '16:9',
      consistencyNotes: 'a'.repeat(500),
      negativePromptEnabled: true,
    });
    expect(valid.success).toBe(true);
  });

  it('buildPreview should emit idle when sceneList is missing', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: promptRefinerTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.promptList.status).toBe('idle');
    expect(out.promptList.value).toBeNull();
  });

  it('buildPreview should produce ready promptList PortPayload', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: promptRefinerTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneListPayload },
    });
    expect(out.promptList.status).toBe('ready');
    expect(out.promptList.schemaType).toBe('promptList');
    expect(out.promptList.value).not.toBeNull();
  });

  it('buildPreview should produce one prompt per scene', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: promptRefinerTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = out.promptList.value as { prompts: readonly unknown[]; count: number };
    expect(v.count).toBe(3);
    expect(v.prompts.length).toBe(3);
    
    v.prompts.forEach((prompt, index) => {
      expect((prompt as { sceneIndex: number }).sceneIndex).toBe(index);
    });
  });

  it('refined prompts should include required fields', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: promptRefinerTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = out.promptList.value as {
      prompts: readonly {
        sceneIndex: number;
        prompt: string;
        aspectRatio: string;
        style: string;
        cameraDirection: string;
        durationHint: string;
      }[];
    };

    v.prompts.forEach(prompt => {
      expect(prompt.prompt).toBeDefined();
      expect(prompt.prompt.length).toBeGreaterThan(0);
      expect(prompt.aspectRatio).toBe('16:9');
      expect(prompt.style).toBe('cinematic');
      expect(prompt.cameraDirection).toBeDefined();
      expect(prompt.durationHint).toBeDefined();
    });
  });

  it('buildPreview should include negativePrompt when enabled', () => {
    const withNegative = promptRefinerTemplate.buildPreview({
      config: { ...promptRefinerTemplate.defaultConfig, negativePromptEnabled: true },
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = withNegative.promptList.value as { prompts: readonly { negativePrompt?: string }[] };
    expect(v.prompts[0].negativePrompt).toBeDefined();
    expect(v.prompts[0].negativePrompt?.length).toBeGreaterThan(0);
  });

  it('buildPreview should omit negativePrompt when disabled', () => {
    const withoutNegative = promptRefinerTemplate.buildPreview({
      config: { ...promptRefinerTemplate.defaultConfig, negativePromptEnabled: false },
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = withoutNegative.promptList.value as { prompts: readonly { negativePrompt?: string }[] };
    expect(v.prompts[0].negativePrompt).toBeUndefined();
  });

  it('mockExecute should return success and deterministic promptList value', async () => {
    const config = promptRefinerTemplate.defaultConfig;
    const a = await promptRefinerTemplate.mockExecute!({
      nodeId: 'n1',
      config,
      inputs: { sceneList: sampleSceneListPayload },
      signal: new AbortController().signal,
      runId: 'run-a',
    });
    const b = await promptRefinerTemplate.mockExecute!({
      nodeId: 'n2',
      config,
      inputs: { sceneList: sampleSceneListPayload },
      signal: new AbortController().signal,
      runId: 'run-b',
    });
    expect(a.promptList.status).toBe('success');
    expect(b.promptList.status).toBe('success');

    // Compare values ignoring generatedAt timestamp
    const valueA = a.promptList.value as { prompts: readonly unknown[]; count: number; visualStyle: string; aspectRatio: string };
    const valueB = b.promptList.value as { prompts: readonly unknown[]; count: number; visualStyle: string; aspectRatio: string };
    expect(valueA.prompts).toEqual(valueB.prompts);
    expect(valueA.count).toBe(valueB.count);
    expect(valueA.visualStyle).toBe(valueB.visualStyle);
    expect(valueA.aspectRatio).toBe(valueB.aspectRatio);
  });

  it('mockExecute should return error when sceneList is missing', async () => {
    const result = await promptRefinerTemplate.mockExecute!({
      nodeId: 'n1',
      config: promptRefinerTemplate.defaultConfig,
      inputs: {},
      signal: new AbortController().signal,
      runId: 'run-a',
    });
    expect(result.promptList.status).toBe('error');
    expect(result.promptList.errorMessage).toBeDefined();
  });

  it('mockExecute should respect abort signal', async () => {
    const controller = new AbortController();
    const promise = promptRefinerTemplate.mockExecute!({
      nodeId: 'n1',
      config: promptRefinerTemplate.defaultConfig,
      inputs: { sceneList: sampleSceneListPayload },
      signal: controller.signal,
      runId: 'run-a',
    });
    controller.abort();
    await expect(promise).rejects.toThrow('cancelled');
  });

  it('buildPreview should append consistencyNotes to prompts when set', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: {
        ...promptRefinerTemplate.defaultConfig,
        consistencyNotes: 'Same palette across scenes',
      },
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = out.promptList.value as { prompts: readonly { prompt: string }[] };
    expect(v.prompts[0].prompt).toContain('Consistency: Same palette across scenes');
  });

  it('prompts should vary based on visualStyle config', () => {
    const cinematic = promptRefinerTemplate.buildPreview({
      config: { ...promptRefinerTemplate.defaultConfig, visualStyle: 'cinematic' },
      inputs: { sceneList: sampleSceneListPayload },
    });
    const anime = promptRefinerTemplate.buildPreview({
      config: { ...promptRefinerTemplate.defaultConfig, visualStyle: 'anime' },
      inputs: { sceneList: sampleSceneListPayload },
    });

    const cinematicPrompt = (cinematic.promptList.value as { prompts: readonly { prompt: string }[] }).prompts[0].prompt;
    const animePrompt = (anime.promptList.value as { prompts: readonly { prompt: string }[] }).prompts[0].prompt;

    expect(cinematicPrompt).toContain('cinematic');
    expect(animePrompt).toContain('anime');
  });

  it('output should include metadata fields', () => {
    const out = promptRefinerTemplate.buildPreview({
      config: { ...promptRefinerTemplate.defaultConfig, visualStyle: 'photorealistic', aspectRatio: '9:16' },
      inputs: { sceneList: sampleSceneListPayload },
    });
    const v = out.promptList.value as { visualStyle: string; aspectRatio: string; count: number };
    
    expect(v.visualStyle).toBe('photorealistic');
    expect(v.aspectRatio).toBe('9:16');
    expect(v.count).toBe(3);
  });

  it('should have at least two fixtures with valid merged configs', () => {
    expect(promptRefinerTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    promptRefinerTemplate.fixtures.forEach((f) => {
      const merged = { ...promptRefinerTemplate.defaultConfig, ...f.config };
      expect(() => PromptRefinerConfigSchema.parse(merged)).not.toThrow();
    });
  });

  it('fixtures should produce valid promptLists', () => {
    promptRefinerTemplate.fixtures.forEach((f) => {
      const result = promptRefinerTemplate.buildPreview({
        config: { ...promptRefinerTemplate.defaultConfig, ...f.config },
        inputs: f.previewInputs || {},
      });
      expect(result.promptList.status).toBe('ready');
      expect(result.promptList.value).not.toBeNull();
      const v = result.promptList.value as { prompts: readonly unknown[]; count: number };
      expect(v.count).toBeGreaterThanOrEqual(2);
      expect(v.prompts.length).toBe(v.count);
    });
  });

  it('fixtures should have unique IDs', () => {
    const ids = promptRefinerTemplate.fixtures.map(f => f.id);
    expect(new Set(ids).size).toBe(ids.length);
  });
});
