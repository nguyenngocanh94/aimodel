/**
 * imageGenerator Node Template - AiModel-9wx.8
 * 
 * Purpose: Generates placeholder images for scenes or prompts.
 * Category: visuals
 * 
 * Config-dependent inputs:
 * - inputMode 'scenes': input is sceneList
 * - inputMode 'prompts': input is promptList
 * 
 * Config-dependent outputs:
 * - outputMode 'frames': output is imageFrameList
 * - outputMode 'assets': output is imageAssetList
 * 
 * Config:
 * - inputMode: 'scenes' | 'prompts'
 * - outputMode: 'frames' | 'assets'
 * - stylePreset: 'default' | 'cinematic' | 'vivid' | 'subdued'
 * - resolution: '512x512' | '1024x1024' | '1024x1536' | '1536x1024'
 * - seedStrategy: 'deterministic' | 'random'
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const ImageGeneratorConfigSchema = z.object({
  inputMode: z.enum(['scenes', 'prompts'])
    .describe('Source of generation input: scene descriptions or refined prompts'),
  outputMode: z.enum(['frames', 'assets'])
    .describe('Output format: frame list for preview or asset list for composition'),
  stylePreset: z.enum(['default', 'cinematic', 'vivid', 'subdued'])
    .describe('Image generation style preset'),
  resolution: z.enum(['512x512', '1024x1024', '1024x1536', '1536x1024'])
    .describe('Output image resolution'),
  seedStrategy: z.enum(['deterministic', 'random'])
    .describe('Seed strategy for reproducibility'),
});

export type ImageGeneratorConfig = z.infer<typeof ImageGeneratorConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

interface Scene {
  readonly sequenceIndex: number;
  readonly visualDescription: string;
  readonly durationSeconds: number;
}

interface SceneListValue {
  readonly scenes: readonly Scene[];
}

interface RefinedPrompt {
  readonly sceneIndex: number;
  readonly prompt: string;
  readonly negativePrompt?: string;
  readonly aspectRatio: string;
}

interface PromptListValue {
  readonly prompts: readonly RefinedPrompt[];
}

interface ImageFrame {
  readonly frameId: string;
  readonly sceneIndex: number;
  readonly prompt: string;
  readonly placeholderUrl: string;
  readonly resolution: string;
  readonly seed: number;
  readonly stylePreset: string;
  readonly generatedAt: string;
}

interface ImageFrameListValue {
  readonly frames: readonly ImageFrame[];
  readonly count: number;
  readonly resolution: string;
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
// Port Definitions (All ports, active/inactive controlled by config)
// ============================================================

const inputPorts: readonly PortDefinition[] = [
  {
    key: 'sceneList',
    label: 'Scene List',
    direction: 'input',
    dataType: 'sceneList',
    required: false, // Required based on inputMode
    multiple: false,
    description: 'Scene descriptions for image generation (used when inputMode=scenes)',
  },
  {
    key: 'promptList',
    label: 'Prompt List',
    direction: 'input',
    dataType: 'promptList',
    required: false, // Required based on inputMode
    multiple: false,
    description: 'Refined prompts for image generation (used when inputMode=prompts)',
  },
];

const outputPorts: readonly PortDefinition[] = [
  {
    key: 'imageFrameList',
    label: 'Image Frame List',
    direction: 'output',
    dataType: 'imageFrameList',
    required: false, // Active based on outputMode
    multiple: false,
    description: 'Generated image frames for preview (produced when outputMode=frames)',
  },
  {
    key: 'imageAssetList',
    label: 'Image Asset List',
    direction: 'output',
    dataType: 'imageAssetList',
    required: false, // Active based on outputMode
    multiple: false,
    description: 'Image assets for composition (produced when outputMode=assets)',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: ImageGeneratorConfig = {
  inputMode: 'scenes',
  outputMode: 'frames',
  stylePreset: 'cinematic',
  resolution: '1024x1024',
  seedStrategy: 'deterministic',
};

// ============================================================
// Helper Functions
// ============================================================

function generateHash(input: string): number {
  let hash = 0;
  for (let i = 0; i < input.length; i++) {
    const char = input.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash;
  }
  return Math.abs(hash);
}

function generatePlaceholderUrl(seed: number, resolution: string, index: number): string {
  // Generate deterministic placeholder URL
  // In production, this would be a real AI generation API
  return `placeholder://image/${resolution}/seed-${seed}/frame-${index}.png`;
}

function extractPromptsFromScenes(sceneList: SceneListValue): { sceneIndex: number; prompt: string }[] {
  return sceneList.scenes.map(scene => ({
    sceneIndex: scene.sequenceIndex,
    prompt: scene.visualDescription,
  }));
}

function extractPromptsFromPromptList(promptList: PromptListValue): { sceneIndex: number; prompt: string }[] {
  return promptList.prompts.map(p => ({
    sceneIndex: p.sceneIndex,
    prompt: p.prompt,
  }));
}

function generateSeed(seedStrategy: 'deterministic' | 'random', baseHash: number, index: number): number {
  if (seedStrategy === 'random') {
    return Math.floor(Math.random() * 1000000);
  }
  return generateHash(`${baseHash}-${index}`) % 1000000;
}

function generateImageFrames(
  prompts: { sceneIndex: number; prompt: string }[],
  config: ImageGeneratorConfig,
  baseHash: number
): ImageFrameListValue {
  const frames = prompts.map((p, index) => {
    const seed = generateSeed(config.seedStrategy, baseHash, index);
    
    return {
      frameId: `frame-${p.sceneIndex}-${index}`,
      sceneIndex: p.sceneIndex,
      prompt: p.prompt,
      placeholderUrl: generatePlaceholderUrl(seed, config.resolution, index),
      resolution: config.resolution,
      seed,
      stylePreset: config.stylePreset,
      generatedAt: new Date().toISOString(),
    };
  });
  
  return {
    frames: Object.freeze(frames),
    count: frames.length,
    resolution: config.resolution,
  };
}

function generateImageAssets(
  prompts: { sceneIndex: number; prompt: string }[],
  config: ImageGeneratorConfig,
  baseHash: number
): ImageAssetListValue {
  const assets = prompts.map((p, index) => {
    const seed = generateSeed(config.seedStrategy, baseHash, index);
    
    return {
      assetId: `asset-${p.sceneIndex}-${index}`,
      sceneIndex: p.sceneIndex,
      role: 'background' as const, // Default role
      placeholderUrl: generatePlaceholderUrl(seed, config.resolution, index),
      localFileName: `scene-${p.sceneIndex}-asset-${index}.png`,
      resolution: config.resolution,
      metadata: {
        prompt: p.prompt,
        seed,
        stylePreset: config.stylePreset,
      },
    };
  });
  
  return {
    assets: Object.freeze(assets),
    count: assets.length,
    resolution: config.resolution,
  };
}

function getActiveInputKey(config: ImageGeneratorConfig): string {
  return config.inputMode === 'scenes' ? 'sceneList' : 'promptList';
}

function getActiveOutputKey(config: ImageGeneratorConfig): string {
  return config.outputMode === 'frames' ? 'imageFrameList' : 'imageAssetList';
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<ImageGeneratorConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;
  
  const activeInputKey = getActiveInputKey(config);
  const activeOutputKey = getActiveOutputKey(config);
  
  const inputPayload = inputs[activeInputKey];
  if (!inputPayload || inputPayload.value === null) {
    return {
      [activeOutputKey]: {
        value: null,
        status: 'idle',
        schemaType: config.outputMode === 'frames' ? 'imageFrameList' : 'imageAssetList',
        previewText: `Waiting for ${activeInputKey} input...`,
      } as PortPayload,
    };
  }
  
  const baseHash = generateHash(JSON.stringify(inputPayload.value) + JSON.stringify(config));
  
  let prompts: { sceneIndex: number; prompt: string }[];
  if (config.inputMode === 'scenes') {
    prompts = extractPromptsFromScenes(inputPayload.value as SceneListValue);
  } else {
    prompts = extractPromptsFromPromptList(inputPayload.value as PromptListValue);
  }
  
  if (config.outputMode === 'frames') {
    const frameList = generateImageFrames(prompts, config, baseHash);
    return {
      imageFrameList: {
        value: frameList,
        status: 'ready',
        schemaType: 'imageFrameList',
        previewText: `${frameList.count} frames · ${config.resolution} · ${config.stylePreset}`,
        sizeBytesEstimate: frameList.count * 50 * 1024, // Rough estimate
      } as PortPayload<ImageFrameListValue>,
    };
  } else {
    const assetList = generateImageAssets(prompts, config, baseHash);
    return {
      imageAssetList: {
        value: assetList,
        status: 'ready',
        schemaType: 'imageAssetList',
        previewText: `${assetList.count} assets · ${config.resolution} · ${config.stylePreset}`,
        sizeBytesEstimate: assetList.count * 50 * 1024,
      } as PortPayload<ImageAssetListValue>,
    };
  }
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<ImageGeneratorConfig>
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;
  
  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }
  
  const activeInputKey = getActiveInputKey(config);
  const activeOutputKey = getActiveOutputKey(config);
  
  const inputPayload = inputs[activeInputKey];
  if (!inputPayload || inputPayload.value === null) {
    return {
      [activeOutputKey]: {
        value: null,
        status: 'error',
        schemaType: config.outputMode === 'frames' ? 'imageFrameList' : 'imageAssetList',
        errorMessage: `Missing required ${activeInputKey} input`,
      } as PortPayload,
    };
  }
  
  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 150));
  
  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }
  
  const baseHash = generateHash(JSON.stringify(inputPayload.value) + JSON.stringify(config));
  
  let prompts: { sceneIndex: number; prompt: string }[];
  if (config.inputMode === 'scenes') {
    prompts = extractPromptsFromScenes(inputPayload.value as SceneListValue);
  } else {
    prompts = extractPromptsFromPromptList(inputPayload.value as PromptListValue);
  }
  
  if (config.outputMode === 'frames') {
    const frameList = generateImageFrames(prompts, config, baseHash);
    return {
      imageFrameList: {
        value: frameList,
        status: 'success',
        schemaType: 'imageFrameList',
        previewText: `${frameList.count} frames · ${config.resolution} · ${config.stylePreset}`,
        sizeBytesEstimate: frameList.count * 50 * 1024,
        producedAt: new Date().toISOString(),
      } as PortPayload<ImageFrameListValue>,
    };
  } else {
    const assetList = generateImageAssets(prompts, config, baseHash);
    return {
      imageAssetList: {
        value: assetList,
        status: 'success',
        schemaType: 'imageAssetList',
        previewText: `${assetList.count} assets · ${config.resolution} · ${config.stylePreset}`,
        sizeBytesEstimate: assetList.count * 50 * 1024,
        producedAt: new Date().toISOString(),
      } as PortPayload<ImageAssetListValue>,
    };
  }
}

// ============================================================
// Fixtures
// ============================================================

const sampleSceneList: PortPayload = {
  value: {
    scenes: [
      { sequenceIndex: 0, visualDescription: 'Mountain landscape at sunrise', durationSeconds: 15 },
      { sequenceIndex: 1, visualDescription: 'Close-up of mountain flowers', durationSeconds: 10 },
      { sequenceIndex: 2, visualDescription: 'Wide valley view', durationSeconds: 20 },
    ],
  },
  status: 'success',
  schemaType: 'sceneList',
};

const samplePromptList: PortPayload = {
  value: {
    prompts: [
      { sceneIndex: 0, prompt: 'Photorealistic mountain at sunrise, cinematic lighting', aspectRatio: '16:9' },
      { sceneIndex: 1, prompt: 'Detailed macro shot of alpine flowers', aspectRatio: '16:9' },
      { sceneIndex: 2, prompt: 'Panoramic valley vista, epic scale', aspectRatio: '16:9' },
    ],
    count: 3,
    visualStyle: 'photorealistic',
    aspectRatio: '16:9',
    generatedAt: new Date().toISOString(),
  },
  status: 'success',
  schemaType: 'promptList',
};

const fixtures: readonly NodeFixture<ImageGeneratorConfig>[] = [
  {
    id: 'scenes-to-frames',
    label: 'Scenes to Frames',
    config: {
      inputMode: 'scenes',
      outputMode: 'frames',
      stylePreset: 'cinematic',
      resolution: '1024x1024',
      seedStrategy: 'deterministic',
    },
    previewInputs: { sceneList: sampleSceneList },
  },
  {
    id: 'prompts-to-frames',
    label: 'Prompts to Frames',
    config: {
      inputMode: 'prompts',
      outputMode: 'frames',
      stylePreset: 'vivid',
      resolution: '1024x1536',
      seedStrategy: 'deterministic',
    },
    previewInputs: { promptList: samplePromptList },
  },
  {
    id: 'scenes-to-assets',
    label: 'Scenes to Assets',
    config: {
      inputMode: 'scenes',
      outputMode: 'assets',
      stylePreset: 'subdued',
      resolution: '1536x1024',
      seedStrategy: 'deterministic',
    },
    previewInputs: { sceneList: sampleSceneList },
  },
  {
    id: 'prompts-to-assets-hd',
    label: 'Prompts to HD Assets',
    config: {
      inputMode: 'prompts',
      outputMode: 'assets',
      stylePreset: 'default',
      resolution: '1024x1024',
      seedStrategy: 'deterministic',
    },
    previewInputs: { promptList: samplePromptList },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * imageGenerator Node Template
 * 
 * Executable: generates placeholder images from scenes or prompts.
 * Config-dependent ports activate/deactivate based on inputMode and outputMode.
 */
export const imageGeneratorTemplate: NodeTemplate<ImageGeneratorConfig> = {
  type: 'imageGenerator',
  templateVersion: '1.0.0',
  title: 'Image Generator',
  category: 'visuals',
  description: 'Generates placeholder images from scene descriptions or refined prompts. Supports frame generation for preview and asset generation for composition. Config-dependent ports allow flexible workflow integration.',
  inputs: inputPorts,
  outputs: outputPorts,
  defaultConfig,
  configSchema: ImageGeneratorConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
