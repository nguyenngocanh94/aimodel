/**
 * StoryWriter Node Template Tests - AiModel-624
 */

import { describe, it, expect, vi } from 'vitest';
import {
  storyWriterTemplate,
  StoryWriterConfigSchema,
  type StoryWriterConfig,
  type StoryArcPayload,
} from './story-writer';
import type { MockNodeExecutionArgs } from '../node-registry';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('storyWriterTemplate', () => {
  describe('template metadata', () => {
    it('should have correct type and category', () => {
      expect(storyWriterTemplate.type).toBe('storyWriter');
      expect(storyWriterTemplate.category).toBe('script');
      expect(storyWriterTemplate.executable).toBe(true);
      expect(storyWriterTemplate.templateVersion).toBe('1.0.0');
    });

    it('should have required ports defined', () => {
      expect(storyWriterTemplate.inputs).toHaveLength(4);
      expect(storyWriterTemplate.outputs).toHaveLength(1);

      const inputKeys = storyWriterTemplate.inputs.map(i => i.key);
      expect(inputKeys).toContain('productAnalysis');
      expect(inputKeys).toContain('trendBrief');
      expect(inputKeys).toContain('modelRoster');
      expect(inputKeys).toContain('seedIdea');

      const outputKeys = storyWriterTemplate.outputs.map(o => o.key);
      expect(outputKeys).toContain('storyArc');
    });

    it('should mark required inputs correctly', () => {
      const productAnalysis = storyWriterTemplate.inputs.find(i => i.key === 'productAnalysis');
      const trendBrief = storyWriterTemplate.inputs.find(i => i.key === 'trendBrief');
      const modelRoster = storyWriterTemplate.inputs.find(i => i.key === 'modelRoster');
      const seedIdea = storyWriterTemplate.inputs.find(i => i.key === 'seedIdea');

      expect(productAnalysis?.required).toBe(true);
      expect(trendBrief?.required).toBe(true);
      expect(modelRoster?.required).toBe(false);
      expect(seedIdea?.required).toBe(false);
    });
  });

  describe('config schema', () => {
    it('should validate correct config', () => {
      const validConfig: StoryWriterConfig = {
        targetDurationSeconds: 30,
        storyFormula: 'hero_journey',
        emotionalTone: 'aspirational',
        productIntegrationStyle: 'natural_use',
        genZAuthenticity: 'high',
        includeCasting: true,
        vietnameseDialect: 'neutral',
        seedIdea: 'Test idea',
      };

      const result = StoryWriterConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should reject invalid duration', () => {
      const invalidConfig = {
        targetDurationSeconds: 5, // too short
        storyFormula: 'hero_journey',
        emotionalTone: 'aspirational',
        productIntegrationStyle: 'natural_use',
        genZAuthenticity: 'high',
        includeCasting: true,
        vietnameseDialect: 'neutral',
      };

      const result = StoryWriterConfigSchema.safeParse(invalidConfig);
      expect(result.success).toBe(false);
    });

    it('should reject invalid story formula', () => {
      const invalidConfig = {
        targetDurationSeconds: 30,
        storyFormula: 'invalid_formula',
        emotionalTone: 'aspirational',
        productIntegrationStyle: 'natural_use',
        genZAuthenticity: 'high',
        includeCasting: true,
        vietnameseDialect: 'neutral',
      };

      const result = StoryWriterConfigSchema.safeParse(invalidConfig);
      expect(result.success).toBe(false);
    });

    it('should apply default values', () => {
      const result = StoryWriterConfigSchema.safeParse({
        targetDurationSeconds: 30,
        storyFormula: 'hero_journey',
        emotionalTone: 'aspirational',
        productIntegrationStyle: 'natural_use',
        genZAuthenticity: 'high',
        includeCasting: true,
        vietnameseDialect: 'neutral',
      });

      expect(result.success).toBe(true);
      if (result.success) {
        // seedIdea is optional, so it can be undefined
        expect(result.data.seedIdea).toBeUndefined();
      }
    });
  });

  describe('fixtures', () => {
    it('should have at least one fixture', () => {
      expect(storyWriterTemplate.fixtures.length).toBeGreaterThan(0);
    });

    it('should have valid fixture configs', () => {
      for (const fixture of storyWriterTemplate.fixtures) {
        const result = StoryWriterConfigSchema.safeParse(fixture.config);
        expect(result.success).toBe(true);
      }
    });
  });

  describe('buildPreview', () => {
    it('should return idle status when inputs missing', () => {
      const result = storyWriterTemplate.buildPreview({
        config: storyWriterTemplate.defaultConfig,
        inputs: {},
      });

      expect(result.storyArc.status).toBe('idle');
      expect(result.storyArc.value).toBeNull();
    });

    it('should generate preview with valid inputs', () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productType: 'fashion', productName: 'Test Product' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: { trendingFormats: ['POV'], trendingHashtags: ['#test'] },
          status: 'success',
          schemaType: 'json',
        },
      };

      const result = storyWriterTemplate.buildPreview({
        config: storyWriterTemplate.defaultConfig,
        inputs,
      });

      expect(result.storyArc.status).toBe('ready');
      expect(result.storyArc.value).toBeDefined();
      expect(result.storyArc.previewText).toContain('shots');
    });
  });

  describe('mockExecute', () => {
    it('should return error when required inputs missing', async () => {
      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);

      expect(result.storyArc.status).toBe('error');
      expect(result.storyArc.errorMessage).toContain('Missing required');
    });

    it('should generate story arc with valid inputs', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: {
          value: {
            productType: 'skincare',
            productName: 'Glow Serum',
            sellingPoints: ['Brightening'],
          },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: {
            trendingFormats: ['POV skincare'],
            trendingHashtags: ['#glowup'],
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);

      expect(result.storyArc.status).toBe('success');
      expect(result.storyArc.value).toBeDefined();
      
      const storyArc = result.storyArc.value as StoryArcPayload;
      expect(storyArc.shots).toBeDefined();
      expect(storyArc.shots.length).toBeGreaterThan(0);
      expect(storyArc.formula).toBeDefined();
      expect(storyArc.toneDirection).toBeDefined();
      expect(storyArc.soundDirection).toBeDefined();
      expect(storyArc.vietnameseLocalization).toBeDefined();
    });

    it('should include cast when includeCasting is true', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productType: 'fashion', productName: 'Jacket' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: { trendingFormats: ['POV'] },
          status: 'success',
          schemaType: 'json',
        },
      };

      const config: StoryWriterConfig = {
        ...storyWriterTemplate.defaultConfig,
        includeCasting: true,
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);
      const storyArc = result.storyArc.value as StoryArcPayload;
      
      expect(storyArc.cast).toBeDefined();
      expect(storyArc.cast.length).toBeGreaterThan(0);
    });

    it('should respect target duration in shot generation', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productName: 'Test' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: {},
          status: 'success',
          schemaType: 'json',
        },
      };

      const config: StoryWriterConfig = {
        ...storyWriterTemplate.defaultConfig,
        targetDurationSeconds: 60,
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);
      const storyArc = result.storyArc.value as StoryArcPayload;
      
      expect(storyArc.targetDuration).toBe(60);
      // Should have more shots for longer duration
      expect(storyArc.shots.length).toBeGreaterThanOrEqual(3);
    });

    it('should handle abort signal', async () => {
      const controller = new AbortController();
      controller.abort();

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs: {
          productAnalysis: { value: {}, status: 'success', schemaType: 'json' },
          trendBrief: { value: {}, status: 'success', schemaType: 'json' },
        },
        signal: controller.signal,
        runId: 'test-run',
      };

      await expect(storyWriterTemplate.mockExecute(args)).rejects.toThrow('cancelled');
    });

    it('should generate different results based on seedIdea', async () => {
      const inputs1: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productName: 'Test' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: {},
          status: 'success',
          schemaType: 'json',
        },
        seedIdea: {
          value: 'Romantic story arc',
          status: 'success',
          schemaType: 'text',
        },
      };

      const inputs2: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productName: 'Test' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: {},
          status: 'success',
          schemaType: 'json',
        },
        seedIdea: {
          value: 'Action adventure story',
          status: 'success',
          schemaType: 'text',
        },
      };

      const args1: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs: inputs1,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const args2: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs: inputs2,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result1 = await storyWriterTemplate.mockExecute(args1);
      const result2 = await storyWriterTemplate.mockExecute(args2);

      // Different seed ideas should produce different titles
      const storyArc1 = result1.storyArc.value as StoryArcPayload;
      const storyArc2 = result2.storyArc.value as StoryArcPayload;
      
      expect(storyArc1.title).not.toBe(storyArc2.title);
    });
  });

  describe('deterministic behavior', () => {
    it('should produce same output for same inputs', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: {
          value: { productName: 'Consistent Product', productType: 'test' },
          status: 'success',
          schemaType: 'json',
        },
        trendBrief: {
          value: { trendingFormats: ['POV'] },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: storyWriterTemplate.defaultConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result1 = await storyWriterTemplate.mockExecute(args);
      const result2 = await storyWriterTemplate.mockExecute(args);

      const storyArc1 = result1.storyArc.value as StoryArcPayload;
      const storyArc2 = result2.storyArc.value as StoryArcPayload;

      expect(storyArc1.title).toBe(storyArc2.title);
      expect(storyArc1.shots.length).toBe(storyArc2.shots.length);
      expect(storyArc1.formula).toBe(storyArc2.formula);
    });
  });

  describe('GenZ authenticity levels', () => {
    it('should include more slang for ultra authenticity', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: { value: { productName: 'Test' }, status: 'success', schemaType: 'json' },
        trendBrief: { value: {}, status: 'success', schemaType: 'json' },
      };

      const ultraConfig: StoryWriterConfig = {
        ...storyWriterTemplate.defaultConfig,
        genZAuthenticity: 'ultra',
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: ultraConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);
      const storyArc = result.storyArc.value as StoryArcPayload;

      expect(storyArc.vietnameseLocalization.genZSlang.length).toBeGreaterThanOrEqual(4);
    });

    it('should have no slang for low authenticity', async () => {
      const inputs: Record<string, PortPayload> = {
        productAnalysis: { value: { productName: 'Test' }, status: 'success', schemaType: 'json' },
        trendBrief: { value: {}, status: 'success', schemaType: 'json' },
      };

      const lowConfig: StoryWriterConfig = {
        ...storyWriterTemplate.defaultConfig,
        genZAuthenticity: 'low',
      };

      const args: MockNodeExecutionArgs<StoryWriterConfig> = {
        nodeId: 'test-node',
        config: lowConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await storyWriterTemplate.mockExecute(args);
      const storyArc = result.storyArc.value as StoryArcPayload;

      expect(storyArc.vietnameseLocalization.genZSlang.length).toBe(0);
    });
  });
});
