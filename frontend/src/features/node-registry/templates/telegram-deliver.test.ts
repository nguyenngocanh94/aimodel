/**
 * TelegramDeliver Node Template Tests - AiModel-618
 */

import { describe, it, expect } from 'vitest';
import {
  telegramDeliverTemplate,
  TelegramDeliverConfigSchema,
  type TelegramDeliverConfig,
  type TelegramDeliveryResult,
} from './telegram-deliver';
import type { MockNodeExecutionArgs } from '../node-registry';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('telegramDeliverTemplate', () => {
  describe('template metadata', () => {
    it('should have correct type and category', () => {
      expect(telegramDeliverTemplate.type).toBe('telegramDeliver');
      expect(telegramDeliverTemplate.category).toBe('output');
      expect(telegramDeliverTemplate.executable).toBe(true);
      expect(telegramDeliverTemplate.templateVersion).toBe('1.0.0');
    });

    it('should have required inputs', () => {
      expect(telegramDeliverTemplate.inputs).toHaveLength(2);
      
      const inputKeys = telegramDeliverTemplate.inputs.map(i => i.key);
      expect(inputKeys).toContain('content');
      expect(inputKeys).toContain('chatId');
    });

    it('should mark content as required', () => {
      const content = telegramDeliverTemplate.inputs.find(i => i.key === 'content');
      expect(content?.required).toBe(true);
    });

    it('should have single output', () => {
      expect(telegramDeliverTemplate.outputs).toHaveLength(1);
      expect(telegramDeliverTemplate.outputs[0].key).toBe('deliveryResult');
    });
  });

  describe('config schema', () => {
    it('should validate correct config', () => {
      const validConfig: TelegramDeliverConfig = {
        botToken: 'test_token',
        defaultChatId: '123456789',
        messageFormat: 'markdown',
        includeTimestamp: true,
        notifyOnSuccess: true,
        maxMessageLength: 4096,
      };

      const result = TelegramDeliverConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should reject invalid message format', () => {
      const invalidConfig = {
        botToken: 'test',
        defaultChatId: '123',
        messageFormat: 'invalid_format',
        includeTimestamp: true,
        notifyOnSuccess: true,
        maxMessageLength: 1000,
      };

      const result = TelegramDeliverConfigSchema.safeParse(invalidConfig);
      expect(result.success).toBe(false);
    });

    it('should reject message length outside range', () => {
      const invalidConfig = {
        botToken: 'test',
        defaultChatId: '123',
        messageFormat: 'text',
        includeTimestamp: true,
        notifyOnSuccess: true,
        maxMessageLength: 5000, // too high
      };

      const result = TelegramDeliverConfigSchema.safeParse(invalidConfig);
      expect(result.success).toBe(false);
    });
  });

  describe('fixtures', () => {
    it('should have at least one fixture', () => {
      expect(telegramDeliverTemplate.fixtures.length).toBeGreaterThan(0);
    });

    it('should have valid fixture configs', () => {
      for (const fixture of telegramDeliverTemplate.fixtures) {
        const result = TelegramDeliverConfigSchema.safeParse(fixture.config);
        expect(result.success).toBe(true);
      }
    });
  });

  describe('buildPreview', () => {
    it('should return idle when content missing', () => {
      const result = telegramDeliverTemplate.buildPreview({
        config: telegramDeliverTemplate.defaultConfig,
        inputs: {},
      });

      expect(result.deliveryResult.status).toBe('idle');
      expect(result.deliveryResult.value).toBeNull();
    });

    it('should generate preview with text content', () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Hello, this is a test message',
          status: 'success',
          schemaType: 'text',
        },
      };

      const result = telegramDeliverTemplate.buildPreview({
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
      });

      expect(result.deliveryResult.status).toBe('ready');
      expect(result.deliveryResult.value).toBeDefined();
      
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.success).toBe(true);
      expect(deliveryResult.contentType).toBe('text');
    });

    it('should use dynamic chat ID when provided', () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Test content',
          status: 'success',
          schemaType: 'text',
        },
        chatId: {
          value: '987654321',
          status: 'success',
          schemaType: 'text',
        },
      };

      const result = telegramDeliverTemplate.buildPreview({
        config: {
          ...telegramDeliverTemplate.defaultConfig,
          defaultChatId: '123456789',
        },
        inputs,
      });

      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.chatId).toBe(987654321);
    });

    it('should use default chat ID when dynamic not provided', () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Test content',
          status: 'success',
          schemaType: 'text',
        },
      };

      const result = telegramDeliverTemplate.buildPreview({
        config: {
          ...telegramDeliverTemplate.defaultConfig,
          defaultChatId: '123456789',
        },
        inputs,
      });

      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.chatId).toBe(123456789);
    });

    it('should detect photo content type', () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: {
            url: 'https://example.com/image.jpg',
            type: 'image',
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const result = telegramDeliverTemplate.buildPreview({
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
      });

      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.contentType).toBe('photo');
    });

    it('should detect video content type', () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: {
            videoUrl: 'https://example.com/video.mp4',
            type: 'video',
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const result = telegramDeliverTemplate.buildPreview({
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
      });

      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.contentType).toBe('video');
    });
  });

  describe('mockExecute', () => {
    it('should return error when content missing', async () => {
      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config: telegramDeliverTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);

      expect(result.deliveryResult.status).toBe('error');
      expect(result.deliveryResult.errorMessage).toContain('Missing required');
    });

    it('should deliver text content', async () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Test message for delivery',
          status: 'success',
          schemaType: 'text',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);

      expect(result.deliveryResult.status).toBe('success');
      expect(result.deliveryResult.producedAt).toBeDefined();
      
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.success).toBe(true);
      expect(deliveryResult.messageId).toBeDefined();
      expect(deliveryResult.contentType).toBe('text');
      expect(deliveryResult.deliveryMethod).toBe('message');
    });

    it('should deliver structured report content', async () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: {
            storyArc: {
              title: 'Test Story',
              shots: 5,
              formula: "Hero's Journey",
            },
            status: 'complete',
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);

      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      expect(deliveryResult.success).toBe(true);
      expect(deliveryResult.contentType).toBe('report');
    });

    it('should include timestamp when configured', async () => {
      const config: TelegramDeliverConfig = {
        ...telegramDeliverTemplate.defaultConfig,
        includeTimestamp: true,
        messageFormat: 'text',
      };

      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Test message',
          status: 'success',
          schemaType: 'text',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      
      // The formatted content should include timestamp
      expect(deliveryResult.contentPreview).toContain('2026');
    });

    it('should respect max message length', async () => {
      const config: TelegramDeliverConfig = {
        ...telegramDeliverTemplate.defaultConfig,
        maxMessageLength: 100,
        messageFormat: 'text',
        includeTimestamp: false,
      };

      const longContent = 'A'.repeat(500);
      const inputs: Record<string, PortPayload> = {
        content: {
          value: longContent,
          status: 'success',
          schemaType: 'text',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      
      expect(deliveryResult.contentPreview.length).toBeLessThanOrEqual(100);
    });

    it('should use media group delivery for photos', async () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: {
            url: 'https://example.com/photo.jpg',
            type: 'image',
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      
      expect(deliveryResult.contentType).toBe('photo');
      expect(deliveryResult.deliveryMethod).toBe('media_group');
    });

    it('should handle abort signal', async () => {
      const controller = new AbortController();
      controller.abort();

      const inputs: Record<string, PortPayload> = {
        content: {
          value: 'Test',
          status: 'success',
          schemaType: 'text',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config: telegramDeliverTemplate.defaultConfig,
        inputs,
        signal: controller.signal,
        runId: 'test-run',
      };

      await expect(telegramDeliverTemplate.mockExecute(args)).rejects.toThrow('cancelled');
    });
  });

  describe('format modes', () => {
    it('should format as markdown', async () => {
      const config: TelegramDeliverConfig = {
        ...telegramDeliverTemplate.defaultConfig,
        messageFormat: 'markdown',
      };

      const inputs: Record<string, PortPayload> = {
        content: {
          value: {
            title: 'Story Complete',
            shots: 5,
          },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      
      expect(deliveryResult.contentPreview).toContain('#');
    });

    it('should format as plain text', async () => {
      const config: TelegramDeliverConfig = {
        ...telegramDeliverTemplate.defaultConfig,
        messageFormat: 'text',
        includeTimestamp: false,
      };

      const inputs: Record<string, PortPayload> = {
        content: {
          value: { title: 'Test', value: 123 },
          status: 'success',
          schemaType: 'json',
        },
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result = await telegramDeliverTemplate.mockExecute(args);
      const deliveryResult = result.deliveryResult.value as TelegramDeliveryResult;
      
      // Plain text shouldn't have markdown
      expect(deliveryResult.contentPreview).not.toContain('# ');
    });
  });

  describe('deterministic behavior', () => {
    it('should produce consistent results for same input', async () => {
      const inputs: Record<string, PortPayload> = {
        content: {
          value: { test: 'consistent' },
          status: 'success',
          schemaType: 'json',
        },
      };

      const config: TelegramDeliverConfig = {
        ...telegramDeliverTemplate.defaultConfig,
        includeTimestamp: false, // Disable timestamp for determinism test
      };

      const args: MockNodeExecutionArgs<TelegramDeliverConfig> = {
        nodeId: 'test-node',
        config,
        inputs,
        signal: new AbortController().signal,
        runId: 'test-run',
      };

      const result1 = await telegramDeliverTemplate.mockExecute(args);
      const result2 = await telegramDeliverTemplate.mockExecute(args);

      const delivery1 = result1.deliveryResult.value as TelegramDeliveryResult;
      const delivery2 = result2.deliveryResult.value as TelegramDeliveryResult;

      // Same content should produce same preview (without timestamp)
      expect(delivery1.contentPreview).toBe(delivery2.contentPreview);
    });
  });
});
