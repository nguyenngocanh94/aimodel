/**
 * userPrompt Node Template Tests - AiModel-9wx.4 (NM3 trim: configSchema assertions removed)
 *
 * Config validation is now backend-authoritative (NM1 + manifest).
 * Tests cover registry shape, buildPreview, and fixtures.
 */

import { describe, it, expect } from 'vitest';
import {
  userPromptTemplate,
  type UserPromptConfig,
} from './user-prompt';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

// Minimal valid config (replaces removed defaultConfig)
const baseConfig: UserPromptConfig = {
  topic: 'Introduction to Machine Learning',
  goal: 'Explain the basics of ML in an engaging way',
  audience: 'Technical beginners',
  tone: 'educational',
  durationSeconds: 120,
};

describe('userPrompt Node Template - AiModel-9wx.4', () => {
  describe('Template Structure', () => {
    it('should have correct metadata', () => {
      expect(userPromptTemplate.type).toBe('userPrompt');
      expect(userPromptTemplate.templateVersion).toBe('1.0.0');
      expect(userPromptTemplate.title).toBe('User Prompt');
      expect(userPromptTemplate.category).toBe('input');
      expect(userPromptTemplate.executable).toBe(false);
    });

    it('should have no inputs', () => {
      expect(userPromptTemplate.inputs).toHaveLength(0);
    });

    it('should have one output for prompt', () => {
      expect(userPromptTemplate.outputs).toHaveLength(1);
      expect(userPromptTemplate.outputs[0].key).toBe('prompt');
      expect(userPromptTemplate.outputs[0].dataType).toBe('prompt');
      expect(userPromptTemplate.outputs[0].direction).toBe('output');
    });

    it('should not have mockExecute (non-executable)', () => {
      expect(userPromptTemplate.mockExecute).toBeUndefined();
    });

    it('should have no configSchema (NM3 pilot stripped, manifest-driven)', () => {
      // configSchema is intentionally absent — backend manifest is the source of truth
      expect(userPromptTemplate.configSchema).toBeUndefined();
      // defaultConfig is an empty sentinel ({}); real defaults come from manifest
      expect(Object.keys(userPromptTemplate.defaultConfig as object).length).toBe(0);
    });
  });

  describe('buildPreview', () => {
    it('should generate valid PortPayload for prompt output', () => {
      const config: UserPromptConfig = {
        topic: 'Space Exploration',
        goal: 'Inspire curiosity about the cosmos',
        audience: 'General public',
        tone: 'cinematic',
        durationSeconds: 150,
      };

      const result = userPromptTemplate.buildPreview({ config, inputs: {} });

      expect(result).toHaveProperty('prompt');
      const promptPayload = result.prompt as PortPayload;

      expect(promptPayload.status).toBe('ready');
      expect(promptPayload.schemaType).toBe('prompt');
      expect(promptPayload.value).toBeDefined();
      expect(promptPayload.previewText).toContain('Space Exploration');
      expect(promptPayload.previewText).toContain('cinematic');
    });

    it('should include all config fields in payload value', () => {
      const config: UserPromptConfig = {
        topic: 'Test Topic',
        goal: 'Test Goal',
        audience: 'Test Audience',
        tone: 'playful',
        durationSeconds: 60,
      };

      const result = userPromptTemplate.buildPreview({ config, inputs: {} });
      const value = result.prompt.value as {
        topic: string;
        goal: string;
        audience: string;
        tone: string;
        durationSeconds: number;
        generatedAt: string;
      };

      expect(value.topic).toBe('Test Topic');
      expect(value.goal).toBe('Test Goal');
      expect(value.audience).toBe('Test Audience');
      expect(value.tone).toBe('playful');
      expect(value.durationSeconds).toBe(60);
      expect(value.generatedAt).toBeDefined();
    });

    it('should use base config when called with defaults', () => {
      const result = userPromptTemplate.buildPreview({
        config: baseConfig,
        inputs: {},
      });

      expect(result.prompt.status).toBe('ready');
      expect(result.prompt.value).toBeDefined();
    });
  });

  describe('Fixtures', () => {
    it('should have at least 2 fixtures', () => {
      expect(userPromptTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have unique fixture IDs', () => {
      const ids = userPromptTemplate.fixtures.map(f => f.id);
      const uniqueIds = new Set(ids);
      expect(uniqueIds.size).toBe(ids.length);
    });

    it('should have fixtures with required fields', () => {
      userPromptTemplate.fixtures.forEach(fixture => {
        expect(fixture.id).toBeDefined();
        expect(fixture.label).toBeDefined();
        expect(fixture.config).toBeDefined();
      });
    });

    it('should cover all tone options in fixtures', () => {
      const tones = userPromptTemplate.fixtures
        .map(f => f.config?.tone)
        .filter(Boolean);

      expect(tones).toContain('educational');
      expect(tones).toContain('cinematic');
      expect(tones).toContain('playful');
      expect(tones).toContain('dramatic');
    });

    it('fixtures produce valid preview outputs', () => {
      userPromptTemplate.fixtures.forEach(fixture => {
        const config: UserPromptConfig = { ...baseConfig, ...fixture.config };
        const result = userPromptTemplate.buildPreview({ config, inputs: {} });
        expect(result.prompt.status).toBe('ready');
        expect(result.prompt.value).toBeDefined();
      });
    });
  });
});
