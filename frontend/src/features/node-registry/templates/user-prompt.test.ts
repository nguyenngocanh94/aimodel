import { describe, it, expect } from 'vitest';
import {
  userPromptTemplate,
  UserPromptConfigSchema,
  type UserPromptConfig,
} from './user-prompt';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

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
  });

  describe('Config Schema', () => {
    it('should validate valid config', () => {
      const validConfig = {
        topic: 'Test Topic',
        goal: 'Test Goal',
        audience: 'Test Audience',
        tone: 'educational',
        durationSeconds: 120,
      };

      const result = UserPromptConfigSchema.parse(validConfig);
      expect(result.topic).toBe('Test Topic');
      expect(result.tone).toBe('educational');
      expect(result.durationSeconds).toBe(120);
    });

    it('should reject invalid tone values', () => {
      const invalidConfig = {
        topic: 'Test',
        goal: 'Test',
        audience: 'Test',
        tone: 'invalid-tone',
        durationSeconds: 120,
      };

      expect(() => UserPromptConfigSchema.parse(invalidConfig)).toThrow();
    });

    it('should reject duration outside range', () => {
      const tooShort = {
        topic: 'Test',
        goal: 'Test',
        audience: 'Test',
        tone: 'educational',
        durationSeconds: 3, // min is 5
      };

      const tooLong = {
        topic: 'Test',
        goal: 'Test',
        audience: 'Test',
        tone: 'educational',
        durationSeconds: 700, // max is 600
      };

      expect(() => UserPromptConfigSchema.parse(tooShort)).toThrow();
      expect(() => UserPromptConfigSchema.parse(tooLong)).toThrow();
    });

    it('should reject empty required strings', () => {
      const emptyTopic = {
        topic: '',
        goal: 'Test',
        audience: 'Test',
        tone: 'educational',
        durationSeconds: 120,
      };

      expect(() => UserPromptConfigSchema.parse(emptyTopic)).toThrow();
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

    it('should use default config when called with defaults', () => {
      const result = userPromptTemplate.buildPreview({
        config: userPromptTemplate.defaultConfig,
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

    it('should have valid fixture configs', () => {
      userPromptTemplate.fixtures.forEach(fixture => {
        expect(fixture.id).toBeDefined();
        expect(fixture.label).toBeDefined();
        
        // Validate partial config against schema
        if (fixture.config) {
          const partialConfig = { ...userPromptTemplate.defaultConfig, ...fixture.config };
          expect(() => UserPromptConfigSchema.parse(partialConfig)).not.toThrow();
        }
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
  });

  describe('Default Config', () => {
    it('should have complete default values', () => {
      expect(userPromptTemplate.defaultConfig.topic).toBeDefined();
      expect(userPromptTemplate.defaultConfig.goal).toBeDefined();
      expect(userPromptTemplate.defaultConfig.audience).toBeDefined();
      expect(userPromptTemplate.defaultConfig.tone).toBeDefined();
      expect(userPromptTemplate.defaultConfig.durationSeconds).toBeDefined();
    });

    it('should validate default config against schema', () => {
      expect(() => 
        UserPromptConfigSchema.parse(userPromptTemplate.defaultConfig)
      ).not.toThrow();
    });
  });
});
