import { describe, it, expect } from 'vitest';
import {
  scriptWriterTemplate,
  ScriptWriterConfigSchema,
  type ScriptWriterConfig,
} from './script-writer';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('scriptWriter Node Template - AiModel-9wx.5', () => {
  const samplePromptPayload: PortPayload = {
    value: {
      topic: 'Ocean currents',
      goal: 'Explain thermohaline circulation',
      audience: 'Curious adults',
      tone: 'educational',
      durationSeconds: 100,
      generatedAt: '2026-04-02T12:00:00.000Z',
    },
    status: 'ready',
    schemaType: 'prompt',
  };

  it('should match plan metadata and ports', () => {
    expect(scriptWriterTemplate.type).toBe('scriptWriter');
    expect(scriptWriterTemplate.category).toBe('script');
    expect(scriptWriterTemplate.executable).toBe(true);
    expect(scriptWriterTemplate.inputs).toHaveLength(1);
    expect(scriptWriterTemplate.inputs[0].key).toBe('prompt');
    expect(scriptWriterTemplate.inputs[0].dataType).toBe('prompt');
    expect(scriptWriterTemplate.outputs).toHaveLength(1);
    expect(scriptWriterTemplate.outputs[0].key).toBe('script');
    expect(scriptWriterTemplate.outputs[0].dataType).toBe('script');
  });

  it('should validate config with Zod', () => {
    const cfg: ScriptWriterConfig = ScriptWriterConfigSchema.parse({
      style: 'Test style',
      structure: 'three_act',
      includeHook: true,
      includeCTA: false,
      targetDurationSeconds: 45,
    });
    expect(cfg.structure).toBe('three_act');
  });

  it('buildPreview should emit idle when prompt is missing', () => {
    const out = scriptWriterTemplate.buildPreview({
      config: scriptWriterTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.script.status).toBe('idle');
    expect(out.script.value).toBeNull();
  });

  it('buildPreview should produce ready script PortPayload', () => {
    const out = scriptWriterTemplate.buildPreview({
      config: scriptWriterTemplate.defaultConfig,
      inputs: { prompt: samplePromptPayload },
    });
    expect(out.script.status).toBe('ready');
    expect(out.script.schemaType).toBe('script');
    expect(out.script.value).not.toBeNull();
    const v = out.script.value as { title: string; beats: readonly string[] };
    expect(v.title.length).toBeGreaterThan(0);
    expect(v.beats.length).toBeGreaterThanOrEqual(3);
  });

  it('mockExecute should return success and deterministic script value', async () => {
    const config = scriptWriterTemplate.defaultConfig;
    const a = await scriptWriterTemplate.mockExecute!({
      nodeId: 'n1',
      config,
      inputs: { prompt: samplePromptPayload },
      signal: new AbortController().signal,
      runId: 'run-a',
    });
    const b = await scriptWriterTemplate.mockExecute!({
      nodeId: 'n2',
      config,
      inputs: { prompt: samplePromptPayload },
      signal: new AbortController().signal,
      runId: 'run-b',
    });
    expect(a.script.status).toBe('success');
    expect(b.script.status).toBe('success');
    expect(JSON.stringify(a.script.value)).toBe(JSON.stringify(b.script.value));
  });

  it('should have at least two fixtures with valid merged configs', () => {
    expect(scriptWriterTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    scriptWriterTemplate.fixtures.forEach((f) => {
      const merged = { ...scriptWriterTemplate.defaultConfig, ...f.config };
      expect(() => ScriptWriterConfigSchema.parse(merged)).not.toThrow();
    });
  });
});
