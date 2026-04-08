import { describe, it, expect } from 'vitest';
import {
  productAnalyzerTemplate,
  ProductAnalyzerConfigSchema,
  type ProductAnalyzerConfig,
  type ProductAnalysisPayload,
} from './product-analyzer';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('productAnalyzer Node Template', () => {
  const sampleImagesPayload: PortPayload = {
    value: [
      { url: 'https://example.com/product-front.jpg' },
      { url: 'https://example.com/product-side.jpg' },
    ],
    status: 'success',
    schemaType: 'imageAssetList',
  };

  const sampleDescriptionPayload: PortPayload = {
    value: 'Premium leather crossbody bag with brass hardware',
    status: 'success',
    schemaType: 'text',
  };

  it('should match plan metadata and ports', () => {
    expect(productAnalyzerTemplate.type).toBe('productAnalyzer');
    expect(productAnalyzerTemplate.category).toBe('input');
    expect(productAnalyzerTemplate.executable).toBe(true);
    expect(productAnalyzerTemplate.inputs).toHaveLength(2);
    expect(productAnalyzerTemplate.inputs[0].key).toBe('images');
    expect(productAnalyzerTemplate.inputs[0].dataType).toBe('imageAssetList');
    expect(productAnalyzerTemplate.inputs[0].required).toBe(true);
    expect(productAnalyzerTemplate.inputs[1].key).toBe('description');
    expect(productAnalyzerTemplate.inputs[1].dataType).toBe('text');
    expect(productAnalyzerTemplate.inputs[1].required).toBe(false);
    expect(productAnalyzerTemplate.outputs).toHaveLength(1);
    expect(productAnalyzerTemplate.outputs[0].key).toBe('analysis');
    expect(productAnalyzerTemplate.outputs[0].dataType).toBe('json');
  });

  it('should validate config with Zod', () => {
    const cfg: ProductAnalyzerConfig = ProductAnalyzerConfigSchema.parse({
      provider: 'stub',
      apiKey: '',
      model: 'gpt-4o',
      analysisDepth: 'detailed',
    });
    expect(cfg.analysisDepth).toBe('detailed');
  });

  it('should reject invalid analysisDepth', () => {
    expect(() =>
      ProductAnalyzerConfigSchema.parse({
        provider: 'stub',
        apiKey: '',
        model: 'gpt-4o',
        analysisDepth: 'ultra',
      }),
    ).toThrow();
  });

  it('buildPreview should emit idle when images is missing', () => {
    const out = productAnalyzerTemplate.buildPreview({
      config: productAnalyzerTemplate.defaultConfig,
      inputs: {},
    });
    expect(out.analysis.status).toBe('idle');
    expect(out.analysis.value).toBeNull();
  });

  it('buildPreview should produce analysis PortPayload when images provided', () => {
    const out = productAnalyzerTemplate.buildPreview({
      config: productAnalyzerTemplate.defaultConfig,
      inputs: { images: sampleImagesPayload },
    });
    expect(out.analysis.schemaType).toBe('json');
    expect(out.analysis.value).not.toBeNull();
    const v = out.analysis.value as ProductAnalysisPayload;
    expect(v.productType.length).toBeGreaterThan(0);
    expect(v.productName.length).toBeGreaterThan(0);
    expect(v.colors.length).toBeGreaterThan(0);
    expect(v.sellingPoints.length).toBeGreaterThan(0);
    expect(v.targetAudience).toBeDefined();
    expect(v.pricePositioning).toBeDefined();
    expect(v.suggestedMood.length).toBeGreaterThan(0);
  });

  it('mockExecute should return success with structured analysis', async () => {
    const config = productAnalyzerTemplate.defaultConfig;
    const result = await productAnalyzerTemplate.mockExecute!({
      nodeId: 'n1',
      config,
      inputs: { images: sampleImagesPayload },
      signal: new AbortController().signal,
      runId: 'run-a',
    });
    expect(result.analysis.status).toBe('success');
    const v = result.analysis.value as ProductAnalysisPayload;
    expect(v.productType).toBeDefined();
    expect(v.productName).toBeDefined();
    expect(v.colors).toBeInstanceOf(Array);
    expect(v.materials).toBeInstanceOf(Array);
    expect(v.sellingPoints).toBeInstanceOf(Array);
    expect(v.targetAudience.age).toBeDefined();
    expect(v.targetAudience.gender).toBeDefined();
  });

  it('mockExecute should be deterministic for same inputs', async () => {
    const config = productAnalyzerTemplate.defaultConfig;
    const argsBase = {
      config,
      inputs: { images: sampleImagesPayload, description: sampleDescriptionPayload },
      signal: new AbortController().signal,
    };
    const a = await productAnalyzerTemplate.mockExecute!({
      ...argsBase,
      nodeId: 'n1',
      runId: 'run-a',
    });
    const b = await productAnalyzerTemplate.mockExecute!({
      ...argsBase,
      nodeId: 'n2',
      runId: 'run-b',
    });
    expect(JSON.stringify(a.analysis.value)).toBe(JSON.stringify(b.analysis.value));
  });

  it('mockExecute should return error when images missing', async () => {
    const result = await productAnalyzerTemplate.mockExecute!({
      nodeId: 'n1',
      config: productAnalyzerTemplate.defaultConfig,
      inputs: {},
      signal: new AbortController().signal,
      runId: 'run-err',
    });
    expect(result.analysis.status).toBe('error');
  });

  it('should have at least one fixture with valid merged config', () => {
    expect(productAnalyzerTemplate.fixtures.length).toBeGreaterThanOrEqual(1);
    productAnalyzerTemplate.fixtures.forEach((f) => {
      const merged = { ...productAnalyzerTemplate.defaultConfig, ...f.config };
      expect(() => ProductAnalyzerConfigSchema.parse(merged)).not.toThrow();
    });
  });
});
