/**
 * diverge Node Template - AiModel-625
 *
 * Purpose: Generic fan-out node for parallel multi-LLM compete pattern.
 *          Sends same input to N parallel LLM providers (Claude, GPT, Gemini, Grok),
 *          collects all outputs, presents for selection via HumanGate or AI selector.
 *          Reusable for any creative step. First use case: StoryWriter compete.
 * Category: utility
 *
 * Inputs:
 *   - input (any) — required — the input payload to process in parallel
 *   - instruction (text) — optional — specific instruction for processing
 *
 * Outputs:
 *   - variants (variantList) — collected outputs from all providers
 *
 * Config:
 *   - providers: which LLM providers to use (claude, gpt, gemini, grok)
 *   - taskType: what kind of processing (creative, analytical, technical, summary)
 *   - aggregationMode: 'all' | 'best' | 'merge' — how to present results
 *   - timeoutSeconds: max wait time for all providers
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const DivergeConfigSchema = z.object({
  providers: z.array(z.enum(['claude', 'gpt', 'gemini', 'grok']))
    .min(2)
    .max(4)
    .describe('LLM providers to use in parallel (2-4 providers)'),
  taskType: z.enum(['creative', 'analytical', 'technical', 'summary', 'review'])
    .describe('Type of processing task'),
  aggregationMode: z.enum(['all', 'best', 'merge'])
    .describe('How to aggregate results: all=return all, best=return top-rated, merge=try to merge'),
  timeoutSeconds: z.number().int().min(5).max(300)
    .describe('Max wait time in seconds for all providers (5-300)'),
});

export type DivergeConfig = z.infer<typeof DivergeConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

export interface VariantItem {
  readonly provider: string;
  readonly result: unknown;
  readonly confidence: number;
  readonly latencyMs: number;
  readonly tokensUsed: number;
}

export interface VariantListValue {
  readonly variants: readonly VariantItem[];
  readonly count: number;
  readonly taskType: string;
  readonly aggregationMode: string;
  readonly completedAt: string;
}

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'input',
    label: 'Input',
    direction: 'input',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Input payload to process in parallel across multiple LLM providers',
  },
  {
    key: 'instruction',
    label: 'Instruction',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Specific instruction for how to process the input (e.g., "Write a creative story")',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'variants',
    label: 'Variants',
    direction: 'output',
    dataType: 'variantList',
    required: true,
    multiple: false,
    description: 'Collected outputs from all parallel LLM providers, ready for selection',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: DivergeConfig = {
  providers: ['claude', 'gpt'],
  taskType: 'creative',
  aggregationMode: 'all',
  timeoutSeconds: 60,
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

function generateMockResult(provider: string, taskType: string, inputHash: string, instruction: string): unknown {
  const hashSuffix = inputHash.slice(0, 4);
  
  switch (taskType) {
    case 'creative':
      return {
        story: `Creative story from ${provider} [${hashSuffix}]: A compelling narrative crafted by the ${provider} model, featuring unique character perspectives and engaging plot twists.`,
        tone: 'engaging',
        wordCount: 150 + (hashSuffix.charCodeAt(0) % 100),
      };
    case 'analytical':
      return {
        analysis: `Analysis from ${provider} [${hashSuffix}]: Comprehensive breakdown with key insights and data-driven recommendations.`,
        keyPoints: ['Point A', 'Point B', 'Point C'],
        confidence: 0.85 + (hashSuffix.charCodeAt(1) % 10) / 100,
      };
    case 'technical':
      return {
        solution: `Technical solution from ${provider} [${hashSuffix}]: Step-by-step implementation guide with code examples and best practices.`,
        complexity: 'medium',
        estimatedTime: '2-4 hours',
      };
    case 'summary':
      return {
        summary: `Summary from ${provider} [${hashSuffix}]: Concise overview capturing essential points.`,
        bulletPoints: ['Key insight 1', 'Key insight 2', 'Key insight 3'],
        length: 'short',
      };
    case 'review':
      return {
        review: `Review from ${provider} [${hashSuffix}]: Critical assessment with strengths, weaknesses, and improvement suggestions.`,
        rating: 4 + (hashSuffix.charCodeAt(2) % 2),
        verdict: 'approve-with-changes',
      };
    default:
      return { result: `Output from ${provider} [${hashSuffix}]` };
  }
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<DivergeConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const inputPayload = inputs.input;
  if (!inputPayload || inputPayload.value === null || inputPayload.value === undefined) {
    return {
      variants: {
        value: null,
        status: 'idle',
        schemaType: 'variantList',
        previewText: 'Waiting for input data...',
      } as PortPayload,
    };
  }

  const inputValue = inputPayload.value;
  const instruction = inputs.instruction?.value ? String(inputs.instruction.value) : '';
  const inputHash = stableHash(JSON.stringify({ inputValue, instruction, config }));

  const variants: VariantItem[] = config.providers.map((provider, index) => ({
    provider,
    result: generateMockResult(provider, config.taskType, inputHash + index, instruction),
    confidence: 0.8 + (index * 0.05),
    latencyMs: 1200 + (index * 300),
    tokensUsed: 450 + (index * 50),
  }));

  const variantList: VariantListValue = {
    variants,
    count: variants.length,
    taskType: config.taskType,
    aggregationMode: config.aggregationMode,
    completedAt: new Date().toISOString(),
  };

  const previewText = [
    `${variants.length} providers`,
    config.taskType,
    config.aggregationMode,
    ...variants.map(v => v.provider),
  ].join(' · ');

  return {
    variants: {
      value: variantList,
      status: 'ready',
      schemaType: 'variantList',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(variantList).length * 2,
    } as PortPayload<VariantListValue>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<DivergeConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const inputPayload = inputs.input;
  if (!inputPayload || inputPayload.value === null || inputPayload.value === undefined) {
    return {
      variants: {
        value: null,
        status: 'error',
        schemaType: 'variantList',
        errorMessage: 'Missing required input data',
      } as PortPayload,
    };
  }

  // Simulate parallel processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const inputValue = inputPayload.value;
  const instruction = inputs.instruction?.value ? String(inputs.instruction.value) : '';
  const inputHash = stableHash(JSON.stringify({ inputValue, instruction, config }));

  const variants: VariantItem[] = config.providers.map((provider, index) => ({
    provider,
    result: generateMockResult(provider, config.taskType, inputHash + index, instruction),
    confidence: 0.8 + (index * 0.05),
    latencyMs: 1200 + (index * 300),
    tokensUsed: 450 + (index * 50),
  }));

  const variantList: VariantListValue = {
    variants,
    count: variants.length,
    taskType: config.taskType,
    aggregationMode: config.aggregationMode,
    completedAt: new Date().toISOString(),
  };

  const previewText = [
    `${variants.length} providers completed`,
    config.taskType,
    config.aggregationMode,
  ].join(' · ');

  return {
    variants: {
      value: variantList,
      status: 'success',
      schemaType: 'variantList',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(variantList).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<VariantListValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleProductData: PortPayload = {
  value: {
    productName: 'Summer Floral Dress',
    productCategory: 'fashion',
    description: 'Elegant summer dress with floral print, perfect for beach weddings',
    images: [{ url: 'placeholder://image/dress.jpg', index: 0, filename: 'dress.jpg' }],
    imageCount: 1,
    source: 'upload',
    inputAt: new Date().toISOString(),
  },
  status: 'success',
  schemaType: 'productData',
};

const fixtures: readonly NodeFixture<DivergeConfig>[] = [
  {
    id: 'creative-duo',
    label: 'Creative - Claude + GPT',
    config: {
      providers: ['claude', 'gpt'],
      taskType: 'creative',
      aggregationMode: 'all',
      timeoutSeconds: 60,
    },
    previewInputs: {
      input: sampleProductData,
      instruction: {
        value: 'Write a compelling product story for GenZ audience',
        status: 'success',
        schemaType: 'text',
      },
    },
  },
  {
    id: 'full-compete',
    label: 'Full Compete - All 4 Providers',
    config: {
      providers: ['claude', 'gpt', 'gemini', 'grok'],
      taskType: 'creative',
      aggregationMode: 'all',
      timeoutSeconds: 120,
    },
    previewInputs: {
      input: sampleProductData,
      instruction: {
        value: 'Create a viral TikTok script concept',
        status: 'success',
        schemaType: 'text',
      },
    },
  },
  {
    id: 'analytical-review',
    label: 'Analytical Review',
    config: {
      providers: ['claude', 'gemini'],
      taskType: 'analytical',
      aggregationMode: 'best',
      timeoutSeconds: 45,
    },
    previewInputs: {
      input: sampleProductData,
    },
  },
  {
    id: 'technical-merge',
    label: 'Technical - Merge Results',
    config: {
      providers: ['gpt', 'grok'],
      taskType: 'technical',
      aggregationMode: 'merge',
      timeoutSeconds: 90,
    },
    previewInputs: {
      input: sampleProductData,
      instruction: {
        value: 'Generate implementation code for product showcase',
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
 * diverge Node Template
 *
 * Executable: sends input to multiple LLM providers in parallel,
 * collects all outputs into a variantList for selection.
 * v1 mock generates deterministic provider-specific results.
 */
export const divergeTemplate: NodeTemplate<DivergeConfig> = {
  type: 'diverge',
  templateVersion: '1.0.0',
  title: 'Diverge',
  category: 'utility',
  description: 'Parallel multi-LLM compete pattern. Sends input to 2-4 providers (Claude, GPT, Gemini, Grok), collects all outputs as variants for selection via HumanGate or AI selector. Reusable for any creative or analytical step.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: DivergeConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
