/**
 * wanR2V Node Template
 *
 * Purpose: Reference-to-Video generation using Wan AI models.
 *          Accepts a text prompt plus optional reference videos and images,
 *          and produces a generated video asset.
 * Category: video
 *
 * Inputs:
 *   - prompt (text) — required
 *   - referenceVideos (videoUrlList) — optional
 *   - referenceImages (imageAssetList) — optional
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
 *   - multiShots: whether to generate multi-shot videos
 *   - seed: deterministic seed
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const WanR2VConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for video generation'),
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
  multiShots: z.boolean()
    .describe('Whether to generate multi-shot videos'),
  seed: z.number().int().nonnegative()
    .describe('Deterministic seed for reproducibility'),
});

export type WanR2VConfig = z.infer<typeof WanR2VConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'prompt',
    label: 'Prompt',
    direction: 'input',
    dataType: 'text',
    required: true,
    multiple: false,
    description: 'Text prompt describing the desired video content',
  },
  {
    key: 'referenceVideos',
    label: 'Reference Videos',
    direction: 'input',
    dataType: 'videoUrlList',
    required: false,
    multiple: false,
    description: 'Optional reference video URLs to guide generation',
  },
  {
    key: 'referenceImages',
    label: 'Reference Images',
    direction: 'input',
    dataType: 'imageAssetList',
    required: false,
    multiple: false,
    description: 'Optional reference images to guide generation',
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
    description: 'Generated video asset',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: WanR2VConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'wan-r2v-default',
  aspectRatio: '9:16',
  resolution: '1080p',
  duration: '5',
  multiShots: false,
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

export interface WanR2VVideoPayload {
  readonly videoUrl: string;
  readonly durationSeconds: number;
  readonly aspectRatio: string;
  readonly resolution: string;
  readonly model: string;
  readonly seed: number;
  readonly prompt: string;
  readonly hasReferenceVideos: boolean;
  readonly hasReferenceImages: boolean;
}

function buildStubVideoPayload(
  config: WanR2VConfig,
  prompt: string,
  hasReferenceVideos: boolean,
  hasReferenceImages: boolean,
  hash: string,
): WanR2VVideoPayload {
  return {
    videoUrl: `placeholder://video/wan-r2v/${hash}.mp4`,
    durationSeconds: parseInt(config.duration, 10),
    aspectRatio: config.aspectRatio,
    resolution: config.resolution,
    model: config.model,
    seed: config.seed,
    prompt,
    hasReferenceVideos,
    hasReferenceImages,
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<WanR2VConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const promptPayload = inputs.prompt;
  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      video: {
        value: null,
        status: 'idle',
        schemaType: 'videoAsset',
        previewText: 'Waiting for prompt input...',
      } as PortPayload,
    };
  }

  const prompt = String(promptPayload.value);
  const hasRefVideos = !!(inputs.referenceVideos?.value);
  const hasRefImages = !!(inputs.referenceImages?.value);

  const hash = stableHash(JSON.stringify({ prompt, config, hasRefVideos, hasRefImages }));
  const videoPayload = buildStubVideoPayload(config, prompt, hasRefVideos, hasRefImages, hash);

  const previewText = [
    `${config.duration}s`,
    config.aspectRatio,
    config.resolution,
    config.model,
    hasRefVideos ? 'ref-videos' : null,
    hasRefImages ? 'ref-images' : null,
  ].filter(Boolean).join(' · ');

  return {
    video: {
      value: videoPayload,
      status: 'ready',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(videoPayload).length * 2,
    } as PortPayload<WanR2VVideoPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<WanR2VConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const promptPayload = inputs.prompt;
  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      video: {
        value: null,
        status: 'error',
        schemaType: 'videoAsset',
        errorMessage: 'Missing required prompt input',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const prompt = String(promptPayload.value);
  const hasRefVideos = !!(inputs.referenceVideos?.value);
  const hasRefImages = !!(inputs.referenceImages?.value);

  const hash = stableHash(JSON.stringify({ prompt, config, hasRefVideos, hasRefImages }));
  const videoPayload = buildStubVideoPayload(config, prompt, hasRefVideos, hasRefImages, hash);

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
    } as PortPayload<WanR2VVideoPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const samplePrompt: PortPayload = {
  value: 'A stylish product showcase with dynamic camera movements and trendy transitions for a TikTok ad',
  status: 'success',
  schemaType: 'text',
};

const fixtures: readonly NodeFixture<WanR2VConfig>[] = [
  {
    id: 'tiktok-tvc-demo',
    label: 'TikTok TVC Demo',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-r2v-default',
      aspectRatio: '9:16',
      resolution: '1080p',
      duration: '5',
      multiShots: false,
      seed: 42,
    },
    previewInputs: { prompt: samplePrompt },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * wanR2V Node Template
 *
 * Executable: generates video from prompt and optional reference media
 * using Wan AI R2V models. v1 mock returns stub video data.
 */
export const wanR2VTemplate: NodeTemplate<WanR2VConfig> = {
  type: 'wanR2V',
  templateVersion: '1.0.0',
  title: 'Wan R2V',
  category: 'video',
  description: 'Reference-to-Video generation using Wan AI models. Accepts a text prompt plus optional reference videos and images, and produces a generated video asset.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: WanR2VConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
