import { describe, it, expect } from 'vitest';
import {
  divergeTemplate,
  DivergeConfigSchema,
  type DivergeConfig,
  type VariantListValue,
  type VariantItem,
} from './diverge';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('diverge Node Template', () => {
  const sampleInput: PortPayload = {
    value: { productName: 'Test Product', description: 'Test description' },
    status: 'success',
    schemaType: 'productData',
  };

  const sampleInstruction: PortPayload = {
    value: 'Write a compelling marketing story',
    status: 'success',
    schemaType: 'text',
  };

  it('should have correct type and category', () => {
    expect(divergeTemplate.type).toBe('diverge');
    expect(divergeTemplate.category).toBe('utility');
    expect(divergeTemplate.title).toBe('Diverge');
    expect(divergeTemplate.executable).toBe(true);
  });

  it('should define correct input port definitions', () => {
    expect(divergeTemplate.inputs.map((p) => p.key)).toEqual([
      'input',
      'instruction',
    ]);
    expect(divergeTemplate.inputs[0].dataType).toBe('json');
    expect(divergeTemplate.inputs[0].required).toBe(true);
    expect(divergeTemplate.inputs[1].dataType).toBe('text');
    expect(divergeTemplate.inputs[1].required).toBe(false);
  });

  it('should define correct output port', () => {
    expect(divergeTemplate.outputs).toHaveLength(1);
    expect(divergeTemplate.outputs[0].key).toBe('variants');
    expect(divergeTemplate.outputs[0].dataType).toBe('variantList');
    expect(divergeTemplate.outputs[0].required).toBe(true);
  });

  it('should have correct default config values', () => {
    const cfg = divergeTemplate.defaultConfig;
    expect(cfg.providers).toEqual(['claude', 'gpt']);
    expect(cfg.taskType).toBe('creative');
    expect(cfg.aggregationMode).toBe('all');
    expect(cfg.timeoutSeconds).toBe(60);
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: DivergeConfig = DivergeConfigSchema.parse({
        providers: ['claude', 'gpt', 'gemini'],
        taskType: 'creative',
        aggregationMode: 'all',
        timeoutSeconds: 90,
      });
      expect(cfg.providers).toHaveLength(3);
      expect(cfg.taskType).toBe('creative');
      expect(cfg.timeoutSeconds).toBe(90);
    });

    it('should require at least 2 providers', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude'],
          taskType: 'creative',
          aggregationMode: 'all',
          timeoutSeconds: 60,
        }),
      ).toThrow();
    });

    it('should reject more than 4 providers', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'gpt', 'gemini', 'grok', 'custom'],
          taskType: 'creative',
          aggregationMode: 'all',
          timeoutSeconds: 60,
        }),
      ).toThrow();
    });

    it('should accept all valid provider combinations', () => {
      const validConfigs = [
        { providers: ['claude', 'gpt'] },
        { providers: ['claude', 'gemini'] },
        { providers: ['gpt', 'grok'] },
        { providers: ['claude', 'gpt', 'gemini', 'grok'] },
      ];
      validConfigs.forEach((c) => {
        const cfg = DivergeConfigSchema.parse({
          ...divergeTemplate.defaultConfig,
          ...c,
        });
        expect(cfg.providers).toEqual(c.providers);
      });
    });

    it('should reject invalid provider names', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'invalid-provider'],
          taskType: 'creative',
          aggregationMode: 'all',
          timeoutSeconds: 60,
        }),
      ).toThrow();
    });

    it('should accept all valid task types', () => {
      const taskTypes = ['creative', 'analytical', 'technical', 'summary', 'review'];
      taskTypes.forEach((taskType) => {
        const cfg = DivergeConfigSchema.parse({
          ...divergeTemplate.defaultConfig,
          taskType: taskType as DivergeConfig['taskType'],
        });
        expect(cfg.taskType).toBe(taskType);
      });
    });

    it('should reject invalid task type', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'gpt'],
          taskType: 'invalid',
          aggregationMode: 'all',
          timeoutSeconds: 60,
        }),
      ).toThrow();
    });

    it('should accept all valid aggregation modes', () => {
      const modes = ['all', 'best', 'merge'];
      modes.forEach((mode) => {
        const cfg = DivergeConfigSchema.parse({
          ...divergeTemplate.defaultConfig,
          aggregationMode: mode as DivergeConfig['aggregationMode'],
        });
        expect(cfg.aggregationMode).toBe(mode);
      });
    });

    it('should reject invalid aggregation mode', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'gpt'],
          taskType: 'creative',
          aggregationMode: 'invalid',
          timeoutSeconds: 60,
        }),
      ).toThrow();
    });

    it('should enforce timeout minimum of 5 seconds', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'gpt'],
          taskType: 'creative',
          aggregationMode: 'all',
          timeoutSeconds: 4,
        }),
      ).toThrow();
    });

    it('should enforce timeout maximum of 300 seconds', () => {
      expect(() =>
        DivergeConfigSchema.parse({
          providers: ['claude', 'gpt'],
          taskType: 'creative',
          aggregationMode: 'all',
          timeoutSeconds: 301,
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should return idle when input is missing', () => {
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.variants.status).toBe('idle');
      expect(out.variants.value).toBeNull();
      expect(out.variants.previewText).toContain('Waiting for input');
    });

    it('should produce variant list with input only', () => {
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput },
      });
      expect(out.variants.status).toBe('ready');
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.variants).toHaveLength(2);
      expect(variantList.count).toBe(2);
      expect(variantList.taskType).toBe('creative');
      expect(variantList.aggregationMode).toBe('all');
    });

    it('should produce variant list with input and instruction', () => {
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput, instruction: sampleInstruction },
      });
      expect(out.variants.status).toBe('ready');
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.variants).toHaveLength(2);
    });

    it('should generate variants for each provider', () => {
      const cfg: DivergeConfig = {
        providers: ['claude', 'gpt', 'gemini', 'grok'],
        taskType: 'creative',
        aggregationMode: 'all',
        timeoutSeconds: 60,
      };
      const out = divergeTemplate.buildPreview({
        config: cfg,
        inputs: { input: sampleInput },
      });
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.variants).toHaveLength(4);
      const providers = variantList.variants.map((v) => v.provider);
      expect(providers).toContain('claude');
      expect(providers).toContain('gpt');
      expect(providers).toContain('gemini');
      expect(providers).toContain('grok');
    });

    it('should generate different results per provider', () => {
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput, instruction: sampleInstruction },
      });
      const variantList = out.variants.value as VariantListValue;
      const claudeResult = variantList.variants.find((v) => v.provider === 'claude')?.result as { story: string };
      const gptResult = variantList.variants.find((v) => v.provider === 'gpt')?.result as { story: string };
      expect(claudeResult?.story).toContain('claude');
      expect(gptResult?.story).toContain('gpt');
    });

    it('should generate different result types based on taskType', () => {
      const taskTypes: DivergeConfig['taskType'][] = ['creative', 'analytical', 'technical', 'summary', 'review'];
      taskTypes.forEach((taskType) => {
        const cfg: DivergeConfig = { ...divergeTemplate.defaultConfig, taskType };
        const out = divergeTemplate.buildPreview({
          config: cfg,
          inputs: { input: sampleInput },
        });
        const variantList = out.variants.value as VariantListValue;
        const result = variantList.variants[0].result as Record<string, unknown>;
        expect(result).toBeDefined();
      });
    });

    it('should include metadata for each variant', () => {
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput },
      });
      const variantList = out.variants.value as VariantListValue;
      variantList.variants.forEach((variant: VariantItem) => {
        expect(variant.provider).toBeDefined();
        expect(variant.confidence).toBeGreaterThanOrEqual(0);
        expect(variant.confidence).toBeLessThanOrEqual(1);
        expect(variant.latencyMs).toBeGreaterThan(0);
        expect(variant.tokensUsed).toBeGreaterThan(0);
      });
    });

    it('should include previewText with provider details', () => {
      const cfg: DivergeConfig = {
        providers: ['claude', 'gpt', 'gemini'],
        taskType: 'analytical',
        aggregationMode: 'best',
        timeoutSeconds: 45,
      };
      const out = divergeTemplate.buildPreview({
        config: cfg,
        inputs: { input: sampleInput },
      });
      expect(out.variants.previewText).toContain('3 providers');
      expect(out.variants.previewText).toContain('analytical');
      expect(out.variants.previewText).toContain('best');
      expect(out.variants.previewText).toContain('claude');
      expect(out.variants.previewText).toContain('gpt');
      expect(out.variants.previewText).toContain('gemini');
    });

    it('should include completion timestamp', () => {
      const before = new Date().toISOString();
      const out = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput },
      });
      const after = new Date().toISOString();
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.completedAt).toBeDefined();
      expect(variantList.completedAt >= before).toBe(true);
      expect(variantList.completedAt <= after).toBe(true);
    });

    it('should be deterministic for same inputs', () => {
      const out1 = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput, instruction: sampleInstruction },
      });
      const out2 = divergeTemplate.buildPreview({
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput, instruction: sampleInstruction },
      });
      const v1 = out1.variants.value as VariantListValue;
      const v2 = out2.variants.value as VariantListValue;
      expect(v1.variants).toHaveLength(v2.variants.length);
      expect(JSON.stringify(v1.variants[0].result)).toBe(JSON.stringify(v2.variants[0].result));
    });
  });

  describe('mockExecute', () => {
    it('should return error when input is missing', async () => {
      const out = await divergeTemplate.mockExecute!({
        nodeId: 'n1',
        config: divergeTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.variants.status).toBe('error');
      expect(out.variants.errorMessage).toContain('Missing required input');
    });

    it('should return success with variant list', async () => {
      const out = await divergeTemplate.mockExecute!({
        nodeId: 'n1',
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.variants.status).toBe('success');
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.variants).toHaveLength(2);
      expect(variantList.count).toBe(2);
    });

    it('should throw when aborted', async () => {
      const controller = new AbortController();
      controller.abort();
      await expect(
        divergeTemplate.mockExecute!({
          nodeId: 'n1',
          config: divergeTemplate.defaultConfig,
          inputs: { input: sampleInput },
          signal: controller.signal,
          runId: 'run-1',
        }),
      ).rejects.toThrow('cancelled');
    });

    it('should handle different provider counts', async () => {
      const counts = [2, 3, 4] as const;
      for (const count of counts) {
        const providers = ['claude', 'gpt', 'gemini', 'grok'].slice(0, count) as DivergeConfig['providers'];
        const cfg: DivergeConfig = { ...divergeTemplate.defaultConfig, providers };
        const out = await divergeTemplate.mockExecute!({
          nodeId: 'n1',
          config: cfg,
          inputs: { input: sampleInput },
          signal: new AbortController().signal,
          runId: 'run-1',
        });
        const variantList = out.variants.value as VariantListValue;
        expect(variantList.variants).toHaveLength(count);
      }
    });

    it('should include producedAt timestamp', async () => {
      const out = await divergeTemplate.mockExecute!({
        nodeId: 'n1',
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.variants.producedAt).toBeDefined();
      expect(new Date(out.variants.producedAt!).getTime()).toBeGreaterThan(0);
    });

    it('should use instruction when provided', async () => {
      const out = await divergeTemplate.mockExecute!({
        nodeId: 'n1',
        config: divergeTemplate.defaultConfig,
        inputs: { input: sampleInput, instruction: sampleInstruction },
        signal: new AbortController().signal,
        runId: 'run-1',
      });
      expect(out.variants.status).toBe('success');
      const variantList = out.variants.value as VariantListValue;
      expect(variantList.variants).toHaveLength(2);
    });
  });

  describe('fixtures', () => {
    it('should have at least 4 fixtures', () => {
      expect(divergeTemplate.fixtures.length).toBeGreaterThanOrEqual(4);
    });

    it('should have creative-duo fixture', () => {
      const f = divergeTemplate.fixtures.find((fx) => fx.id === 'creative-duo');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Creative - Claude + GPT');
      expect(f!.config.providers).toEqual(['claude', 'gpt']);
      expect(f!.config.taskType).toBe('creative');
    });

    it('should have full-compete fixture with all 4 providers', () => {
      const f = divergeTemplate.fixtures.find((fx) => fx.id === 'full-compete');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Full Compete - All 4 Providers');
      expect(f!.config.providers).toHaveLength(4);
      expect(f!.config.providers).toContain('claude');
      expect(f!.config.providers).toContain('gpt');
      expect(f!.config.providers).toContain('gemini');
      expect(f!.config.providers).toContain('grok');
    });

    it('should have analytical-review fixture', () => {
      const f = divergeTemplate.fixtures.find((fx) => fx.id === 'analytical-review');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Analytical Review');
      expect(f!.config.taskType).toBe('analytical');
      expect(f!.config.aggregationMode).toBe('best');
    });

    it('should have technical-merge fixture', () => {
      const f = divergeTemplate.fixtures.find((fx) => fx.id === 'technical-merge');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Technical - Merge Results');
      expect(f!.config.taskType).toBe('technical');
      expect(f!.config.aggregationMode).toBe('merge');
    });

    it('should include sample product data in fixtures', () => {
      const f = divergeTemplate.fixtures.find((fx) => fx.id === 'creative-duo');
      expect(f?.previewInputs).toBeDefined();
      expect(f!.previewInputs!.input).toBeDefined();
      expect(f!.previewInputs!.instruction).toBeDefined();
    });
  });
});
