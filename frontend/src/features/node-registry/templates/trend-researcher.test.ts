import { describe, it, expect } from 'vitest';
import {
  trendResearcherTemplate,
  TrendResearcherConfigSchema,
  type TrendResearcherConfig,
  type TrendBriefPayload,
} from './trend-researcher';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('trendResearcher Node Template', () => {
  const sampleContextPayload: PortPayload = {
    value: { product: 'Vietnamese coffee brand', target: 'Gen Z' },
    status: 'success',
    schemaType: 'json',
  };

  const sampleTopicPayload: PortPayload = {
    value: 'Vietnamese coffee culture trends on TikTok',
    status: 'success',
    schemaType: 'text',
  };

  it('should match backend metadata and ports', () => {
    expect(trendResearcherTemplate.type).toBe('trendResearcher');
    expect(trendResearcherTemplate.templateVersion).toBe('1.0.0');
    expect(trendResearcherTemplate.title).toBe('Trend Researcher');
    expect(trendResearcherTemplate.category).toBe('script');
    expect(trendResearcherTemplate.executable).toBe(true);

    expect(trendResearcherTemplate.inputs).toHaveLength(2);
    expect(trendResearcherTemplate.inputs[0].key).toBe('context');
    expect(trendResearcherTemplate.inputs[0].dataType).toBe('json');
    expect(trendResearcherTemplate.inputs[0].required).toBe(false);
    expect(trendResearcherTemplate.inputs[1].key).toBe('topic');
    expect(trendResearcherTemplate.inputs[1].dataType).toBe('text');
    expect(trendResearcherTemplate.inputs[1].required).toBe(false);

    expect(trendResearcherTemplate.outputs).toHaveLength(1);
    expect(trendResearcherTemplate.outputs[0].key).toBe('trendBrief');
    expect(trendResearcherTemplate.outputs[0].dataType).toBe('json');
  });

  it('should validate config with Zod', () => {
    const cfg: TrendResearcherConfig = TrendResearcherConfigSchema.parse({
      provider: 'grok',
      apiKey: 'test-key',
      model: 'grok-3',
      market: 'vietnam',
      platform: 'tiktok',
      language: 'vi',
    });
    expect(cfg.market).toBe('vietnam');
    expect(cfg.platform).toBe('tiktok');
  });

  it('should reject invalid market/platform values', () => {
    expect(() => TrendResearcherConfigSchema.parse({
      provider: 'stub',
      market: 'invalid-market',
      platform: 'tiktok',
      language: 'vi',
    })).toThrow();

    expect(() => TrendResearcherConfigSchema.parse({
      provider: 'stub',
      market: 'vietnam',
      platform: 'invalid-platform',
      language: 'vi',
    })).toThrow();
  });

  it('default config should target Vietnam TikTok', () => {
    const cfg = trendResearcherTemplate.defaultConfig;
    expect(cfg.provider).toBe('stub');
    expect(cfg.model).toBe('grok-3');
    expect(cfg.market).toBe('vietnam');
    expect(cfg.platform).toBe('tiktok');
    expect(cfg.language).toBe('vi');
  });

  it('buildPreview should return idle status', () => {
    const out = trendResearcherTemplate.buildPreview({
      config: trendResearcherTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.trendBrief.status).toBe('idle');
    expect(out.trendBrief.value).toBeNull();
    expect(out.trendBrief.schemaType).toBe('json');
  });

  it('mockExecute should return structured trend brief', async () => {
    const result = await trendResearcherTemplate.mockExecute!({
      nodeId: 'n1',
      config: trendResearcherTemplate.defaultConfig,
      inputs: {
        context: sampleContextPayload,
        topic: sampleTopicPayload,
      },
      signal: new AbortController().signal,
      runId: 'run-trend-1',
    });

    expect(result.trendBrief.status).toBe('success');
    expect(result.trendBrief.schemaType).toBe('json');

    const brief = result.trendBrief.value as TrendBriefPayload;
    expect(brief.trendingFormats.length).toBeGreaterThan(0);
    expect(brief.trendingHashtags.length).toBeGreaterThan(0);
    expect(brief.trendingSounds.length).toBeGreaterThan(0);
    expect(brief.culturalMoments.length).toBeGreaterThan(0);
    expect(brief.contentAngles.length).toBeGreaterThan(0);
    expect(brief.audienceInsights).toBeDefined();
    expect(brief.avoidList.length).toBeGreaterThan(0);
  });

  it('mockExecute should be deterministic for same inputs', async () => {
    const args = {
      nodeId: 'n1',
      config: trendResearcherTemplate.defaultConfig,
      inputs: {
        context: sampleContextPayload,
        topic: sampleTopicPayload,
      },
      signal: new AbortController().signal,
      runId: 'run-a',
    };

    const a = await trendResearcherTemplate.mockExecute!(args);
    const b = await trendResearcherTemplate.mockExecute!({
      ...args,
      nodeId: 'n2',
      runId: 'run-b',
    });

    expect(JSON.stringify((a.trendBrief.value as TrendBriefPayload).trendingFormats))
      .toBe(JSON.stringify((b.trendBrief.value as TrendBriefPayload).trendingFormats));
  });

  it('should have at least one fixture with valid config', () => {
    expect(trendResearcherTemplate.fixtures.length).toBeGreaterThanOrEqual(1);
    trendResearcherTemplate.fixtures.forEach((f) => {
      const merged = { ...trendResearcherTemplate.defaultConfig, ...f.config };
      expect(() => TrendResearcherConfigSchema.parse(merged)).not.toThrow();
    });
  });

  it('fixture should be Vietnam TikTok Trends', () => {
    const fixture = trendResearcherTemplate.fixtures[0];
    expect(fixture.id).toBe('vietnam-tiktok-trends');
    expect(fixture.label).toBe('Vietnam TikTok Trends');
  });
});
