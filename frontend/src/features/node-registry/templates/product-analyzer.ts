/**
 * productAnalyzer Node Template
 *
 * Purpose: Analyzes product images using vision AI to extract structured
 *          product intelligence including features, selling points, and
 *          target audience.
 * Category: input
 *
 * Inputs:
 *   - images (imageAssetList) — required
 *   - description (text) — optional
 *
 * Output: json (structured product analysis)
 *
 * Config:
 *   - provider: API provider
 *   - apiKey: API key
 *   - model: model identifier (default gpt-4o)
 *   - analysisDepth: basic | detailed
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Product Analysis Payload
// ============================================================

export interface ProductAnalysisPayload {
  readonly productType: string;
  readonly productName: string;
  readonly colors: readonly string[];
  readonly materials: readonly string[];
  readonly style: string;
  readonly sellingPoints: readonly string[];
  readonly targetAudience: {
    readonly age: string;
    readonly gender: string;
    readonly occasion: string;
    readonly lifestyle: string;
  };
  readonly pricePositioning: 'budget' | 'mid-range' | 'premium' | 'luxury';
  readonly suggestedMood: string;
}

// ============================================================
// Configuration Schema
// ============================================================

export const ProductAnalyzerConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for vision AI'),
  apiKey: z.string()
    .describe('API key for the provider'),
  model: z.string()
    .describe('Model identifier (e.g. gpt-4o, gemini-pro-vision)'),
  analysisDepth: z.enum(['basic', 'detailed'])
    .describe('Depth of analysis: basic or detailed'),
});

export type ProductAnalyzerConfig = z.infer<typeof ProductAnalyzerConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'images',
    label: 'Images',
    direction: 'input',
    dataType: 'imageAssetList',
    required: true,
    multiple: false,
    description: 'Product photos to analyze',
  },
  {
    key: 'description',
    label: 'Description',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Optional text description from seller',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'analysis',
    label: 'Analysis',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Structured product analysis report',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: ProductAnalyzerConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'gpt-4o',
  analysisDepth: 'detailed',
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

function pickIndex(seedHex: string, modulo: number, salt: number): number {
  const n = Number.parseInt(seedHex.slice(0, 8), 16) ^ salt;
  return Math.abs(n) % modulo;
}

// ============================================================
// Stub Analysis Data
// ============================================================

const productTypes = ['apparel', 'accessory', 'footwear', 'electronics', 'home decor'];
const productNames = ['Classic Cotton Tee', 'Leather Crossbody Bag', 'Canvas Sneakers', 'Wireless Earbuds', 'Ceramic Vase'];
const colorPools = [
  ['navy', 'white', 'grey'],
  ['black', 'tan', 'gold'],
  ['white', 'red', 'blue'],
  ['matte black', 'silver'],
  ['terracotta', 'cream', 'olive'],
];
const materialPools = [
  ['cotton', 'polyester blend'],
  ['genuine leather', 'brass hardware'],
  ['canvas', 'rubber sole'],
  ['ABS plastic', 'silicone tips'],
  ['ceramic', 'glaze finish'],
];
const styles = ['casual minimalist', 'classic heritage', 'streetwear', 'modern tech', 'artisan bohemian'];
const sellingPointPools = [
  ['Breathable fabric', 'Versatile layering piece', 'Pre-shrunk for lasting fit'],
  ['Handcrafted details', 'Adjustable strap', 'Multiple compartments'],
  ['Lightweight and flexible', 'All-day comfort', 'Eco-friendly materials'],
  ['Active noise cancellation', '24h battery life', 'IPX5 water resistant'],
  ['Handmade by artisans', 'Unique glaze pattern', 'Timeless centerpiece'],
];
const audiences = [
  { age: '18-35', gender: 'unisex', occasion: 'everyday casual', lifestyle: 'urban minimalist' },
  { age: '25-45', gender: 'female', occasion: 'work and travel', lifestyle: 'professional on-the-go' },
  { age: '16-30', gender: 'unisex', occasion: 'casual outings', lifestyle: 'streetwear enthusiast' },
  { age: '20-40', gender: 'unisex', occasion: 'commute and workout', lifestyle: 'active tech-savvy' },
  { age: '28-55', gender: 'unisex', occasion: 'home styling', lifestyle: 'design-conscious homeowner' },
];
const priceTiers: readonly ('budget' | 'mid-range' | 'premium' | 'luxury')[] = ['budget', 'mid-range', 'premium', 'luxury'];
const moods = ['clean and fresh', 'warm and inviting', 'energetic urban', 'sleek and modern', 'earthy and calm'];

function buildStubAnalysis(config: ProductAnalyzerConfig, imageCount: number, description: string): ProductAnalysisPayload {
  const seed = stableHash(JSON.stringify({ config, imageCount, description }));
  const idx = pickIndex(seed, productTypes.length, 3);

  return {
    productType: productTypes[idx]!,
    productName: productNames[idx]!,
    colors: colorPools[idx]!,
    materials: materialPools[idx]!,
    style: styles[idx]!,
    sellingPoints: sellingPointPools[idx]!,
    targetAudience: audiences[idx]!,
    pricePositioning: priceTiers[pickIndex(seed, priceTiers.length, 7)]!,
    suggestedMood: moods[idx]!,
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<ProductAnalyzerConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs: inputPayloads } = args;

  const imagesPayload = inputPayloads.images;
  if (!imagesPayload || imagesPayload.value === null || imagesPayload.value === undefined) {
    return {
      analysis: {
        value: null,
        status: 'idle',
        schemaType: 'json',
        previewText: 'Waiting for product images...',
      } as PortPayload,
    };
  }

  const imageCount = Array.isArray(imagesPayload.value) ? imagesPayload.value.length : 1;
  const description = inputPayloads.description?.value
    ? String(inputPayloads.description.value)
    : '';

  const analysis = buildStubAnalysis(config, imageCount, description);

  return {
    analysis: {
      value: analysis,
      status: 'idle',
      schemaType: 'json',
      previewText: `${analysis.productName} · ${analysis.productType}`,
      sizeBytesEstimate: JSON.stringify(analysis).length * 2,
    } as PortPayload<ProductAnalysisPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<ProductAnalyzerConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs: inputPayloads, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imagesPayload = inputPayloads.images;
  if (!imagesPayload || imagesPayload.value === null || imagesPayload.value === undefined) {
    return {
      analysis: {
        value: null,
        status: 'error',
        schemaType: 'json',
        errorMessage: 'Missing required input: images',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imageCount = Array.isArray(imagesPayload.value) ? imagesPayload.value.length : 1;
  const description = inputPayloads.description?.value
    ? String(inputPayloads.description.value)
    : '';

  const analysis = buildStubAnalysis(config, imageCount, description);

  return {
    analysis: {
      value: analysis,
      status: 'success',
      schemaType: 'json',
      previewText: `${analysis.productName} · ${analysis.productType}`,
      sizeBytesEstimate: JSON.stringify(analysis).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<ProductAnalysisPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleImages: PortPayload = {
  value: [
    { url: 'https://example.com/product-front.jpg' },
    { url: 'https://example.com/product-side.jpg' },
  ],
  status: 'success',
  schemaType: 'imageAssetList',
};

const fixtures: readonly NodeFixture<ProductAnalyzerConfig>[] = [
  {
    id: 'product-analysis-demo',
    label: 'Product Analysis Demo',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'gpt-4o',
      analysisDepth: 'detailed',
    },
    previewInputs: { images: sampleImages },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * productAnalyzer Node Template
 *
 * Executable: analyzes product images via vision AI and produces
 * a structured product intelligence report. v1 mock returns
 * deterministic stub analysis data.
 */
export const productAnalyzerTemplate: NodeTemplate<ProductAnalyzerConfig> = {
  type: 'productAnalyzer',
  templateVersion: '1.0.0',
  title: 'Product Analyzer',
  category: 'input',
  description: 'Analyzes product images using vision AI to extract features, selling points, and target audience.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: ProductAnalyzerConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
