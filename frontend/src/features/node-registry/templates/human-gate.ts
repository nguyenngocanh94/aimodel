/**
 * humanGate Node Template
 *
 * Purpose: Universal pause/resume mechanism for workflows that can interact
 * with external channels (Telegram, MCP, UI).
 * Category: utility
 *
 * Inputs: data (json, required) — the data to present to the human/AI
 * Outputs: response (json) — the response from the human/AI
 *
 * Config:
 * - messageTemplate: string (template for the message, supports {{variable}} placeholders)
 * - channel: 'ui' | 'telegram' | 'mcp' | 'any' (which channel to notify)
 * - timeoutSeconds: number (0 = wait forever, max 86400)
 * - autoFallbackResponse: string | null (JSON response if timeout reached)
 * - options: string[] | null (predefined options to present)
 *
 * Behavior: Pauses execution and waits for external response.
 * In mock mode, simulates a response after a short delay.
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const HumanGateConfigSchema = z.object({
  messageTemplate: z.string().max(2000)
    .describe('Template for the message to send, supports {{variable}} placeholders'),
  channel: z.enum(['ui', 'telegram', 'mcp', 'any'])
    .describe('Which channel to notify'),
  timeoutSeconds: z.number().int().min(0).max(86400)
    .describe('How long to wait before auto-fallback. 0 = wait forever'),
  autoFallbackResponse: z.string().nullable()
    .describe('JSON response to use if timeout is reached'),
  options: z.array(z.string()).nullable()
    .describe('Predefined options to present (e.g., ["A", "B", "C", "D"])'),
});

export type HumanGateConfig = z.infer<typeof HumanGateConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'data',
    label: 'Data',
    direction: 'input',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'The data to present to the human or AI for review',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'response',
    label: 'Response',
    direction: 'output',
    dataType: 'json',
    required: false,
    multiple: false,
    description: 'The response from the human or AI',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: HumanGateConfig = {
  messageTemplate: '',
  channel: 'ui',
  timeoutSeconds: 0,
  autoFallbackResponse: null,
  options: null,
};

// ============================================================
// Helper: Render Message Template
// ============================================================

function renderMessageTemplate(template: string, data: Record<string, unknown>): string {
  if (!template) return '';

  return template.replace(/\{\{(\w+)\}\}/g, (_match, key: string) => {
    if (!(key in data)) return `{{${key}}}`;
    const value = data[key];
    return typeof value === 'string' ? value : JSON.stringify(value);
  });
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<HumanGateConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs: inputsRecord } = args;

  const dataPayload = inputsRecord.data;

  if (!dataPayload || dataPayload.value === null || dataPayload.value === undefined) {
    return {
      response: {
        value: null,
        status: 'idle',
        schemaType: 'json',
        previewText: `Waiting for input data... (channel: ${config.channel})`,
      } as PortPayload,
    };
  }

  const message = renderMessageTemplate(
    config.messageTemplate,
    typeof dataPayload.value === 'object' && dataPayload.value !== null
      ? (dataPayload.value as Record<string, unknown>)
      : {},
  );

  return {
    response: {
      value: null,
      status: 'idle',
      schemaType: 'json',
      previewText: message
        ? `Gate paused: ${message.substring(0, 100)}`
        : `Gate paused, awaiting response (channel: ${config.channel})`,
    } as PortPayload,
  };
}

// ============================================================
// Mock Execute (simulates human response)
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<HumanGateConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs: inputsRecord, signal, nodeId } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const dataPayload = inputsRecord.data;

  if (!dataPayload || dataPayload.value === null || dataPayload.value === undefined) {
    return {
      response: {
        value: null,
        status: 'error',
        schemaType: 'json',
        errorMessage: 'Missing required data input',
      } as PortPayload,
    };
  }

  // Simulate human review latency
  await new Promise(resolve => setTimeout(resolve, 80));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const now = new Date().toISOString();

  // In mock mode, auto-respond with the first option or a default response
  let mockResponse: unknown;
  if (config.options && config.options.length > 0) {
    mockResponse = { choice: config.options[0], respondedAt: now };
  } else if (config.autoFallbackResponse) {
    try {
      mockResponse = JSON.parse(config.autoFallbackResponse);
    } catch {
      mockResponse = { text: config.autoFallbackResponse, respondedAt: now };
    }
  } else {
    mockResponse = { approved: true, respondedAt: now, source: 'mock' };
  }

  const valueStr = JSON.stringify(mockResponse);

  return {
    response: {
      value: mockResponse,
      status: 'success',
      schemaType: 'json',
      previewText: `Response received (${config.channel}): ${valueStr.substring(0, 120)}`,
      sizeBytesEstimate: valueStr.length * 2,
      producedAt: now,
    } as PortPayload,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleData: PortPayload = {
  value: {
    question: 'Which story direction should we take?',
    context: 'The hero has reached a crossroads.',
    storyOptions: ['A: Go north', 'B: Go south', 'C: Stay and rest', 'D: Turn back'],
  },
  status: 'success',
  schemaType: 'json',
};

const fixtures: readonly NodeFixture<HumanGateConfig>[] = [
  {
    id: 'human-gate-ui',
    label: 'Human Gate (UI Channel)',
    config: {
      messageTemplate: 'Please choose a direction: {{question}}',
      channel: 'ui',
      timeoutSeconds: 0,
      autoFallbackResponse: null,
      options: ['A', 'B', 'C', 'D'],
    },
    previewInputs: { data: sampleData },
  },
  {
    id: 'human-gate-telegram',
    label: 'Human Gate (Telegram with Timeout)',
    config: {
      messageTemplate: '{{question}} — Context: {{context}}',
      channel: 'telegram',
      timeoutSeconds: 3600,
      autoFallbackResponse: '{"choice": "A", "reason": "auto-fallback"}',
      options: null,
    },
    previewInputs: { data: sampleData },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * humanGate Node Template
 *
 * Executable: pauses workflow execution and waits for an external response.
 * In mock mode, auto-responds with the first option or a default response.
 */
export const humanGateTemplate: NodeTemplate<HumanGateConfig> = {
  type: 'humanGate',
  templateVersion: '1.0.0',
  title: 'Human Gate',
  category: 'utility',
  description: 'Pauses workflow execution, sends data to an external channel, and waits for a human or AI response before resuming. Supports UI, Telegram, MCP, and any channel. In mock execution mode, auto-responds with the first option or a default response.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: HumanGateConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
