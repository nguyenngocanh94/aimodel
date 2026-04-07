/**
 * wanVideoEdit Node Template
 *
 * Purpose: Video post-processing using Wan AI models.
 *          Edit generated videos via prompts.
 *          Supports: color grading, local/global editing, video reshaping,
 *          style transfer.
 *          Used to polish R2V output — fix lighting issues, adjust mood,
 *          improve cinematic quality.
 * Category: video
 *
 * Inputs:
 *   - video (videoAsset) — required — source video to edit
 *   - prompt (text) — required — edit instruction in natural language
 *
 * Output: videoAsset
 *
 * Config:
 *   - provider: API provider
 *   - apiKey: API key
 *   - model: model identifier
 *   - editMode: 'color-grade' | 'local-edit' | 'global-edit' | 'reshape' | 'style-transfer'
 *   - seed: deterministic seed
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const WanVideoEditConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for video editing'),
  apiKey: z.string()
    .describe('API key for the provider'),
  model: z.string()
    .describe('Model identifier'),
  editMode: z.enum(['color-grade', 'local-edit', 'global-edit', 'reshape', 'style-transfer'])
    .describe('Type of video edit to perform'),
  seed: z.number().int().nonnegative()
    .describe('Deterministic seed for reproducibility'),
});

export type WanVideoEditConfig = z.infer<typeof WanVideoEditConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'video',
    label: 'Source Video',
    direction: 'input',
    dataType: 'videoAsset',
    required: true,
    multiple: false,
    description: 'Source video to edit',
  },
  {
    key: 'prompt',
    label: 'Edit Instruction',
    direction: 'input',
    dataType: 'text',
    required: true,
    multiple: false,
    description: 'Natural language edit instruction (e.g., "Warm golden hour color grade")',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'editedVideo',
    label: 'Edited Video',
    direction: 'output',
    dataType: 'videoAsset',
    required: true,
    multiple: false,
    description: 'Edited video asset',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: WanVideoEditConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'wan-video-edit-default',
  editMode: 'color-grade',
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

export interface WanVideoEditPayload {
  readonly videoUrl: string;
  readonly durationSeconds: number;
  readonly aspectRatio: string;
  readonly resolution: string;
  readonly model: string;
  readonly seed: number;
  readonly originalVideoUrl: string;
  readonly editPrompt: string;
  readonly editMode: string;
  readonly editedAt: string;
}

function buildStubVideoPayload(
  config: WanVideoEditConfig,
  originalVideo: WanVideoEditPayload | null,
  editPrompt: string,
  hash: string,
): WanVideoEditPayload {
  // Preserve original video properties if available
  const durationSeconds = originalVideo?.durationSeconds ?? 5;
  const aspectRatio = originalVideo?.aspectRatio ?? '9:16';
  const resolution = originalVideo?.resolution ?? '1080p';
  const originalVideoUrl = originalVideo?.videoUrl ?? 'placeholder://video/original.mp4';

  return {
    videoUrl: `placeholder://video/wan-video-edit/${hash}.mp4`,
    durationSeconds,
    aspectRatio,
    resolution,
    model: config.model,
    seed: config.seed,
    originalVideoUrl,
    editPrompt,
    editMode: config.editMode,
    editedAt: new Date().toISOString(),
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<WanVideoEditConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const videoPayload = inputs.video;
  const promptPayload = inputs.prompt;

  if (!videoPayload || videoPayload.value === null || videoPayload.value === undefined) {
    return {
      editedVideo: {
        value: null,
        status: 'idle',
        schemaType: 'videoAsset',
        previewText: 'Waiting for source video...',
      } as PortPayload,
    };
  }

  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      editedVideo: {
        value: null,
        status: 'idle',
        schemaType: 'videoAsset',
        previewText: 'Waiting for edit instruction...',
      } as PortPayload,
    };
  }

  const originalVideo = videoPayload.value as WanVideoEditPayload | null;
  const editPrompt = String(promptPayload.value);

  const hash = stableHash(JSON.stringify({ originalVideo, editPrompt, config }));
  const editedVideoPayload = buildStubVideoPayload(config, originalVideo, editPrompt, hash);

  const previewText = [
    config.editMode,
    config.model,
    editPrompt.substring(0, 50) + (editPrompt.length > 50 ? '...' : ''),
  ].filter(Boolean).join(' · ');

  return {
    editedVideo: {
      value: editedVideoPayload,
      status: 'ready',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(editedVideoPayload).length * 2,
    } as PortPayload<WanVideoEditPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<WanVideoEditConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const videoPayload = inputs.video;
  const promptPayload = inputs.prompt;

  if (!videoPayload || videoPayload.value === null || videoPayload.value === undefined) {
    return {
      editedVideo: {
        value: null,
        status: 'error',
        schemaType: 'videoAsset',
        errorMessage: 'Missing required source video input',
      } as PortPayload,
    };
  }

  if (!promptPayload || promptPayload.value === null || promptPayload.value === undefined) {
    return {
      editedVideo: {
        value: null,
        status: 'error',
        schemaType: 'videoAsset',
        errorMessage: 'Missing required edit instruction (prompt)',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const originalVideo = videoPayload.value as WanVideoEditPayload | null;
  const editPrompt = String(promptPayload.value);

  const hash = stableHash(JSON.stringify({ originalVideo, editPrompt, config }));
  const editedVideoPayload = buildStubVideoPayload(config, originalVideo, editPrompt, hash);

  const previewText = [
    config.editMode,
    config.model,
  ].filter(Boolean).join(' · ');

  return {
    editedVideo: {
      value: editedVideoPayload,
      status: 'success',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(editedVideoPayload).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<WanVideoEditPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleSourceVideo: PortPayload = {
  value: {
    videoUrl: 'placeholder://video/r2v-output-001.mp4',
    durationSeconds: 5,
    aspectRatio: '9:16',
    resolution: '1080p',
    model: 'wan-r2v-default',
    seed: 42,
    prompt: 'Product showcase video',
    hasReferenceVideos: false,
    hasReferenceImages: true,
  },
  status: 'success',
  schemaType: 'videoAsset',
};

const sampleColorGradePrompt: PortPayload = {
  value: 'Warm golden hour color grade with soft highlights and rich shadows',
  status: 'success',
  schemaType: 'text',
};

const sampleLocalEditPrompt: PortPayload = {
  value: 'Remove the logo in the bottom right corner',
  status: 'success',
  schemaType: 'text',
};

const sampleStyleTransferPrompt: PortPayload = {
  value: 'Transform into cinematic film look with anamorphic lens flare',
  status: 'success',
  schemaType: 'text',
};

const fixtures: readonly NodeFixture<WanVideoEditConfig>[] = [
  {
    id: 'color-grade-warm',
    label: 'Warm Color Grading',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-video-edit-default',
      editMode: 'color-grade',
      seed: 42,
    },
    previewInputs: { video: sampleSourceVideo, prompt: sampleColorGradePrompt },
  },
  {
    id: 'local-edit-cleanup',
    label: 'Local Edit - Logo Removal',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-video-edit-default',
      editMode: 'local-edit',
      seed: 123,
    },
    previewInputs: { video: sampleSourceVideo, prompt: sampleLocalEditPrompt },
  },
  {
    id: 'style-transfer-cinematic',
    label: 'Cinematic Style Transfer',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'wan-video-edit-default',
      editMode: 'style-transfer',
      seed: 456,
    },
    previewInputs: { video: sampleSourceVideo, prompt: sampleStyleTransferPrompt },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * wanVideoEdit Node Template
 *
 * Executable: post-processes videos via natural language using Wan AI models.
 * v1 mock returns stub edited video data.
 */
export const wanVideoEditTemplate: NodeTemplate<WanVideoEditConfig> = {
  type: 'wanVideoEdit',
  templateVersion: '1.0.0',
  title: 'Wan Video Edit',
  category: 'video',
  description: 'Video post-processing using Wan AI models. Edit generated videos via prompts: color grading, local/global editing, video reshaping, style transfer. Used to polish R2V output — fix lighting issues, adjust mood, improve cinematic quality.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: WanVideoEditConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
