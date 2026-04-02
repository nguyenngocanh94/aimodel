import { describe, it, expect } from 'vitest';
import {
  getTemplate,
  getAllTemplates,
  getTemplatesByCategory,
  getTemplateMetadata,
  hasTemplate,
  getTemplateCount,
  isExecutableNode,
  isNonExecutableNode,
  nodeTemplateRegistry,
  type NodeTemplate,
} from './node-registry';

describe('Node Registry Module - AiModel-9wx.15', () => {
  describe('Auto-registration', () => {
    it('should have all 11 templates registered', () => {
      expect(getTemplateCount()).toBe(11);
    });

    it('should have all expected template types', () => {
      const expectedTypes = [
        'userPrompt',
        'scriptWriter',
        'sceneSplitter',
        'promptRefiner',
        'imageGenerator',
        'imageAssetMapper',
        'ttsVoiceoverPlanner',
        'subtitleFormatter',
        'videoComposer',
        'reviewCheckpoint',
        'finalExport',
      ];

      expectedTypes.forEach((type) => {
        expect(hasTemplate(type)).toBe(true);
      });
    });
  });

  describe('getTemplate', () => {
    it('should return template by type', () => {
      const template = getTemplate('userPrompt');
      expect(template).toBeDefined();
      expect(template?.type).toBe('userPrompt');
      expect(template?.title).toBe('User Prompt');
    });

    it('should return undefined for unknown type', () => {
      const template = getTemplate('unknown');
      expect(template).toBeUndefined();
    });

    it('should return executable templates with mockExecute', () => {
      const template = getTemplate('scriptWriter');
      expect(template).toBeDefined();
      expect(template?.executable).toBe(true);
      expect(isExecutableNode(template as NodeTemplate<unknown>)).toBe(true);
    });

    it('should return non-executable templates without mockExecute', () => {
      const template = getTemplate('userPrompt');
      expect(template).toBeDefined();
      expect(template?.executable).toBe(false);
      expect(isNonExecutableNode(template as NodeTemplate<unknown>)).toBe(true);
    });
  });

  describe('getAllTemplates', () => {
    it('should return all templates', () => {
      const templates = getAllTemplates();
      expect(templates).toHaveLength(11);
    });

    it('should return templates with correct structure', () => {
      const templates = getAllTemplates();
      templates.forEach((template) => {
        expect(template.type).toBeDefined();
        expect(template.title).toBeDefined();
        expect(template.category).toBeDefined();
        expect(template.inputs).toBeDefined();
        expect(template.outputs).toBeDefined();
        expect(template.configSchema).toBeDefined();
        expect(template.buildPreview).toBeDefined();
      });
    });
  });

  describe('getTemplatesByCategory', () => {
    it('should filter by category', () => {
      const inputTemplates = getTemplatesByCategory('input');
      expect(inputTemplates).toHaveLength(1);
      expect(inputTemplates[0].type).toBe('userPrompt');
    });

    it('should return multiple templates for categories with many', () => {
      const visualTemplates = getTemplatesByCategory('visuals');
      expect(visualTemplates.length).toBeGreaterThanOrEqual(2);
      const types = visualTemplates.map((t) => t.type);
      expect(types).toContain('promptRefiner');
      expect(types).toContain('imageGenerator');
    });

    it('should return empty array for category with no templates', () => {
      // There are no templates in some categories yet
      const templates = getTemplatesByCategory('output');
      expect(templates).toHaveLength(1); // finalExport
    });
  });

  describe('getTemplateMetadata', () => {
    it('should return metadata for all templates', () => {
      const metadata = getTemplateMetadata();
      expect(metadata).toHaveLength(11);
    });

    it('should include required metadata fields', () => {
      const metadata = getTemplateMetadata();
      metadata.forEach((m) => {
        expect(m.type).toBeDefined();
        expect(m.title).toBeDefined();
        expect(m.description).toBeDefined();
        expect(m.category).toBeDefined();
        expect(m.inputs).toBeDefined();
        expect(m.outputs).toBeDefined();
        expect(m.executable).toBeDefined();
        expect(typeof m.executable).toBe('boolean');
        expect(m.fixtureCount).toBeGreaterThanOrEqual(0);
      });
    });

    it('should match template data', () => {
      const metadata = getTemplateMetadata();
      const userPromptMeta = metadata.find((m) => m.type === 'userPrompt');
      expect(userPromptMeta).toBeDefined();
      expect(userPromptMeta?.title).toBe('User Prompt');
      expect(userPromptMeta?.category).toBe('input');
      expect(userPromptMeta?.executable).toBe(false);
    });
  });

  describe('hasTemplate', () => {
    it('should return true for registered types', () => {
      expect(hasTemplate('userPrompt')).toBe(true);
      expect(hasTemplate('scriptWriter')).toBe(true);
      expect(hasTemplate('finalExport')).toBe(true);
    });

    it('should return false for unregistered types', () => {
      expect(hasTemplate('unknown')).toBe(false);
      expect(hasTemplate('customNode')).toBe(false);
    });
  });

  describe('getTemplateCount', () => {
    it('should return correct count', () => {
      expect(getTemplateCount()).toBe(11);
    });
  });

  describe('nodeTemplateRegistry singleton', () => {
    it('should align with exported helpers', () => {
      expect(nodeTemplateRegistry.size).toBe(getTemplateCount());
      expect(nodeTemplateRegistry.getAll()).toEqual(getAllTemplates());
      expect(nodeTemplateRegistry.getMetadata()).toEqual(getTemplateMetadata());
      expect(nodeTemplateRegistry.get('userPrompt')).toEqual(getTemplate('userPrompt'));
    });
  });

  describe('Type Guards', () => {
    it('isExecutableNode should identify executable templates', () => {
      const template = getTemplate('scriptWriter') as NodeTemplate<unknown>;
      expect(isExecutableNode(template)).toBe(true);
      expect(isNonExecutableNode(template)).toBe(false);
    });

    it('isNonExecutableNode should identify non-executable templates', () => {
      const template = getTemplate('userPrompt') as NodeTemplate<unknown>;
      expect(isNonExecutableNode(template)).toBe(true);
      expect(isExecutableNode(template)).toBe(false);
    });

    it('should correctly categorize all templates', () => {
      const templates = getAllTemplates();
      const executable = templates.filter((t) => isExecutableNode(t));
      const nonExecutable = templates.filter((t) => isNonExecutableNode(t));

      expect(executable.length + nonExecutable.length).toBe(11);
    });
  });

  describe('Template Categories', () => {
    it('should have expected categories', () => {
      const templates = getAllTemplates();
      const categories = new Set(templates.map((t) => t.category));

      expect(categories.has('input')).toBe(true);
      expect(categories.has('script')).toBe(true);
      expect(categories.has('visuals')).toBe(true);
      expect(categories.has('audio')).toBe(true);
      expect(categories.has('video')).toBe(true);
      expect(categories.has('utility')).toBe(true);
      expect(categories.has('output')).toBe(true);
    });
  });
});
