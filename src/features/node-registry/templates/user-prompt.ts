/**
 * userPrompt Node Template - AiModel-9wx.4
 * 
 * Purpose: Collects or seeds the initial creative intent.
 * Category: input
 * 
 * Inputs: None
 * Outputs: prompt (structured prompt payload)
 * 
 * Config:
 * - topic: string - The main subject/topic
 * - goal: string - The intended outcome
 * - audience: string - Target audience description
 * - tone: 'educational' | 'cinematic' | 'playful' | 'dramatic'
 * - durationSeconds: number - Target duration in seconds
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const UserPromptConfigSchema = z.object({
  topic: z.string().min(1).max(500).describe('The main subject or topic of the video'),
  goal: z.string().min(1).max(500).describe('What you want to achieve with this video'),
  audience: z.string().min(1).max(500).describe('Who the video is for'),
  tone: z.enum(['educational', 'cinematic', 'playful', 'dramatic'])
    .describe('The emotional tone and style of the video'),
  durationSeconds: z.number().min(5).max(600).describe('Target duration in seconds (5-600)'),
});

export type UserPromptConfig = z.infer<typeof UserPromptConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [];

const outputs: readonly PortDefinition[] = [
  {
    key: 'prompt',
    label: 'Prompt',
    direction: 'output',
    dataType: 'prompt',
    required: true,
    multiple: false,
    description: 'Structured prompt payload generated from form inputs',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: UserPromptConfig = {
  topic: 'Introduction to Machine Learning',
  goal: 'Explain the basics of ML in an engaging way',
  audience: 'Technical beginners',
  tone: 'educational',
  durationSeconds: 120,
};

// ============================================================
// Preview Builder
// ============================================================

interface PromptValue {
  topic: string;
  goal: string;
  audience: string;
  tone: string;
  durationSeconds: number;
  generatedAt: string;
}

function buildPreview(args: {
  readonly config: Readonly<UserPromptConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config } = args;
  
  const promptValue: PromptValue = {
    topic: config.topic,
    goal: config.goal,
    audience: config.audience,
    tone: config.tone,
    durationSeconds: config.durationSeconds,
    generatedAt: new Date().toISOString(),
  };
  
  const previewText = `Create a ${config.tone} video about "${config.topic}" ` +
    `for ${config.audience}. Goal: ${config.goal}. ` +
    `Duration: ${config.durationSeconds}s`;
  
  return {
    prompt: {
      value: promptValue,
      status: 'ready',
      schemaType: 'prompt',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(promptValue).length * 2,
    } as PortPayload<PromptValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<UserPromptConfig>[] = [
  {
    id: 'educational-tutorial',
    label: 'Educational Tutorial',
    config: {
      topic: 'How Photosynthesis Works',
      goal: 'Explain the process of photosynthesis clearly',
      audience: 'High school biology students',
      tone: 'educational',
      durationSeconds: 90,
    },
  },
  {
    id: 'cinematic-story',
    label: 'Cinematic Story',
    config: {
      topic: 'A Day in the Life of a Deep Sea Diver',
      goal: 'Create an immersive underwater experience',
      audience: 'Nature documentary enthusiasts',
      tone: 'cinematic',
      durationSeconds: 180,
    },
  },
  {
    id: 'playful-explainer',
    label: 'Playful Explainer',
    config: {
      topic: 'Why Cats Love Boxes',
      goal: 'Entertain and inform about cat behavior',
      audience: 'Pet owners and animal lovers',
      tone: 'playful',
      durationSeconds: 60,
    },
  },
  {
    id: 'dramatic-pitch',
    label: 'Dramatic Pitch',
    config: {
      topic: 'The Climate Crisis: A Call to Action',
      goal: 'Inspire urgent action on climate change',
      audience: 'General public, ages 18-45',
      tone: 'dramatic',
      durationSeconds: 240,
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * userPrompt Node Template
 * 
 * Non-executable: preview and run output are identical.
 * No async mockExecute needed.
 */
export const userPromptTemplate: NodeTemplate<UserPromptConfig> = {
  type: 'userPrompt',
  templateVersion: '1.0.0',
  title: 'User Prompt',
  category: 'input',
  description: 'Collects initial creative intent through structured form inputs. Generates a prompt payload that serves as the foundation for the AI video workflow.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: UserPromptConfigSchema,
  fixtures,
  executable: false,
  buildPreview,
};
