/**
 * wanImageEdit Node Template
 *
 * Purpose: Image editing via natural language using Wan AI models.
 *          Modifies images via text commands.
 *          Use cases: put product on virtual model, change outfit,
 *          swap background, adjust style.
 *          Preserves geometric consistency.
 *          Used in fallback pipeline when R2V can't accurately render the product.
 * Category: visuals
 *
 * Inputs:
 *   - image (imageAsset) — required — source image to edit
 *   - prompt (text) — required — edit instruction in natural language
 *
 * Output: imageAsset
 *
 * Config:
 *   - provider: API provider
 *   - apiKey: API key
 *   - model: model identifier
 *   - preserveProduct: whether to preserve product geometry/fidelity
 *   - seed: deterministic seed
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const WanImageEditConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for image editing'),
  apiKey: z.string()
    .describe('API key for the provider'),
  model: z.string()
    .describe('Model identifier'),
  preserveProduct: z.boolean()
    .describe('Preserve product geometry and fidelity during editing'),
  seed: z.number().int().nonnegative()
    .describe('Deterministic seed for reproducibility'),
});

export type WanImageEditConfig = z.infer<typeof WanImageEditConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'image',
    label: 'Source Image',
    direction: 'input',
    dataType: 'imageAsset',
    required: true,
    multiple: false,
    description: 'Source image to edit',
  },
  {
    key: 'prompt',
    label: 'Edit Instruction',
    direction: 'input',
    dataType: 'text',
    required: true,
    multiple: false,
    description: 'Natural language edit instruction (e.g., "Put this dress on a virtual model in a studio setting")',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'editedImage',
    label: 'Edited Image',
    direction: 'output',
    dataType: 'imageAsset',
    required: true,
    multiple: false,
    description: 'Edited image asset',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: WanImageEditConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'wan-image-edit-default',
  preserveProduct: true,
  seed: 0,
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
// Stub Image Data
// ============================================================

export interface WanImageEditPayload {
  readonly assetId: string;
  readonly role: 'background' | 'foreground' | 'overlay';
  readonly placeholderUrl: string;
  readonly localFileName: string;
  readonly resolution: string;
  readonly metadata: {
    readonly originalAssetId: string;
    readonly editPrompt: string;
    readonly seed: number;
    readonly model: string;
    readonly preserveProduct: boolean;
    readonly editedAt: string;
  };
}

function buildStubImagePayload(
  config: WanImageEditConfig,
  originalAssetId: string,
  editPrompt: string,
  hash: string,
): WanImageEditPayload {
  return {
    assetId: `wan-edit-${hash}`,
    role: 'background',
    placeholderUrl: `placeholder://image/wan-image-edit/${hash}.jpg`,
    localFileName: `wan-edit-${hash}.jpg`,
    resolution: '1080x1920',
    metadata: {
      originalAssetId,
      editPrompt,
      seed: config.seed,
      model: config.model,
      preserveProduct: config.preserveProduct,
      editedAt: new Date().toISOString(),
    },
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<WanImageEditConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const imagePayload = inputs.image;
  const promptPayload = inputs.prompt;

  if (!imagePayload || imagePayload.value === null || imagePayload.value === undefined) {
    return {
      editedImage: {
        value: null,
        status: 'idle',
        schemaType: 'imageAsset',
        previewText: 'Waiting for source image...',
      } as PortPayload,
    };
  }

  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      editedImage: {
        value: null,
        status: 'idle',
        schemaType: 'imageAsset',
        previewText: 'Waiting for edit instruction...',
      } as PortPayload,
    };
  }

  const imageAsset = imagePayload.value as { assetId?: string };
  const originalAssetId = imageAsset.assetId ?? 'unknown';
  const editPrompt = String(promptPayload.value);

  const hash = stableHash(JSON.stringify({ originalAssetId, editPrompt, config }));
  const editedImagePayload = buildStubImagePayload(config, originalAssetId, editPrompt, hash);

  const previewText = [
    config.model,
    config.preserveProduct ? 'preserve-product' : 'free-edit',
    editPrompt.substring(0, 50) + (editPrompt.length > 50 ? '...' : ''),
  ].filter(Boolean).join(' · ');

  return {
    editedImage: {
      value: editedImagePayload,
      status: 'ready',
      schemaType: 'imageAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(editedImagePayload).length * 2,
    } as PortPayload<WanImageEditPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<WanImageEditConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imagePayload = inputs.image;
  const promptPayload = inputs.prompt;

  if (!imagePayload || imagePayload.value === null || imagePayload.value === undefined) {
    return {
      editedImage: {
        value: null,
        status: 'error',
        schemaType: 'imageAsset',
        errorMessage: 'Missing required source image input',
      } as PortPayload,
    };
  }

  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      editedImage: {
        value: null,
        status: 'error',
        schemaType: 'imageAsset',
        errorMessage: 'Missing required edit instruction (prompt)',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imageAsset = imagePayload.value as { assetId?: string };
  const originalAssetId = imageAsset.assetId ?? 'unknown';
  const editPrompt = String(promptPayload.value);

  const hash = stableHash(JSON.stringify({ originalAssetId, editPrompt, config }));
  const editedImagePayload = buildStubImagePayload(config, originalAssetId, editPrompt, hash);

  const previewText = [
    config.model,
    config.preserveProduct ? 'preserve-product' : 'free-edit',
  ].filter(Boolean).join(' · ');

  return {
    editedImage: {
      value: editedImagePayload,
      status: 'success',
      schemaType: 'imageAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(editedImagePayload).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<WanImageEditPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleProductImage: PortPayload = {
  value: {
    assetId: 'product-dress-001',
    role: 'foreground',
    placeholderUrl: 'placeholder://image/dress-001.jpg',
    localFileName: 'dress-001.jpg',
    resolution: '1024x1024',
    metadata: {
      prompt: 'Red summer dress product photo',
      seed: 42,
      stylePreset: 'default',
    },
  },
  status: 'success',
  schemaType: 'imageAsset',
};

const sampleVirtualModelPrompt: PortPayload = {
  value: 'Put this dress on a slim Asian virtual model, professional studio lighting, white background',
  status: 'success',
  schemaType: 'text',
};

const sampleBackgroundSwapPrompt: PortPayload = {
  value: 'Replace background with tropical beach sunset scene, keep product intact',
  status: 'success',
  schemaType: 'text',
};

const fixtures: readonly NodeFixture<WanImageEditConfig>[] = [
  {
    id: 'virtual-model',
    label: 'Virtual Model Placement',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-image-edit-default',
      preserveProduct: true,
      seed: 42,
    },
    previewInputs: { image: sampleProductImage, prompt: sampleVirtualModelPrompt },
  },
  {
    id: 'background-swap',
    label: 'Background Replacement',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-image-edit-default',
      preserveProduct: true,
      seed: 123,
    },
    previewInputs: { image: sampleProductImage, prompt: sampleBackgroundSwapPrompt },
  },
  {
    id: 'style-transfer',
    label: 'Style Transfer (Free Edit)',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-image-edit-default',
      preserveProduct: false,
      seed: 456,
    },
    previewInputs: { 
      image: sampleProductImage, 
      prompt: {
        value: 'Transform into oil painting style with impressionist brush strokes',
        status: 'success',
        schemaType: 'text',
      } as PortPayload,
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * wanImageEdit Node Template
 *
 * Executable: edits images via natural language using Wan AI models.
 * v1 mock returns stub edited image data.
 */
export const wanImageEditTemplate: NodeTemplate<WanImageEditConfig> = {
  type: 'wanImageEdit',
  templateVersion: '1.0.0',
  title: 'Wan Image Edit',
  category: 'visuals',
  description: 'Image editing via natural language using Wan AI models. Modify images with text commands: put products on virtual models, change outfits, swap backgrounds, adjust style. Preserves geometric consistency.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: WanImageEditConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
