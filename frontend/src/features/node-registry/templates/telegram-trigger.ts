/**
 * telegramTrigger Node Template - AiModel-618
 *
 * Purpose: Trigger node that starts a workflow from a Telegram message.
 *          Extracts images + text from the incoming message as workflow input.
 * Category: input
 *
 * Outputs:
 *   - message (telegramMessage) — structured Telegram message data
 *   - text (text) — extracted text content
 *   - images (imageAssetList) — extracted images from message
 *
 * Config:
 *   - botToken: Telegram bot token
 *   - allowedChatIds: string[] — whitelist of chat IDs (empty = allow all)
 *   - extractImages: boolean — whether to download and extract images
 *   - maxImages: number — max images to process per message
 *   - filterKeywords: string[] — optional keywords to filter messages
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Telegram Message Payload
// ============================================================

export interface TelegramImage {
  readonly fileId: string;
  readonly url: string;
  readonly width?: number;
  readonly height?: number;
  readonly caption?: string;
}

export interface TelegramMessagePayload {
  readonly messageId: number;
  readonly chatId: number;
  readonly chatType: 'private' | 'group' | 'channel';
  readonly fromUserId?: number;
  readonly fromUsername?: string;
  readonly timestamp: string;
  readonly text?: string;
  readonly caption?: string;
  readonly images: readonly TelegramImage[];
  readonly hasMedia: boolean;
  readonly entities: readonly unknown[];
  readonly raw: Record<string, unknown>;
}

// ============================================================
// Configuration Schema
// ============================================================

export const TelegramTriggerConfigSchema = z.object({
  botToken: z.string()
    .describe('Telegram bot token from @BotFather'),
  allowedChatIds: z.array(z.string())
    .describe('Whitelist of allowed chat IDs (empty = allow all)'),
  extractImages: z.boolean()
    .describe('Whether to download and extract images from messages'),
  maxImages: z.number().int().min(1).max(10)
    .describe('Maximum number of images to extract per message (1-10)'),
  filterKeywords: z.array(z.string())
    .describe('Optional keywords to filter messages (empty = no filter)'),
});

export type TelegramTriggerConfig = z.infer<typeof TelegramTriggerConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  // Trigger nodes have no inputs - they initiate workflows
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'message',
    label: 'Message',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Complete Telegram message payload with metadata',
  },
  {
    key: 'text',
    label: 'Text',
    direction: 'output',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Extracted text content from the message',
  },
  {
    key: 'images',
    label: 'Images',
    direction: 'output',
    dataType: 'imageAssetList',
    required: false,
    multiple: false,
    description: 'Extracted images from the message as asset list',
  },
  {
    key: 'triggerInfo',
    label: 'Trigger Info',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Trigger metadata (timestamp, source, etc.)',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: TelegramTriggerConfig = {
  botToken: '',
  allowedChatIds: [],
  extractImages: true,
  maxImages: 5,
  filterKeywords: [],
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

// ============================================================
// Mock Message Generator
// ============================================================

function generateMockMessage(config: TelegramTriggerConfig): TelegramMessagePayload {
  const now = new Date().toISOString();
  const seed = stableHash(JSON.stringify(config));
  
  const hasImages = config.extractImages;
  const imageCount = hasImages ? Math.min(config.maxImages, 1 + (parseInt(seed.slice(0, 2), 16) % 3)) : 0;
  
  const images: TelegramImage[] = [];
  for (let i = 0; i < imageCount; i++) {
    images.push({
      fileId: `mock_file_${seed}_${i}`,
      url: `placeholder://telegram/image_${i}.jpg`,
      width: 1080,
      height: 1080,
      caption: i === 0 ? 'Product image for analysis' : undefined,
    });
  }

  const sampleMessages = [
    'Please create a TVC for this product',
    'Need a GenZ style story for this',
    'Help me make a viral TikTok script',
    'Analyze these product photos',
    'Create marketing content for this',
    'Story idea: youth empowerment',
  ];
  
  const msgIndex = parseInt(seed.slice(2, 4), 16) % sampleMessages.length;
  const text = sampleMessages[msgIndex];

  return {
    messageId: 1000000000 + parseInt(seed.slice(0, 6), 16),
    chatId: 123456789,
    chatType: 'private',
    fromUserId: 987654321,
    fromUsername: 'creative_user',
    timestamp: now,
    text,
    caption: hasImages && images.length > 0 ? text : undefined,
    images,
    hasMedia: images.length > 0,
    entities: [],
    raw: {
      update_id: parseInt(seed.slice(0, 8), 16),
      message: {
        message_id: 1000000000 + parseInt(seed.slice(0, 6), 16),
        date: Math.floor(Date.now() / 1000),
        text,
      },
    },
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<TelegramTriggerConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config } = args;
  
  const mockMessage = generateMockMessage(config);
  
  const previewText = [
    `Chat: ${mockMessage.chatType}`,
    mockMessage.fromUsername ? `@${mockMessage.fromUsername}` : `User ${mockMessage.fromUserId}`,
    mockMessage.images.length > 0 ? `${mockMessage.images.length} images` : 'Text only',
    mockMessage.text?.substring(0, 50),
  ].filter(Boolean).join(' · ').substring(0, 200);

  return {
    message: {
      value: mockMessage,
      status: 'ready',
      schemaType: 'json',
      previewText: `Telegram message from ${mockMessage.fromUsername || 'user'}`,
      sizeBytesEstimate: JSON.stringify(mockMessage).length * 2,
    } as PortPayload<TelegramMessagePayload>,
    text: {
      value: mockMessage.text || null,
      status: mockMessage.text ? 'ready' : 'idle',
      schemaType: 'text',
      previewText: mockMessage.text?.substring(0, 100) || 'No text',
    } as PortPayload,
    images: {
      value: mockMessage.images.length > 0 ? mockMessage.images : null,
      status: mockMessage.images.length > 0 ? 'ready' : 'idle',
      schemaType: 'imageAssetList',
      previewText: mockMessage.images.length > 0 ? `${mockMessage.images.length} images` : 'No images',
    } as PortPayload,
    triggerInfo: {
      value: {
        source: 'telegram',
        triggeredAt: mockMessage.timestamp,
        triggerType: 'message',
        chatId: mockMessage.chatId,
      },
      status: 'ready',
      schemaType: 'json',
      previewText: `Telegram · ${mockMessage.timestamp}`,
    } as PortPayload,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<TelegramTriggerConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  // Simulate webhook processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const mockMessage = generateMockMessage(config);
  const now = new Date().toISOString();

  return {
    message: {
      value: mockMessage,
      status: 'success',
      schemaType: 'json',
      previewText: `Message ${mockMessage.messageId} from @${mockMessage.fromUsername || 'user'}`,
      sizeBytesEstimate: JSON.stringify(mockMessage).length * 2,
      producedAt: now,
    } as PortPayload<TelegramMessagePayload>,
    text: {
      value: mockMessage.text || null,
      status: mockMessage.text ? 'success' : 'idle',
      schemaType: 'text',
      previewText: mockMessage.text?.substring(0, 100) || 'No text content',
      producedAt: now,
    } as PortPayload,
    images: {
      value: mockMessage.images.length > 0 ? mockMessage.images : null,
      status: mockMessage.images.length > 0 ? 'success' : 'idle',
      schemaType: 'imageAssetList',
      previewText: mockMessage.images.length > 0 
        ? `${mockMessage.images.length} image(s) extracted`
        : 'No images in message',
      producedAt: now,
    } as PortPayload,
    triggerInfo: {
      value: {
        source: 'telegram',
        triggeredAt: now,
        triggerType: 'message',
        chatId: mockMessage.chatId,
        messageId: mockMessage.messageId,
        processed: true,
      },
      status: 'success',
      schemaType: 'json',
      previewText: `Telegram trigger · ${now}`,
      producedAt: now,
    } as PortPayload,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<TelegramTriggerConfig>[] = [
  {
    id: 'telegram-text-only',
    label: 'Text Message Trigger',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      allowedChatIds: [],
      extractImages: false,
      maxImages: 1,
      filterKeywords: [],
    },
    previewInputs: {},
  },
  {
    id: 'telegram-with-images',
    label: 'Image + Text Trigger',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      allowedChatIds: [],
      extractImages: true,
      maxImages: 5,
      filterKeywords: ['product', 'create', 'help'],
    },
    previewInputs: {},
  },
  {
    id: 'telegram-restricted',
    label: 'Restricted Access (Whitelist)',
    config: {
      botToken: 'YOUR_BOT_TOKEN_HERE',
      allowedChatIds: ['123456789', '987654321'],
      extractImages: true,
      maxImages: 3,
      filterKeywords: [],
    },
    previewInputs: {},
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * telegramTrigger Node Template
 *
 * Trigger node: starts workflow execution from Telegram messages.
 * Extracts text and images for downstream processing.
 * Requires Telegram Bot API integration with webhook endpoint.
 */
export const telegramTriggerTemplate: NodeTemplate<TelegramTriggerConfig> = {
  type: 'telegramTrigger',
  templateVersion: '1.0.0',
  title: 'Telegram Trigger',
  category: 'input',
  description: 'Starts workflow from Telegram message. Extracts text and images for downstream processing. Configurable chat whitelist, keyword filters, and image extraction.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: TelegramTriggerConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
