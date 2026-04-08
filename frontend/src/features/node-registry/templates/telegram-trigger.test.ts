/**
 * TelegramTrigger Node Template Tests - AiModel-618
 */

import { describe, it, expect, vi } from 'vitest';
import {
  telegramTriggerTemplate,
  TelegramTriggerConfigSchema,
  type TelegramTriggerConfig,
  type TelegramMessagePayload,
} from './telegram-trigger';
import type { MockNodeExecutionArgs } from '../node-registry';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('telegramTriggerTemplate', () => {
  describe('template metadata', () => {
    it('should have correct type and category', () => {
      expect(telegramTriggerTemplate.type).toBe('telegramTrigger');
      expect(telegramTriggerTemplate.category).toBe('input');
      expect(telegramTriggerTemplate.executable).toBe(true);
      expect(telegramTriggerTemplate.templateVersion).toBe('1.0.0');
    });

    it('should have no inputs (trigger node)', () => {
      expect(telegramTriggerTemplate.inputs).toHaveLength(0);
    });

    it('should have correct outputs', () => {
      expect(telegramTriggerTemplate.outputs).toHaveLength(4);
      
      const outputKeys = telegramTriggerTemplate.outputs.map(o => o.key);
      expect(outputKeys).toContain('message');
      expect(outputKeys).toContain('text');
      expect(outputKeys).toContain('images');
      expect(outputKeys).toContain('triggerInfo');
    });
  });

  describe('config schema', () => {
    it('should validate correct config', () => {
      const validConfig: TelegramTriggerConfig = {
        botToken: 'test_token',
        allowedChatIds: ['123', '456'],
        extractImages: true,
        maxImages: 5,
        filterKeywords: ['product', 'help'],
      };

      const result = TelegramTriggerConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should reject invalid maxImages', () => {
      const invalidConfig = {
        botToken: 'test',
        allowedChatIds: [],
        extractImages: true,
        maxImages: 15, // too high
        filterKeywords: [],
      };

      const result = TelegramTriggerConfigSchema.safeParse(invalidConfig);
      expect(result.success).toBe(false);
    });

    it('should accept empty arrays', () => {
      const config = {
        botToken: 'test',
        allowedChatIds: [],
        extractImages: false,
        maxImages: 1,
        filterKeywords: [],
      };

      const result = TelegramTriggerConfigSchema.safeParse(config);
      expect(result.success).toBe(true);
    });
  });

  describe('fixtures', () => {
    it('should have at least one fixture', () => {
      expect(telegramTriggerTemplate.fixtures.length).toBeGreaterThan(0);
    });

    it('should have valid fixture configs', () => {
      for (const fixture of telegramTriggerTemplate.fixtures) {
        const result = TelegramTriggerConfigSchema.safeParse(fixture.config);
        expect(result.success).toBe(true);
      }
    });
  });

  describe('buildPreview', () => {
    it('should generate preview with mock message', () => {
      const result = telegramTriggerTemplate.buildPreview({
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
      });

      expect(result.message.status).toBe('ready');
      expect(result.message.value).toBeDefined();
      
      const message = result.message.value as TelegramMessagePayload;
      expect(message.messageId).toBeDefined();
      expect(message.chatId).toBeDefined();
      expect(message.timestamp).toBeDefined();
    });

    it('should include text output', () => {
      const result = telegramTriggerTemplate.buildPreview({
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
      });

      expect(result.text.status).toBe('ready');
      expect(result.text.schemaType).toBe('text');
    });

    it('should include images when extractImages is true', () => {
      const config: TelegramTriggerConfig = {
        ...telegramTriggerTemplate.defaultConfig,
        extractImages: true,
        maxImages: 3,
      };

      const result = telegramTriggerTemplate.buildPreview({
        config,
        inputs: {},
      });

      expect(result.images.status).toBe('ready');
      expect(result.images.schemaType).toBe('imageAssetList');
      
      const images = result.images.value as unknown[];
      expect(images.length).toBeGreaterThan(0);
      expect(images.length).toBeLessThanOrEqual(3);
    });

    it('should not include images when extractImages is false', () => {
      const config: TelegramTriggerConfig = {
        ...telegramTriggerTemplate.defaultConfig,
        extractImages: false,
        maxImages: 0,
      };

      const result = telegramTriggerTemplate.buildPreview({
        config,
        inputs: {},
      });

      expect(result.images.status).toBe('idle');
      expect(result.images.value).toBeNull();
    });

    it('should include triggerInfo output', () => {
      const result = telegramTriggerTemplate.buildPreview({
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
      });

      expect(result.triggerInfo.status).toBe('ready');
      expect(result.triggerInfo.schemaType).toBe('json');
      expect(result.triggerInfo.previewText).toContain('Telegram');
    });
  });

  describe('mockExecute', () => {
    it('should generate message output', async () => {
      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramTriggerTemplate.mockExecute(args);

      expect(result.message.status).toBe('success');
      expect(result.message.value).toBeDefined();
      expect(result.message.producedAt).toBeDefined();
      
      const message = result.message.value as TelegramMessagePayload;
      expect(message.messageId).toBeGreaterThan(0);
      expect(message.chatId).toBeDefined();
      expect(message.timestamp).toBeDefined();
    });

    it('should generate deterministic message IDs', async () => {
      const config = telegramTriggerTemplate.defaultConfig;
      
      const args1: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run-1',
      };

      const args2: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run-2',
      };

      const result1 = await telegramTriggerTemplate.mockExecute(args1);
      const result2 = await telegramTriggerTemplate.mockExecute(args2);

      // Same config should produce same mock message
      const msg1 = result1.message.value as TelegramMessagePayload;
      const msg2 = result2.message.value as TelegramMessagePayload;
      expect(msg1.messageId).toBe(msg2.messageId);
    });

    it('should extract text content', async () => {
      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramTriggerTemplate.mockExecute(args);

      expect(result.text.status).toBe('success');
      expect(typeof result.text.value === 'string').toBe(true);
      expect((result.text.value as string).length).toBeGreaterThan(0);
    });

    it('should respect maxImages limit', async () => {
      const config: TelegramTriggerConfig = {
        ...telegramTriggerTemplate.defaultConfig,
        extractImages: true,
        maxImages: 2,
      };

      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramTriggerTemplate.mockExecute(args);
      const images = result.images.value as unknown[];

      expect(images.length).toBeLessThanOrEqual(2);
    });

    it('should handle abort signal', async () => {
      const controller = new AbortController();
      controller.abort();

      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
        signal: controller.signal,
        runId: 'test-run',
      };

      await expect(telegramTriggerTemplate.mockExecute(args)).rejects.toThrow('cancelled');
    });
  });

  describe('message structure', () => {
    it('should have valid TelegramMessagePayload structure', async () => {
      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config: telegramTriggerTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramTriggerTemplate.mockExecute(args);
      const message = result.message.value as TelegramMessagePayload;

      expect(message.messageId).toBeTypeOf('number');
      expect(message.chatId).toBeTypeOf('number');
      expect(message.chatType).toMatch(/private|group|channel/);
      expect(message.timestamp).toBeTypeOf('string');
      expect(message.images).toBeInstanceOf(Array);
      expect(message.hasMedia).toBeTypeOf('boolean');
      expect(message.raw).toBeTypeOf('object');
    });

    it('should have image metadata when images present', async () => {
      const config: TelegramTriggerConfig = {
        ...telegramTriggerTemplate.defaultConfig,
        extractImages: true,
        maxImages: 3,
      };

      const args: MockNodeExecutionArgs<TelegramTriggerConfig> = {
        nodeId: 'test-node',
        config,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramTriggerTemplate.mockExecute(args);
      const message = result.message.value as TelegramMessagePayload;

      if (message.images.length > 0) {
        const firstImage = message.images[0];
        expect(firstImage.fileId).toBeTypeOf('string');
        expect(firstImage.url).toBeTypeOf('string');
      }
    });
  });
});
