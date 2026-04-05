/**
 * reviewCheckpoint Node Template - AiModel-9wx.13
 *
 * Purpose: Human-in-the-loop checkpoint where user can annotate or approve
 * data before downstream steps.
 * Category: utility
 *
 * Inputs: script | sceneList | imageAssetList | subtitleAsset | videoAsset
 *         (all optional — reviewType config selects which is active)
 * Outputs: approvedScript | approvedSceneList | approvedImageAssetList |
 *          approvedSubtitleAsset | approvedVideoAsset | reviewDecision
 *
 * Config:
 * - reviewLabel: string (display label for the checkpoint)
 * - instructions: string (guidance text for reviewer)
 * - blocking: boolean (whether execution pauses here)
 * - reviewType: enum selecting the active input/output pair
 *
 * Behavior: Does not transform data — wraps and re-emits after confirmation.
 * Supports auto-approve in mock mode.
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { DataType, PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const ReviewCheckpointConfigSchema = z.object({
  reviewLabel: z.string().min(1).max(200)
    .describe('Display label for this review checkpoint'),
  instructions: z.string().min(1).max(2000)
    .describe('Guidance text shown to the reviewer'),
  blocking: z.boolean()
    .describe('Whether execution pauses at this checkpoint'),
  reviewType: z.enum(['script', 'sceneList', 'imageAssetList', 'subtitleAsset', 'videoAsset'])
    .describe('Selects which input/output pair is active'),
});

export type ReviewCheckpointConfig = z.infer<typeof ReviewCheckpointConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

interface ReviewDecisionValue {
  readonly decision: 'approved' | 'rejected';
  readonly reviewedAt: string;
  readonly reviewerNotes: string;
  readonly reviewType: string;
}

// ============================================================
// Review Type → DataType / Port Key Mappings
// ============================================================

const REVIEW_TYPE_TO_DATA_TYPE: Readonly<Record<string, DataType>> = {
  script: 'script',
  sceneList: 'sceneList',
  imageAssetList: 'imageAssetList',
  subtitleAsset: 'subtitleAsset',
  videoAsset: 'videoAsset',
};

const REVIEW_TYPE_TO_APPROVED_KEY: Readonly<Record<string, string>> = {
  script: 'approvedScript',
  sceneList: 'approvedSceneList',
  imageAssetList: 'approvedImageAssetList',
  subtitleAsset: 'approvedSubtitleAsset',
  videoAsset: 'approvedVideoAsset',
};

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'script',
    label: 'Script',
    direction: 'input',
    dataType: 'script',
    required: false,
    multiple: false,
    description: 'Script data to review (active when reviewType = script)',
  },
  {
    key: 'sceneList',
    label: 'Scene List',
    direction: 'input',
    dataType: 'sceneList',
    required: false,
    multiple: false,
    description: 'Scene list data to review (active when reviewType = sceneList)',
  },
  {
    key: 'imageAssetList',
    label: 'Image Asset List',
    direction: 'input',
    dataType: 'imageAssetList',
    required: false,
    multiple: false,
    description: 'Image asset list to review (active when reviewType = imageAssetList)',
  },
  {
    key: 'subtitleAsset',
    label: 'Subtitle Asset',
    direction: 'input',
    dataType: 'subtitleAsset',
    required: false,
    multiple: false,
    description: 'Subtitle asset to review (active when reviewType = subtitleAsset)',
  },
  {
    key: 'videoAsset',
    label: 'Video Asset',
    direction: 'input',
    dataType: 'videoAsset',
    required: false,
    multiple: false,
    description: 'Video asset to review (active when reviewType = videoAsset)',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'approvedScript',
    label: 'Approved Script',
    direction: 'output',
    dataType: 'script',
    required: false,
    multiple: false,
    description: 'Approved script (emitted when reviewType = script)',
  },
  {
    key: 'approvedSceneList',
    label: 'Approved Scene List',
    direction: 'output',
    dataType: 'sceneList',
    required: false,
    multiple: false,
    description: 'Approved scene list (emitted when reviewType = sceneList)',
  },
  {
    key: 'approvedImageAssetList',
    label: 'Approved Image Asset List',
    direction: 'output',
    dataType: 'imageAssetList',
    required: false,
    multiple: false,
    description: 'Approved image asset list (emitted when reviewType = imageAssetList)',
  },
  {
    key: 'approvedSubtitleAsset',
    label: 'Approved Subtitle Asset',
    direction: 'output',
    dataType: 'subtitleAsset',
    required: false,
    multiple: false,
    description: 'Approved subtitle asset (emitted when reviewType = subtitleAsset)',
  },
  {
    key: 'approvedVideoAsset',
    label: 'Approved Video Asset',
    direction: 'output',
    dataType: 'videoAsset',
    required: false,
    multiple: false,
    description: 'Approved video asset (emitted when reviewType = videoAsset)',
  },
  {
    key: 'reviewDecision',
    label: 'Review Decision',
    direction: 'output',
    dataType: 'reviewDecision',
    required: true,
    multiple: false,
    description: 'The review outcome with decision, timestamp, and notes',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: ReviewCheckpointConfig = {
  reviewLabel: 'Review Checkpoint',
  instructions: 'Please review the data and approve or reject.',
  blocking: true,
  reviewType: 'script',
};

// ============================================================
// Helper: Detect Active Input
// ============================================================

function findActiveInput(
  inputsRecord: Readonly<Record<string, PortPayload>>,
  reviewType: string,
): { readonly key: string; readonly payload: PortPayload } | null {
  // Prefer the input matching the configured reviewType
  const configuredPayload = inputsRecord[reviewType];
  if (configuredPayload && configuredPayload.value !== null) {
    return { key: reviewType, payload: configuredPayload };
  }

  // Fallback: check all known input keys for a connected one
  const allInputKeys = ['script', 'sceneList', 'imageAssetList', 'subtitleAsset', 'videoAsset'] as const;
  for (const key of allInputKeys) {
    const payload = inputsRecord[key];
    if (payload && payload.value !== null) {
      return { key, payload };
    }
  }

  return null;
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<ReviewCheckpointConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs: inputsRecord } = args;

  const active = findActiveInput(inputsRecord, config.reviewType);

  if (!active) {
    const approvedKey = REVIEW_TYPE_TO_APPROVED_KEY[config.reviewType];
    const dataType = REVIEW_TYPE_TO_DATA_TYPE[config.reviewType];
    return {
      [approvedKey]: {
        value: null,
        status: 'idle',
        schemaType: dataType,
        previewText: `Waiting for ${config.reviewType} input...`,
      } as PortPayload,
      reviewDecision: {
        value: null,
        status: 'idle',
        schemaType: 'reviewDecision',
        previewText: 'No review yet',
      } as PortPayload,
    };
  }

  const approvedKey = REVIEW_TYPE_TO_APPROVED_KEY[active.key];
  const dataType = REVIEW_TYPE_TO_DATA_TYPE[active.key];
  const valueStr = JSON.stringify(active.payload.value);
  const previewText = `${config.reviewLabel} · ${active.key} · pass-through ready`;

  return {
    [approvedKey]: {
      value: active.payload.value,
      status: 'ready',
      schemaType: dataType,
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: valueStr.length * 2,
    } as PortPayload,
    reviewDecision: {
      value: null,
      status: 'idle',
      schemaType: 'reviewDecision',
      previewText: 'Pending review',
    } as PortPayload,
  };
}

// ============================================================
// Mock Execute (auto-approve)
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<ReviewCheckpointConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs: inputsRecord, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const active = findActiveInput(inputsRecord, config.reviewType);

  if (!active) {
    const approvedKey = REVIEW_TYPE_TO_APPROVED_KEY[config.reviewType];
    const dataType = REVIEW_TYPE_TO_DATA_TYPE[config.reviewType];
    return {
      [approvedKey]: {
        value: null,
        status: 'error',
        schemaType: dataType,
        errorMessage: `Missing required ${config.reviewType} input`,
      } as PortPayload,
      reviewDecision: {
        value: null,
        status: 'error',
        schemaType: 'reviewDecision',
        errorMessage: 'Cannot review — no input data provided',
      } as PortPayload,
    };
  }

  // Simulate human review latency
  await new Promise(resolve => setTimeout(resolve, 60));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const approvedKey = REVIEW_TYPE_TO_APPROVED_KEY[active.key];
  const dataType = REVIEW_TYPE_TO_DATA_TYPE[active.key];
  const now = new Date().toISOString();

  const decision: ReviewDecisionValue = {
    decision: 'approved',
    reviewedAt: now,
    reviewerNotes: 'Auto-approved in mock execution mode',
    reviewType: active.key,
  };

  const valueStr = JSON.stringify(active.payload.value);
  const previewText = `${config.reviewLabel} · ${active.key} · approved`;

  return {
    [approvedKey]: {
      value: active.payload.value,
      status: 'success',
      schemaType: dataType,
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: valueStr.length * 2,
      producedAt: now,
    } as PortPayload,
    reviewDecision: {
      value: decision,
      status: 'success',
      schemaType: 'reviewDecision',
      previewText: `Approved · ${active.key} · ${now}`,
      sizeBytesEstimate: JSON.stringify(decision).length * 2,
      producedAt: now,
    } as PortPayload<ReviewDecisionValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleScript: PortPayload = {
  value: {
    title: 'How Solar Panels Work',
    hook: 'Did you know solar panels are more affordable than ever?',
    beats: [
      { timestamp: '0s', narration: 'Solar panels convert sunlight into electricity.', durationSeconds: 10 },
      { timestamp: '10s', narration: 'Photovoltaic cells absorb photons and release electrons.', durationSeconds: 15 },
    ],
    cta: 'Switch to solar energy today!',
    totalDurationSeconds: 60,
    style: 'educational',
    structure: 'problem-solution',
  },
  status: 'success',
  schemaType: 'script',
};

const sampleSceneList: PortPayload = {
  value: {
    scenes: [
      { sceneIndex: 0, title: 'Opening', description: 'Solar panel array at sunrise', durationSeconds: 10 },
      { sceneIndex: 1, title: 'Close-up', description: 'Detail of photovoltaic cell', durationSeconds: 15 },
    ],
    count: 2,
  },
  status: 'success',
  schemaType: 'sceneList',
};

const fixtures: readonly NodeFixture<ReviewCheckpointConfig>[] = [
  {
    id: 'review-script',
    label: 'Review Script (Auto-Approve)',
    config: {
      reviewLabel: 'Script Review',
      instructions: 'Verify that the script tone matches the target audience.',
      blocking: true,
      reviewType: 'script',
    },
    previewInputs: { script: sampleScript },
  },
  {
    id: 'review-scene-list',
    label: 'Review Scene List (Auto-Approve)',
    config: {
      reviewLabel: 'Scene Review',
      instructions: 'Confirm each scene description is clear and achievable.',
      blocking: false,
      reviewType: 'sceneList',
    },
    previewInputs: { sceneList: sampleSceneList },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * reviewCheckpoint Node Template
 *
 * Executable: human-in-the-loop review gate that auto-approves in mock mode.
 */
export const reviewCheckpointTemplate: NodeTemplate<ReviewCheckpointConfig> = {
  type: 'reviewCheckpoint',
  templateVersion: '1.0.0',
  title: 'Review Checkpoint',
  category: 'utility',
  description: 'Human-in-the-loop checkpoint where a reviewer can annotate or approve data before downstream steps. Supports script, scene list, image asset list, subtitle, and video asset review. Auto-approves in mock execution mode.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: ReviewCheckpointConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
