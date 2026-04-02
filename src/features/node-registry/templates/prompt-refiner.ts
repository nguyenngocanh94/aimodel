/**
 * promptRefiner Node Template - AiModel-9wx.7
 * 
 * Purpose: Converts scene descriptions into refined AI image generation prompts.
 * Category: visuals
 * 
 * Inputs: sceneList
 * Outputs: promptList
 * 
 * Config:
 * - visualStyle: 'photorealistic' | 'cinematic' | 'illustrated' | '3d-rendered' | 'anime'
 * - cameraLanguage: 'standard' | 'dramatic' | 'intimate' | 'epic'
 * - aspectRatio: '16:9' | '9:16' | '1:1' | '4:3'
 * - consistencyNotes: string - Notes for maintaining visual consistency
 * - negativePromptEnabled: boolean - Whether to include negative prompt guidance
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const PromptRefinerConfigSchema = z.object({
  visualStyle: z.enum(['photorealistic', 'cinematic', 'illustrated', '3d-rendered', 'anime'])
    .describe('The visual style for generated images'),
  cameraLanguage: z.enum(['standard', 'dramatic', 'intimate', 'epic'])
    .describe('Camera framing and movement language'),
  aspectRatio: z.enum(['16:9', '9:16', '1:1', '4:3'])
    .describe('Target aspect ratio for images'),
  consistencyNotes: z.string().max(500).default('')
    .describe('Notes for maintaining visual consistency across scenes'),
  negativePromptEnabled: z.boolean().default(true)
    .describe('Include negative prompt to exclude unwanted elements'),
});

export type PromptRefinerConfig = z.infer<typeof PromptRefinerConfigSchema>;

// ============================================================
// Scene Types
// ============================================================

interface Scene {
  readonly sequenceIndex: number;
  readonly summary: string;
  readonly startTimeSeconds: number;
  readonly durationSeconds: number;
  readonly narration: string;
  readonly visualDescription: string;
  readonly shotIntent?: string;
  readonly visualPromptHint?: string;
}

interface SceneListValue {
  readonly scenes: readonly Scene[];
  readonly totalDurationSeconds: number;
  readonly sceneCount: number;
}

interface RefinedPrompt {
  readonly sceneIndex: number;
  readonly prompt: string;
  readonly negativePrompt?: string;
  readonly aspectRatio: string;
  readonly style: string;
  readonly cameraDirection: string;
  readonly durationHint: string;
}

interface PromptListValue {
  readonly prompts: readonly RefinedPrompt[];
  readonly count: number;
  readonly visualStyle: string;
  readonly aspectRatio: string;
  readonly generatedAt: string;
}

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'sceneList',
    label: 'Scene List',
    direction: 'input',
    dataType: 'sceneList',
    required: true,
    multiple: false,
    description: 'The scene list to convert into refined prompts',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'promptList',
    label: 'Prompt List',
    direction: 'output',
    dataType: 'promptList',
    required: true,
    multiple: false,
    description: 'Refined AI image generation prompts, one per scene',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: PromptRefinerConfig = {
  visualStyle: 'cinematic',
  cameraLanguage: 'standard',
  aspectRatio: '16:9',
  consistencyNotes: '',
  negativePromptEnabled: true,
};

// ============================================================
// Prompt Generation Logic
// ============================================================

const STYLE_MODIFIERS: Record<string, string> = {
  photorealistic: 'photorealistic, highly detailed, 8k resolution, professional photography',
  cinematic: 'cinematic composition, film grain, dramatic lighting, movie still',
  illustrated: 'digital illustration, artistic interpretation, stylized, concept art',
  '3d-rendered': '3D render, octane render, blender, unreal engine, ray tracing',
  anime: 'anime style, studio ghibli inspired, cel shading, vibrant colors',
};

const CAMERA_MODIFIERS: Record<string, string> = {
  standard: 'balanced composition, eye-level shot, clear subject focus',
  dramatic: 'dramatic angle, Dutch tilt, high contrast lighting, intense mood',
  intimate: 'close framing, shallow depth of field, personal perspective, warm tones',
  epic: 'wide angle, sweeping vista, aerial perspective, grand scale',
};

const ASPECT_RATIO_HINTS: Record<string, string> = {
  '16:9': 'landscape orientation, widescreen',
  '9:16': 'portrait orientation, vertical format',
  '1:1': 'square format, centered composition',
  '4:3': 'classic aspect ratio, balanced framing',
};

const NEGATIVE_PROMPT_BASE = 'blurry, low quality, distorted, deformed, ugly, duplicate, watermark, signature, text, logo, cropped, out of frame';

function generateHash(input: string): number {
  let hash = 0;
  for (let i = 0; i < input.length; i++) {
    const char = input.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash;
  }
  return Math.abs(hash);
}

function refinePrompt(
  scene: Scene,
  config: PromptRefinerConfig,
  sceneHash: number
): RefinedPrompt {
  const visualDescription = scene.visualPromptHint || scene.visualDescription;
  const styleModifier = STYLE_MODIFIERS[config.visualStyle];
  const cameraModifier = CAMERA_MODIFIERS[config.cameraLanguage];
  const aspectHint = ASPECT_RATIO_HINTS[config.aspectRatio];
  const consistencySuffix = config.consistencyNotes.trim()
    ? ` Consistency: ${config.consistencyNotes.trim()}`
    : '';

  // Build the refined prompt
  const prompt = `${visualDescription}. ${styleModifier}. ${cameraModifier}. ${aspectHint}. Professional quality.${consistencySuffix}`;
  
  // Generate negative prompt if enabled
  const negativePrompt = config.negativePromptEnabled
    ? `${NEGATIVE_PROMPT_BASE}, ${config.visualStyle === 'anime' ? '3d render, photorealistic' : 'cartoon, anime, sketch'}`
    : undefined;
  
  // Generate camera direction based on shot intent and camera language
  const shotTypes = [
    'establishing shot',
    'medium shot',
    'close-up',
    'wide shot',
    'overhead view',
    'low angle',
  ];
  const cameraDirection = scene.shotIntent || shotTypes[sceneHash % shotTypes.length];
  
  return {
    sceneIndex: scene.sequenceIndex,
    prompt: prompt.trim(),
    negativePrompt,
    aspectRatio: config.aspectRatio,
    style: config.visualStyle,
    cameraDirection,
    durationHint: `${scene.durationSeconds}s`,
  };
}

function refineScenesIntoPrompts(
  sceneList: SceneListValue,
  config: PromptRefinerConfig
): PromptListValue {
  const baseHash = generateHash(JSON.stringify(sceneList) + JSON.stringify(config));
  
  const prompts = sceneList.scenes.map((scene, index) => {
    const sceneHash = generateHash(`${baseHash}-${index}`);
    return refinePrompt(scene, config, sceneHash);
  });
  
  return {
    prompts: Object.freeze(prompts),
    count: prompts.length,
    visualStyle: config.visualStyle,
    aspectRatio: config.aspectRatio,
    generatedAt: new Date().toISOString(),
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<PromptRefinerConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;
  
  const sceneListPayload = inputs.sceneList;
  if (!sceneListPayload || sceneListPayload.value === null) {
    return {
      promptList: {
        value: null,
        status: 'idle',
        schemaType: 'promptList',
        previewText: 'Waiting for scene list input...',
      } as PortPayload,
    };
  }
  
  const sceneList = sceneListPayload.value as SceneListValue;
  const promptList = refineScenesIntoPrompts(sceneList, config);
  
  const previewText = `${promptList.count} prompts · ${config.visualStyle} style · ${config.aspectRatio}`;
  
  return {
    promptList: {
      value: promptList,
      status: 'ready',
      schemaType: 'promptList',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(promptList).length * 2,
    } as PortPayload<PromptListValue>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<PromptRefinerConfig>
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;
  
  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }
  
  const sceneListPayload = inputs.sceneList;
  if (!sceneListPayload || sceneListPayload.value === null) {
    return {
      promptList: {
        value: null,
        status: 'error',
        schemaType: 'promptList',
        errorMessage: 'Missing required scene list input',
      } as PortPayload,
    };
  }
  
  await new Promise(resolve => setTimeout(resolve, 100));
  
  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }
  
  const sceneList = sceneListPayload.value as SceneListValue;
  const promptList = refineScenesIntoPrompts(sceneList, config);
  
  const previewText = `${promptList.count} prompts · ${config.visualStyle} style · ${config.aspectRatio}`;
  
  return {
    promptList: {
      value: promptList,
      status: 'success',
      schemaType: 'promptList',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(promptList).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<PromptListValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<PromptRefinerConfig>[] = [
  {
    id: 'cinematic-widescreen',
    label: 'Cinematic Widescreen',
    config: {
      visualStyle: 'cinematic',
      cameraLanguage: 'dramatic',
      aspectRatio: '16:9',
      consistencyNotes: 'Maintain consistent lighting and color grading across all scenes',
      negativePromptEnabled: true,
    },
    previewInputs: {
      sceneList: {
        value: {
          scenes: [
            {
              sequenceIndex: 0,
              summary: 'Opening scene',
              startTimeSeconds: 0,
              durationSeconds: 15,
              narration: 'Introduction',
              visualDescription: 'Wide shot of a bustling city at sunset',
              shotIntent: 'Establishing shot',
            },
            {
              sequenceIndex: 1,
              summary: 'Character introduction',
              startTimeSeconds: 15,
              durationSeconds: 20,
              narration: 'Meet the protagonist',
              visualDescription: 'Close-up of person looking determined',
              shotIntent: 'Close-up portrait',
            },
            {
              sequenceIndex: 2,
              summary: 'Action scene',
              startTimeSeconds: 35,
              durationSeconds: 25,
              narration: 'The chase begins',
              visualDescription: 'Dynamic shot of running through urban environment',
              shotIntent: 'Tracking shot',
            },
          ],
          totalDurationSeconds: 60,
          sceneCount: 3,
        },
        status: 'success',
        schemaType: 'sceneList',
      } as PortPayload,
    },
  },
  {
    id: 'portrait-social',
    label: 'Portrait Social Media',
    config: {
      visualStyle: 'photorealistic',
      cameraLanguage: 'intimate',
      aspectRatio: '9:16',
      consistencyNotes: 'Warm skin tones, natural lighting',
      negativePromptEnabled: true,
    },
    previewInputs: {
      sceneList: {
        value: {
          scenes: [
            {
              sequenceIndex: 0,
              summary: 'Product showcase',
              startTimeSeconds: 0,
              durationSeconds: 10,
              narration: 'Look at this product',
              visualDescription: 'Hand holding product against clean background',
              shotIntent: 'Product close-up',
            },
            {
              sequenceIndex: 1,
              summary: 'Benefit demo',
              startTimeSeconds: 10,
              durationSeconds: 15,
              narration: 'See how it works',
              visualDescription: 'Product in use with happy user',
              shotIntent: 'Medium shot',
            },
          ],
          totalDurationSeconds: 25,
          sceneCount: 2,
        },
        status: 'success',
        schemaType: 'sceneList',
      } as PortPayload,
    },
  },
  {
    id: 'illustrated-educational',
    label: 'Illustrated Educational',
    config: {
      visualStyle: 'illustrated',
      cameraLanguage: 'standard',
      aspectRatio: '1:1',
      consistencyNotes: 'Use same color palette and illustration style throughout',
      negativePromptEnabled: false,
    },
    previewInputs: {
      sceneList: {
        value: {
          scenes: [
            {
              sequenceIndex: 0,
              summary: 'Concept introduction',
              startTimeSeconds: 0,
              durationSeconds: 20,
              narration: 'Let us learn about atoms',
              visualDescription: 'Colorful illustration of atomic structure',
              shotIntent: 'Diagram shot',
            },
            {
              sequenceIndex: 1,
              summary: 'Process visualization',
              startTimeSeconds: 20,
              durationSeconds: 25,
              narration: 'See how electrons orbit',
              visualDescription: 'Animated diagram showing electron movement',
              shotIntent: 'Animated diagram',
            },
            {
              sequenceIndex: 2,
              summary: 'Real world example',
              startTimeSeconds: 45,
              durationSeconds: 20,
              narration: 'Atoms in everyday objects',
              visualDescription: 'Illustration showing atoms in common objects',
              shotIntent: 'Comparison diagram',
            },
          ],
          totalDurationSeconds: 65,
          sceneCount: 3,
        },
        status: 'success',
        schemaType: 'sceneList',
      } as PortPayload,
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * promptRefiner Node Template
 * 
 * Executable: converts scene descriptions into refined AI image prompts.
 */
export const promptRefinerTemplate: NodeTemplate<PromptRefinerConfig> = {
  type: 'promptRefiner',
  templateVersion: '1.0.0',
  title: 'Prompt Refiner',
  category: 'visuals',
  description: 'Converts scene descriptions into refined AI image generation prompts with style modifiers, camera direction, and optional negative prompts. Outputs one prompt per input scene.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: PromptRefinerConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
