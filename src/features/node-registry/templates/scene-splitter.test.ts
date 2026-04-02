import { describe, it, expect } from 'vitest';
import {
  sceneSplitterTemplate,
  SceneSplitterConfigSchema,
  type SceneSplitterConfig,
} from './scene-splitter';
import type { ScriptPayload } from './script-writer';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('sceneSplitter Node Template - AiModel-9wx.6', () => {
  const sampleScript: ScriptPayload = {
    title: 'Test video',
    hook: 'Hook line',
    beats: ['Beat A', 'Beat B', 'Beat C'],
    narration: 'Narration body',
    callToAction: 'CTA',
  };

  const scriptPayload: PortPayload = {
    value: sampleScript,
    status: 'ready',
    schemaType: 'script',
  };

  it('should match plan ports and category', () => {
    expect(sceneSplitterTemplate.type).toBe('sceneSplitter');
    expect(sceneSplitterTemplate.category).toBe('script');
    expect(sceneSplitterTemplate.executable).toBe(true);
    expect(sceneSplitterTemplate.inputs[0].dataType).toBe('script');
    expect(sceneSplitterTemplate.outputs[0].dataType).toBe('sceneList');
  });

  it('should validate config', () => {
    const c: SceneSplitterConfig = SceneSplitterConfigSchema.parse({
      sceneCountTarget: 4,
      maxSceneDurationSeconds: 30,
      includeShotIntent: true,
      includeVisualPromptHints: false,
    });
    expect(c.sceneCountTarget).toBe(4);
  });

  it('buildPreview should be idle without script', () => {
    const out = sceneSplitterTemplate.buildPreview({
      config: sceneSplitterTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.scenes.status).toBe('idle');
    expect(out.scenes.value).toBeNull();
  });

  it('buildPreview should produce ready sceneList', () => {
    const out = sceneSplitterTemplate.buildPreview({
      config: sceneSplitterTemplate.defaultConfig,
      inputs: { script: scriptPayload },
    });
    expect(out.scenes.status).toBe('ready');
    expect(out.scenes.schemaType).toBe('sceneList');
    const list = out.scenes.value as readonly { sequenceIndex: number; summary: string }[];
    expect(list.length).toBe(sceneSplitterTemplate.defaultConfig.sceneCountTarget);
    expect(list[0].sequenceIndex).toBe(0);
  });

  it('mockExecute should return success and deterministic scenes', async () => {
    const config: SceneSplitterConfig = sceneSplitterTemplate.defaultConfig;
    const a = await sceneSplitterTemplate.mockExecute!({
      nodeId: 'n1',
      config,
      inputs: { script: scriptPayload },
      signal: new AbortController().signal,
      runId: 'r1',
    });
    const b = await sceneSplitterTemplate.mockExecute!({
      nodeId: 'n2',
      config,
      inputs: { script: scriptPayload },
      signal: new AbortController().signal,
      runId: 'r2',
    });
    expect(a.scenes.status).toBe('success');
    expect(JSON.stringify(a.scenes.value)).toBe(JSON.stringify(b.scenes.value));
  });

  it('should include optional fields per config', () => {
    const out = sceneSplitterTemplate.buildPreview({
      config: {
        sceneCountTarget: 2,
        maxSceneDurationSeconds: 20,
        includeShotIntent: true,
        includeVisualPromptHints: true,
      },
      inputs: { script: scriptPayload },
    });
    const list = out.scenes.value as readonly {
      shotIntent?: string;
      visualPromptHints?: readonly string[];
    }[];
    expect(list[0].shotIntent).toBeDefined();
    expect(list[0].visualPromptHints?.length).toBeGreaterThan(0);
  });

  it('should have at least two fixtures', () => {
    expect(sceneSplitterTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
  });
});
