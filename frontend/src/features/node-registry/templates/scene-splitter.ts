/**
 * sceneSplitter Node Template - AiModel-9wx.6
 *
 * Plan §5.3: Turns a script into a list of scenes.
 * Category: script. Input: script. Output: sceneList.
 */

import { z } from 'zod';
import type { MockNodeExecutionArgs, NodeFixture, NodeTemplate } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';
import type { ScriptPayload } from './script-writer';

// ============================================================
// Scene payload items (v1)
// ============================================================

export interface SceneItem {
  readonly sequenceIndex: number;
  readonly summary: string;
  readonly startSeconds: number;
  readonly durationSeconds: number;
  readonly shotIntent?: string;
  readonly visualPromptHints?: readonly string[];
}

// ============================================================
// Config (plan §5.3)
// ============================================================

export const SceneSplitterConfigSchema = z.object({
  sceneCountTarget: z
    .number()
    .int()
    .min(1)
    .max(20)
    .describe('Target number of scenes to derive'),
  maxSceneDurationSeconds: z
    .number()
    .int()
    .min(5)
    .max(600)
    .describe('Upper bound per scene for timing split'),
  includeShotIntent: z.boolean().describe('Include a shot intent line per scene'),
  includeVisualPromptHints: z.boolean().describe('Include visual prompt hints per scene'),
});

export type SceneSplitterConfig = z.infer<typeof SceneSplitterConfigSchema>;

const inputs: readonly PortDefinition[] = [
  {
    key: 'script',
    label: 'Script',
    direction: 'input',
    dataType: 'script',
    required: true,
    multiple: false,
    description: 'Structured script from scriptWriter',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'scenes',
    label: 'Scenes',
    direction: 'output',
    dataType: 'sceneList',
    required: true,
    multiple: false,
    description: 'Ordered list of scenes with timing and optional shot/hint metadata',
  },
];

const defaultConfig: SceneSplitterConfig = {
  sceneCountTarget: 5,
  maxSceneDurationSeconds: 45,
  includeShotIntent: true,
  includeVisualPromptHints: true,
};

function stableHash(input: string): string {
  let h = 5381;
  for (let i = 0; i < input.length; i++) {
    h = Math.imul(31, h) + input.charCodeAt(i);
  }
  return (h >>> 0).toString(16).padStart(8, '0');
}

function pickIndex(seedHex: string, modulo: number, salt: number): number {
  const n = Number.parseInt(seedHex.slice(0, 8), 16) ^ salt;
  return Math.abs(n) % modulo;
}

function scriptFromPayload(payload: PortPayload | undefined): ScriptPayload | null {
  const v = payload?.value;
  if (v === null || v === undefined || typeof v !== 'object') {
    return null;
  }
  const o = v as Record<string, unknown>;
  const title = typeof o.title === 'string' ? o.title : '';
  const beatsRaw = o.beats;
  const beats: readonly string[] =
    Array.isArray(beatsRaw) && beatsRaw.every((b) => typeof b === 'string')
      ? (beatsRaw as readonly string[])
      : [];
  if (title.length === 0) {
    return null;
  }
  return {
    title,
    hook: typeof o.hook === 'string' ? o.hook : '',
    beats,
    narration: typeof o.narration === 'string' ? o.narration : '',
    callToAction: typeof o.callToAction === 'string' ? o.callToAction : '',
  };
}

const SHOT_INTENTS: readonly string[] = [
  'Wide establishing — context and tone',
  'Medium — subject and action',
  'Close-up — emotion or detail',
  'Insert — graphic or prop',
  'Transition — movement between ideas',
];

const HINT_PREFIXES: readonly string[] = [
  'Lighting: soft key, subtle rim',
  'Camera: slow push-in',
  'Color grade: warm highlights',
  'Composition: rule of thirds, negative space',
  'Motion: handheld micro-movement',
];

