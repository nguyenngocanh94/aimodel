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

import type { NodeTemplate, NodeFixture } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Type
// Backend manifest is the authoritative source for config schema and defaults (NM3+).
// The type alias is kept for use in buildPreview signatures.
// ============================================================

export type UserPromptConfig = {
  readonly topic: string;
  readonly goal: string;
  readonly audience: string;
  readonly tone: 'educational' | 'cinematic' | 'playful' | 'dramatic';
  readonly durationSeconds: number;
};

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
// configSchema is intentionally omitted (NM3 pilot strip) — backend manifest is
// the authoritative source. defaultConfig is an empty sentinel; actual defaults
// come from manifestEntry.defaultConfig at runtime via useResolvedNodeTemplate.
export const userPromptTemplate: NodeTemplate<UserPromptConfig> = {
  type: 'userPrompt',
  templateVersion: '1.0.0',
  title: 'User Prompt',
  category: 'input',
  description: 'Collects initial creative intent through structured form inputs. Generates a prompt payload that serves as the foundation for the AI video workflow.',
  inputs,
  outputs,
  defaultConfig: {} as unknown as UserPromptConfig,
  fixtures,
  executable: false,
  buildPreview,
};
