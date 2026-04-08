/**
 * telegramDeliver Node Template - AiModel-618
 *
 * Purpose: Delivery node that sends workflow output back to Telegram.
 *          Can send text, images, videos, documents, or reports.
 * Category: output
 *
 * Inputs:
 *   - content (json) — required — content to deliver (text, media, report)
 *   - chatId (text) — optional — target chat ID (defaults to config)
 *
 * Config:
 *   - botToken: Telegram bot token
 *   - defaultChatId: string — default chat ID to send to
 *   - messageFormat: 'text' | 'markdown' | 'html' | 'structured' — format mode
 *   - includeTimestamp: boolean — add timestamp to message
 *   - notifyOnSuccess: boolean — send notification when delivered
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Delivery Result Payload
// ============================================================

export interface TelegramDeliveryResult {
  readonly success: boolean;
  readonly messageId?: number;
  readonly chatId: number;
  readonly timestamp: string;
  readonly contentType: 'text' | 'photo' | 'video' | 'document' | 'report';
  readonly contentPreview: string;
  readonly deliveryMethod: 'message' | 'media_group' | 'document';
  readonly error?: string;
}

// ============================================================
// Configuration Schema
// ============================================================

export const TelegramDeliverConfigSchema = z.object({
  botToken: z.string()
    .describe('Telegram bot token from @BotFather'),
  defaultChatId: z.string()
    .describe('Default chat ID to send messages to'),
  messageFormat: z.enum(['text', 'markdown', 'html', 'structured'])
    .describe('Message format mode'),
  includeTimestamp: z.boolean()
    .describe('Include timestamp in delivered message'),
  notifyOnSuccess: z.boolean()
    .describe('Send notification when delivery succeeds'),
  maxMessageLength: z.number().int().min(100).max(4096)
    .describe('Max message length (100-4096 characters)'),
});

export type TelegramDeliverConfig = z.infer<typeof TelegramDeliverConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'content',
    label: 'Content',
    direction: 'input',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Content to deliver: text, media assets, or structured report',
  },
  {
    key: 'chatId',
    label: 'Chat ID',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Target Telegram chat ID (overrides default)',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'deliveryResult',
    label: 'Delivery Result',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Delivery confirmation with message ID and metadata',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: TelegramDeliverConfig = {
  botToken: '',
  defaultChatId: '',
  messageFormat: 'structured',
  includeTimestamp: true,
  notifyOnSuccess: true,
  maxMessageLength: 4096,
};

// ============================================================
// Deterministic Helpers
// ============================================================

function stableHash(input: string): string {
  let h = 5381;
  for (let i = 0; i < input.length; i++) {
    h = Math.imul(31, h) + input.charCodeAt(i);
  }
  return (h >>> 0).toString(16).padStart(8, '0');
}

function determineContentType(content: unknown): TelegramDeliveryResult['contentType'] {
  if (typeof content === 'string') {
    return 'text';
  }
  
  if (content && typeof content === 'object') {
    const obj = content as Record<string, unknown>;
    
    // Check for media assets
    if (obj.url || obj.fileUrl || obj.videoUrl || obj.imageUrl) {
      if (obj.videoUrl || (obj.type === 'video')) {
        return 'video';
      }
      if (obj.imageUrl || obj.url || (obj.type === 'image')) {
        return 'photo';
      }
      return 'document';
    }
    
    // Check for structured report indicators
    if (obj.storyArc || obj.script || obj.analysis || obj.report) {
      return 'report';
    }
    
    // Check for array of assets
    if (Array.isArray(obj.images) || Array.isArray(obj.assets)) {
      return 'photo';
    }
  }
  
  return 'text';
}

function formatContentForDelivery(args: {
  content: unknown;
  format: TelegramDeliverConfig['messageFormat'];
  includeTimestamp: boolean;
  maxLength: number;
}): string {
  const { content, format, includeTimestamp, maxLength } = args;
  
  let formatted = '';
  
  if (typeof content === 'string') {
    formatted = content;
  } else if (content && typeof content === 'object') {
    switch (format) {
      case 'structured':
        formatted = JSON.stringify(content, null, 2);
        break;
      case 'markdown':
        formatted = formatAsMarkdown(content);
        break;
      case 'html':
        formatted = formatAsHtml(content);
        break;
      case 'text':
      default:
        formatted = formatAsPlainText(content);
        break;
    }
  } else {
    formatted = String(content);
  }
  
  if (includeTimestamp) {
    const timestamp = new Date().toISOString();
    formatted = `[${timestamp}]\n\n${formatted}`;
  }
  
  return formatted.slice(0, maxLength);
}

function formatAsMarkdown(content: unknown): string {
  if (!content || typeof content !== 'object') {
    return String(content);
  }
  
  const parts: string[] = [];
  const obj = content as Record<string, unknown>;
  
  if (obj.title || obj.name) {
    parts.push(`# ${obj.title || obj.name}\n`);
  }
  
  for (const [key, value] of Object.entries(obj)) {
    if (key === 'title' || key === 'name') continue;
    
    const displayKey = key.replace(/([A-Z])/g, ' $1').toLowerCase();
    
    if (Array.isArray(value)) {
      parts.push(`## ${displayKey}\n`);
      value.forEach((item, i) => {
        parts.push(`${i + 1}. ${JSON.stringify(item)}\n`);
      });
    } else if (typeof value === 'object' && value !== null) {
      parts.push(`## ${displayKey}\n`);
      parts.push(formatAsMarkdown(value));
    } else {
      parts.push(`**${displayKey}:** ${value}\n`);
    }
  }
  
  return parts.join('\n');
}

function formatAsHtml(content: unknown): string {
  const markdown = formatAsMarkdown(content);
  // Simple markdown to HTML conversion
  return markdown
    .replace(/^# (.+)$/gm, '<h1>$1</h1>')
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br/>');
}

function formatAsPlainText(content: unknown): string {
  if (!content || typeof content !== 'object') {
    return String(content);
  }
  
  return JSON.stringify(content, null, 2)
    .replace(/[{\[\]}"]/g, '')
    .replace(/,/g, '')
    .trim();
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<TelegramDeliverConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;
  
  const contentPayload = inputs.content;
  const chatIdPayload = inputs.chatId;
  
  if (!contentPayload || contentPayload.value === null || contentPayload.value === undefined) {
    return {
      deliveryResult: {
        value: null,
        status: 'idle',
        schemaType: 'json',
        previewText: 'Waiting for content to deliver...',
      } as PortPayload,
    };
  }
  
  const content = contentPayload.value;
  const contentType = determineContentType(content);
  const targetChatId = chatIdPayload?.value ? String(chatIdPayload.value) : config.defaultChatId;
  
  const formattedPreview = formatContentForDelivery({
    content,
    format: config.messageFormat,
    includeTimestamp: config.includeTimestamp,
    maxLength: 200,
  });
  
  const previewResult: TelegramDeliveryResult = {
    success: true,
    chatId: parseInt(targetChatId) || 0,
    timestamp: new Date().toISOString(),
    contentType,
    contentPreview: formattedPreview,
    deliveryMethod: contentType === 'photo' || contentType === 'video' ? 'media_group' : 'message',
  };
  
  return {
    deliveryResult: {
      value: previewResult,
      status: 'ready',
      schemaType: 'json',
      previewText: `${contentType} → Chat ${targetChatId || 'default'}`,
      sizeBytesEstimate: JSON.stringify(previewResult).length * 2,
    } as PortPayload<TelegramDeliveryResult>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<TelegramDeliverConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const contentPayload = inputs.content;
  
  if (!contentPayload || contentPayload.value === null || contentPayload.value === undefined) {
    return {
      deliveryResult: {
        value: null,
        status: 'error',
        schemaType: 'json',
        errorMessage: 'Missing required input: content',
      } as PortPayload,
    };
  }

  // Simulate API call latency
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const content = contentPayload.value;
  const contentType = determineContentType(content);
  const chatIdPayload = inputs.chatId;
  const targetChatId = chatIdPayload?.value 
    ? String(chatIdPayload.value) 
    : config.defaultChatId;
  
  const now = new Date().toISOString();
  const seed = stableHash(JSON.stringify({ content, targetChatId, now }));
  
  const formattedContent = formatContentForDelivery({
    content,
    format: config.messageFormat,
    includeTimestamp: config.includeTimestamp,
    maxLength: config.maxMessageLength,
  });

  const result: TelegramDeliveryResult = {
    success: true,
    messageId: 1000000 + parseInt(seed.slice(0, 6), 16),
    chatId: parseInt(targetChatId) || 0,
    timestamp: now,
    contentType,
    contentPreview: formattedContent.slice(0, 200),
    deliveryMethod: contentType === 'photo' || contentType === 'video' ? 'media_group' : 'message',
  };

  return {
    deliveryResult: {
      value: result,
      status: 'success',
      schemaType: 'json',
      previewText: `Delivered ${contentType} to ${targetChatId || 'default'}`,
      sizeBytesEstimate: JSON.stringify(result).length * 2,
      producedAt: now,
    } as PortPayload<TelegramDeliveryResult>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<TelegramDeliverConfig>[] = [
  {
    id: 'deliver-text-report',
    label: 'Text Report Delivery',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      defaultChatId: '123456789',
      messageFormat: 'text',
      includeTimestamp: true,
      notifyOnSuccess: true,
      maxMessageLength: 4096,
    },
    previewInputs: {
      content: {
        value: {
          reportTitle: 'TVC Story Analysis',
          summary: 'Analysis of 3 competing story concepts',
          recommendation: 'Story B has highest viral potential',
        },
        status: 'success',
        schemaType: 'json',
      },
    },
  },
  {
    id: 'deliver-structured-markdown',
    label: 'Markdown Report',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      defaultChatId: '123456789',
      messageFormat: 'markdown',
      includeTimestamp: true,
      notifyOnSuccess: false,
      maxMessageLength: 4096,
    },
    previewInputs: {
      content: {
        value: {
          title: 'Story Arc Complete',
          storyFormula: "Hero's Journey",
          shots: 5,
          duration: 30,
          castSelected: 2,
        },
        status: 'success',
        schemaType: 'json',
      },
    },
  },
  {
    id: 'deliver-to-dynamic-chat',
    label: 'Dynamic Chat ID',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      defaultChatId: '',
      messageFormat: 'structured',
      includeTimestamp: true,
      notifyOnSuccess: true,
      maxMessageLength: 4096,
    },
    previewInputs: {
      content: {
        value: { status: 'Workflow completed successfully!' },
        status: 'success',
        schemaType: 'json',
      },
      chatId: {
        value: '987654321',
        status: 'success',
        schemaType: 'text',
      },
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * telegramDeliver Node Template
 *
 * Delivery node: sends workflow output back to Telegram.
 * Supports text, markdown, HTML, and structured formats.
 * Can deliver to configured default chat or dynamic chat ID.
 */
export const telegramDeliverTemplate: NodeTemplate<TelegramDeliverConfig> = {
  type: 'telegramDeliver',
  templateVersion: '1.0.0',
  title: 'Telegram Deliver',
  category: 'output',
  description: 'Sends workflow output to Telegram. Supports text, markdown, HTML, and structured formats. Can target default or dynamic chat ID.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: TelegramDeliverConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