function buildSceneList(args: {
  readonly script: ScriptPayload;
  readonly config: Readonly<SceneSplitterConfig>;
}): readonly SceneItem[] {
  const { script, config } = args;
  const count = Math.max(1, Math.min(config.sceneCountTarget, 20));
  const seed = stableHash(
    JSON.stringify({
      title: script.title,
      beats: script.beats,
      sceneCountTarget: config.sceneCountTarget,
      maxSceneDurationSeconds: config.maxSceneDurationSeconds,
      includeShotIntent: config.includeShotIntent,
      includeVisualPromptHints: config.includeVisualPromptHints,
    }),
  );

  const beatSource =
    script.beats.length > 0 ? script.beats : [script.title, script.hook].filter((s) => s.length > 0);
  const fallback = script.title;

  const segment = Math.max(5, Math.min(config.maxSceneDurationSeconds, 240));

  const scenes: SceneItem[] = [];
  for (let i = 0; i < count; i++) {
    const beat = beatSource[i % beatSource.length] ?? fallback;
    const summary = `${script.title} — ${beat}`;

    const shotIntent = config.includeShotIntent
      ? SHOT_INTENTS[pickIndex(seed, SHOT_INTENTS.length, i + 3)] ?? SHOT_INTENTS[0]
      : undefined;

    const visualPromptHints = config.includeVisualPromptHints
      ? ([
          `${HINT_PREFIXES[pickIndex(seed, HINT_PREFIXES.length, i * 5 + 1)]} — scene ${i + 1}`,
          `${HINT_PREFIXES[pickIndex(seed, HINT_PREFIXES.length, i * 5 + 2)]}`,
        ] as const)
      : undefined;

    scenes.push({
      sequenceIndex: i,
      summary,
      startSeconds: Math.round(segment * i * 10) / 10,
      durationSeconds: Math.round(segment * 10) / 10,
      ...(shotIntent !== undefined ? { shotIntent } : {}),
      ...(visualPromptHints !== undefined ? { visualPromptHints } : {}),
    });
  }

  return scenes;
}

function sceneListPortPayload(args: {
  readonly scenes: readonly SceneItem[];
  readonly status: PortPayload['status'];
  readonly producedAt?: string;
}): PortPayload<readonly SceneItem[]> {
  const previewText = args.scenes
    .slice(0, 3)
    .map((s) => s.summary)
    .join(' | ')
    .slice(0, 220);

  return {
    value: args.scenes,
    status: args.status,
    schemaType: 'sceneList',
    previewText,
    sizeBytesEstimate: JSON.stringify(args.scenes).length * 2,
    ...(args.producedAt !== undefined ? { producedAt: args.producedAt } : {}),
  };
}

function buildPreview(args: {
  readonly config: Readonly<SceneSplitterConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const scriptPayload = args.inputs.script;
  const script = scriptFromPayload(scriptPayload);

  if (!script) {
    return {
      scenes: {
        value: null,
        status: 'idle',
        schemaType: 'sceneList',
        previewText: 'Connect a script input to split into scenes.',
      },
    };
  }

  const scenes = buildSceneList({ script, config: args.config });
  return {
    scenes: sceneListPortPayload({ scenes, status: 'ready' }),
  };
}

async function mockExecute(
  args: MockNodeExecutionArgs<SceneSplitterConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const script = scriptFromPayload(args.inputs.script);

  if (!script) {
    return {
      scenes: {
        value: null,
        status: 'error',
        schemaType: 'sceneList',
        errorMessage: 'Missing required input: script',
      },
    };
  }

  const scenes = buildSceneList({ script, config: args.config });
  return {
    scenes: sceneListPortPayload({
      scenes,
      status: 'success',
      producedAt: new Date().toISOString(),
    }),
  };
}

const sampleScript: ScriptPayload = {
  title: 'Demo script',
  hook: 'Open with curiosity',
  beats: ['Context', 'Tension', 'Insight', 'Payoff'],
  narration: 'Full narration body',
  callToAction: 'Subscribe',
};

const fixtures: readonly NodeFixture<SceneSplitterConfig>[] = [
  {
    id: 'five-scene-doc',
    label: 'Five scenes with hints',
    config: {
      sceneCountTarget: 5,
      maxSceneDurationSeconds: 40,
      includeShotIntent: true,
      includeVisualPromptHints: true,
    },
    previewInputs: {
      script: {
        value: sampleScript,
        status: 'ready',
        schemaType: 'script',
      },
    },
  },
  {
    id: 'three-scene-minimal',
    label: 'Three scenes — no hints',
    config: {
      sceneCountTarget: 3,
      maxSceneDurationSeconds: 60,
      includeShotIntent: false,
      includeVisualPromptHints: false,
    },
    previewInputs: {
      script: {
        value: sampleScript,
        status: 'ready',
        schemaType: 'script',
      },
    },
  },
];

export const sceneSplitterTemplate: NodeTemplate<SceneSplitterConfig> = {
  type: 'sceneSplitter',
  templateVersion: '1.0.0',
  title: 'Scene Splitter',
  category: 'script',
  description:
    'Splits a structured script into an ordered scene list with timing and optional shot intent / visual prompt hints.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: SceneSplitterConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
