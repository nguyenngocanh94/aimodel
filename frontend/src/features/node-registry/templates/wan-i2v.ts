/**
 * wanI2V Node Template
 *
 * Purpose: Image-to-Video generation using Wan AI models.
 *          Converts still product photos into animated video clips.
 *          Used for: product reveal close-ups, unboxing animations,
 *          product showcase scenes.
 * Category: video
 *
 * Inputs:
 *   - image (imageAsset) — required — source image to animate
 *   - prompt (text) — optional — motion and camera movement description
 *
 * Output: videoAsset
 *
 * Config:
 *   - provider: API provider
 *   - apiKey: API key
 *   - model: model identifier
 *   - aspectRatio: output aspect ratio
 *   - resolution: output resolution
 *   - duration: clip duration in seconds
 *   - seed: deterministic seed
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const WanI2VConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for image-to-video generation'),
  apiKey: z.string()
    .describe('API key for the provider'),
  model: z.string()
    .describe('Model identifier'),
  aspectRatio: z.enum(['16:9', '9:16', '1:1', '4:3'])
    .describe('Output video aspect ratio'),
  resolution: z.enum(['720p', '1080p', '4k'])
    .describe('Output video resolution'),
  duration: z.enum(['3', '5', '10'])
    .describe('Video clip duration in seconds'),
  seed: z.number().int().nonnegative()
    .describe('Deterministic seed for reproducibility'),
});

export type WanI2VConfig = z.infer<typeof WanI2VConfigSchema>;

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
    description: 'Source product photo to animate into video',
  },
  {
    key: 'prompt',
    label: 'Motion Prompt',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Motion description + camera movement (e.g., "gentle rotation with zoom in")',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'video',
    label: 'Video',
    direction: 'output',
    dataType: 'videoAsset',
    required: true,
    multiple: false,
    description: 'Generated video from image animation',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: WanI2VConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'wan-i2v-default',
  aspectRatio: '9:16',
  resolution: '1080p',
  duration: '5',
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
// Stub Video Data
// ============================================================

export interface WanI2VVideoPayload {
  readonly videoUrl: string;
  readonly durationSeconds: number;
  readonly aspectRatio: string;
  readonly resolution: string;
  readonly model: string;
  readonly seed: number;
  readonly sourceImageAssetId: string;
  readonly motionPrompt: string;
}

function buildStubVideoPayload(
  config: WanI2VConfig,
  sourceImageAssetId: string,
  motionPrompt: string,
  hash: string,
): WanI2VVideoPayload {
  return {
    videoUrl: `placeholder://video/wan-i2v/${hash}.mp4`,
    durationSeconds: parseInt(config.duration, 10),
    aspectRatio: config.aspectRatio,
    resolution: config.resolution,
    model: config.model,
    seed: config.seed,
    sourceImageAssetId,
    motionPrompt,
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<WanI2VConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const imagePayload = inputs.image;
  if (!imagePayload || imagePayload.value === null || imagePayload.value === undefined) {
    return {
      video: {
        value: null,
        status: 'idle',
        schemaType: 'videoAsset',
        previewText: 'Waiting for source image...',
      } as PortPayload,
    };
  }

  const imageAsset = imagePayload.value as { assetId?: string };
  const sourceImageAssetId = imageAsset.assetId ?? 'unknown';
  const motionPrompt = inputs.prompt?.value ? String(inputs.prompt.value) : 'default animation';

  const hash = stableHash(JSON.stringify({ sourceImageAssetId, motionPrompt, config }));
  const videoPayload = buildStubVideoPayload(config, sourceImageAssetId, motionPrompt, hash);

  const previewText = [
    `${config.duration}s`,
    config.aspectRatio,
    config.resolution,
    config.model,
    motionPrompt !== 'default animation' ? 'custom-motion' : 'default-motion',
  ].filter(Boolean).join(' · ');

  return {
    video: {
      value: videoPayload,
      status: 'ready',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(videoPayload).length * 2,
    } as PortPayload<WanI2VVideoPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<WanI2VConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imagePayload = inputs.image;
  if (!imagePayload || imagePayload.value === null || imagePayload.value === undefined) {
    return {
      video: {
        value: null,
        status: 'error',
        schemaType: 'videoAsset',
        errorMessage: 'Missing required source image input',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const imageAsset = imagePayload.value as { assetId?: string };
  const sourceImageAssetId = imageAsset.assetId ?? 'unknown';
  const motionPrompt = inputs.prompt?.value ? String(inputs.prompt.value) : 'default animation';

  const hash = stableHash(JSON.stringify({ sourceImageAssetId, motionPrompt, config }));
  const videoPayload = buildStubVideoPayload(config, sourceImageAssetId, motionPrompt, hash);

  const previewText = [
    `${config.duration}s`,
    config.aspectRatio,
    config.resolution,
    config.model,
  ].filter(Boolean).join(' · ');

  return {
    video: {
      value: videoPayload,
      status: 'success',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(videoPayload).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<WanI2VVideoPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleImage: PortPayload = {
  value: {
    assetId: 'product-photo-001',
    role: 'background',
    placeholderUrl: 'placeholder://image/product-001.jpg',
    localFileName: 'product-001.jpg',
    resolution: '1080x1920',
    metadata: {
      prompt: 'Product photo on white background',
      seed: 42,
      stylePreset: 'default',
    },
  },
  status: 'success',
  schemaType: 'imageAsset',
};

const sampleMotionPrompt: PortPayload = {
  value: 'Gentle 360 rotation with soft lighting reveal, zoom in slowly for dramatic effect',
  status: 'success',
  schemaType: 'text',
};

const fixtures: readonly NodeFixture<WanI2VConfig>[] = [
  {
    id: 'product-unboxing',
    label: 'Product Unboxing Animation',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-i2v-default',
      aspectRatio: '9:16',
      resolution: '1080p',
      duration: '5',
      seed: 42,
    },
    previewInputs: { image: sampleImage, prompt: sampleMotionPrompt },
  },
  {
    id: 'product-showcase',
    label: 'Product Showcase Close-up',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-i2v-default',
      aspectRatio: '9:16',
      resolution: '1080p',
      duration: '3',
      seed: 123,
    },
    previewInputs: { image: sampleImage },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * wanI2V Node Template
 *
 * Executable: animates a product photo into video using Wan I2V models.
 * v1 mock returns stub video data.
 */
export const wanI2VTemplate: NodeTemplate<WanI2VConfig> = {
  type: 'wanI2V',
  templateVersion: '1.0.0',
  title: 'Wan I2V',
  category: 'video',
  description: 'Image-to-Video generation using Wan AI models. Converts still product photos into animated video clips with customizable motion and camera movements.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: WanI2VConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
