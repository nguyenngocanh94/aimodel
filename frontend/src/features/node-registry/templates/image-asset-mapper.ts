/**
 * imageAssetMapper Node Template - AiModel-9wx.9
 * 
 * Purpose: Normalizes image frame outputs to composition contract.
 * Category: utility
 * 
 * Inputs: imageFrameList
 * Outputs: imageAssetList
 * 
 * Config:
 * - assetRole: 'background' | 'foreground' | 'overlay' | 'auto' - Role assignment strategy
 * - namingPattern: string - Pattern for generating asset filenames
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const ImageAssetMapperConfigSchema = z.object({
  assetRole: z.enum(['background', 'foreground', 'overlay', 'auto'])
    .describe('How to assign asset roles during mapping'),
  namingPattern: z.string().min(1).max(100)
    .describe('Filename pattern using {index} placeholder'),
});

export type ImageAssetMapperConfig = z.infer<typeof ImageAssetMapperConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

interface ImageFrame {
  readonly frameId: string;
  readonly sceneIndex: number;
  readonly prompt: string;
  readonly placeholderUrl: string;
  readonly resolution: string;
  readonly seed: number;
  readonly stylePreset: string;
}

interface ImageFrameListValue {
  readonly frames: readonly ImageFrame[];
}

interface ImageAsset {
  readonly assetId: string;
  readonly sceneIndex: number;
  readonly role: 'background' | 'foreground' | 'overlay';
  readonly placeholderUrl: string;
  readonly localFileName: string;
  readonly resolution: string;
  readonly metadata: {
    readonly prompt: string;
    readonly seed: number;
    readonly stylePreset: string;
  };
}

interface ImageAssetListValue {
  readonly assets: readonly ImageAsset[];
  readonly count: number;
  readonly resolution: string;
}

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'imageFrameList',
    label: 'Image Frame List',
    direction: 'input',
    dataType: 'imageFrameList',
    required: true,
    multiple: false,
    description: 'Image frames to map to assets',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'imageAssetList',
    label: 'Image Asset List',
    direction: 'output',
    dataType: 'imageAssetList',
    required: true,
    multiple: false,
    description: 'Mapped image assets ready for composition',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: ImageAssetMapperConfig = {
  assetRole: 'auto',
  namingPattern: 'scene-{index}-asset',
};

// ============================================================
// Role Assignment Logic
// ============================================================

function assignRole(index: number, total: number, strategy: string): 'background' | 'foreground' | 'overlay' {
  if (strategy !== 'auto') {
    return strategy as 'background' | 'foreground' | 'overlay';
  }
  
  // Auto strategy: first frame is background, others alternate
  if (index === 0) return 'background';
  if (index === total - 1) return 'foreground';
  return index % 2 === 0 ? 'foreground' : 'overlay';
}

function generateFileName(pattern: string, index: number): string {
  return pattern.replace('{index}', String(index)) + '.png';
}

function mapFramesToAssets(
  frameList: ImageFrameListValue,
  config: ImageAssetMapperConfig
): ImageAssetListValue {
  const assets = frameList.frames.map((frame, index) => ({
    assetId: `asset-${frame.sceneIndex}-${index}`,
    sceneIndex: frame.sceneIndex,
    role: assignRole(index, frameList.frames.length, config.assetRole),
    placeholderUrl: frame.placeholderUrl,
    localFileName: generateFileName(config.namingPattern, index),
    resolution: frame.resolution,
    metadata: {
      prompt: frame.prompt,
      seed: frame.seed,
      stylePreset: frame.stylePreset,
    },
  }));
  
  // Get resolution from first asset, or default
  const resolution = frameList.frames[0]?.resolution || '1024x1024';
  
  return {
    assets: Object.freeze(assets),
    count: assets.length,
    resolution,
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<ImageAssetMapperConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;
  
  const frameListPayload = inputs.imageFrameList;
  if (!frameListPayload || frameListPayload.value === null) {
    return {
      imageAssetList: {
        value: null,
        status: 'idle',
        schemaType: 'imageAssetList',
        previewText: 'Waiting for image frame list input...',
      } as PortPayload,
    };
  }
  
  const frameList = frameListPayload.value as ImageFrameListValue;
  const assetList = mapFramesToAssets(frameList, config);
  
  const roleSummary = assetList.assets.reduce((acc, asset) => {
    acc[asset.role] = (acc[asset.role] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);
  
  const roleText = Object.entries(roleSummary)
    .map(([role, count]) => `${count} ${role}`)
    .join(', ');
  
  const previewText = `${assetList.count} assets · ${roleText} · ${config.namingPattern}`;
  
  return {
    imageAssetList: {
      value: assetList,
      status: 'ready',
      schemaType: 'imageAssetList',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(assetList).length * 2,
    } as PortPayload<ImageAssetListValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleFrameList: PortPayload = {
  value: {
    frames: [
      {
        frameId: 'frame-0-0',
        sceneIndex: 0,
        prompt: 'Mountain landscape at sunrise',
        placeholderUrl: 'placeholder://image/1024x1024/seed-123/frame-0.png',
        resolution: '1024x1024',
        seed: 123,
        stylePreset: 'cinematic',
      },
      {
        frameId: 'frame-1-1',
        sceneIndex: 1,
        prompt: 'Close-up of mountain flowers',
        placeholderUrl: 'placeholder://image/1024x1024/seed-456/frame-1.png',
        resolution: '1024x1024',
        seed: 456,
        stylePreset: 'cinematic',
      },
      {
        frameId: 'frame-2-2',
        sceneIndex: 2,
        prompt: 'Wide valley view',
        placeholderUrl: 'placeholder://image/1024x1024/seed-789/frame-2.png',
        resolution: '1024x1024',
        seed: 789,
        stylePreset: 'cinematic',
      },
    ],
    count: 3,
    resolution: '1024x1024',
  },
  status: 'success',
  schemaType: 'imageFrameList',
};

const fixtures: readonly NodeFixture<ImageAssetMapperConfig>[] = [
  {
    id: 'auto-roles',
    label: 'Auto Role Assignment',
    config: {
      assetRole: 'auto',
      namingPattern: 'scene-{index}-asset',
    },
    previewInputs: { imageFrameList: sampleFrameList },
  },
  {
    id: 'all-background',
    label: 'All Background Roles',
    config: {
      assetRole: 'background',
      namingPattern: 'bg-{index}',
    },
    previewInputs: { imageFrameList: sampleFrameList },
  },
  {
    id: 'custom-naming',
    label: 'Custom Naming Pattern',
    config: {
      assetRole: 'foreground',
      namingPattern: 'fg-layer-{index}',
    },
    previewInputs: { imageFrameList: sampleFrameList },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * imageAssetMapper Node Template
 * 
 * Non-executable: deterministic transform from frames to assets.
 */
export const imageAssetMapperTemplate: NodeTemplate<ImageAssetMapperConfig> = {
  type: 'imageAssetMapper',
  templateVersion: '1.0.0',
  title: 'Image Asset Mapper',
  category: 'utility',
  description: 'Maps image frame outputs to composition-ready assets with configurable role assignment and naming patterns. Converts frames to the asset contract required by video composition.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: ImageAssetMapperConfigSchema,
  fixtures,
  executable: false,
  buildPreview,
};
