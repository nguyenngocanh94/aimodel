/**
 * scriptWriter Node Template - AiModel-9wx.5
 *
 * Plan §5.2: Transforms prompt intent into a structured script object.
 * Category: script. Input: prompt. Output: script.
 * Mock execution: deterministic from prompt + config (no providers).
 */

import { z } from 'zod';
import type { MockNodeExecutionArgs, NodeFixture, NodeTemplate } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Script payload (v1 — inspector-friendly structured script)
// ============================================================

export interface ScriptPayload {
  readonly title: string;
  readonly hook: string;
  readonly beats: readonly string[];
  readonly narration: string;
  readonly callToAction: string;
}

// ============================================================
// Configuration Schema (plan §5.2)
// ============================================================

export const ScriptWriterConfigSchema = z.object({
  style: z.string().min(1).max(200).describe('Voice / presentation style'),
  structure: z
    .enum(['three_act', 'problem_solution', 'story_arc', 'listicle'])
    .describe('High-level narrative structure'),
  includeHook: z.boolean().describe('Include an opening hook beat'),
  includeCTA: z.boolean().describe('Include a closing call to action'),
  targetDurationSeconds: z
    .number()
    .int()
    .min(5)
    .max(600)
    .describe('Target runtime in seconds'),
});

export type ScriptWriterConfig = z.infer<typeof ScriptWriterConfigSchema>;

// ============================================================
// Ports
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'prompt',
    label: 'Prompt',
    direction: 'input',
    dataType: 'prompt',
    required: true,
    multiple: false,
    description: 'Structured prompt from upstream (e.g. userPrompt)',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'script',
    label: 'Script',
    direction: 'output',
    dataType: 'script',
    required: true,
    multiple: false,
    description: 'Structured script: title, hook, beats, narration, CTA',
  },
];

const defaultConfig: ScriptWriterConfig = {
  style: 'Clear, conversational narration with concrete examples',
  structure: 'three_act',
  includeHook: true,
  includeCTA: true,
  targetDurationSeconds: 90,
};

// ============================================================
// Deterministic helpers
// ============================================================

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

function promptSummaryFromPayload(payload: PortPayload | undefined): string {
  const v = payload?.value;
  if (v === null || v === undefined) {
    return '';
  }
  if (typeof v === 'string') {
    return v.trim();
  }
  if (typeof v === 'object' && v !== null) {
    const o = v as Record<string, unknown>;
    const topic = typeof o.topic === 'string' ? o.topic : '';
    const goal = typeof o.goal === 'string' ? o.goal : '';
    const audience = typeof o.audience === 'string' ? o.audience : '';
    const tone = typeof o.tone === 'string' ? o.tone : '';
    const parts = [topic, goal, audience].filter((s) => s.length > 0);
    if (parts.length > 0) {
      return `${parts.join(' — ')}${tone ? ` (${tone})` : ''}`;
    }
  }
  return JSON.stringify(v);
}

function buildScriptPayload(args: {
  readonly config: Readonly<ScriptWriterConfig>;
  readonly promptSummary: string;
}): ScriptPayload {
  const { config, promptSummary } = args;
  const seed = stableHash(
    JSON.stringify({
      style: config.style,
      structure: config.structure,
      includeHook: config.includeHook,
      includeCTA: config.includeCTA,
      targetDurationSeconds: config.targetDurationSeconds,
      promptSummary,
    }),
  );

  const titleBase =
    promptSummary.length > 0
      ? promptSummary.split(' — ')[0]?.trim() ?? 'Untitled video'
      : 'Untitled video';

  const hookTemplates = [
    `Open with a bold question about: ${titleBase}`,
    `Start with a relatable moment, then pivot to: ${titleBase}`,
    `Lead with a single stat or contrast that frames: ${titleBase}`,
  ];
  const beatPools: readonly (readonly string[])[] = [
    [
      'Establish context and stakes',
      'Explain the core idea in plain language',
      'Show a concrete example or analogy',
      'Address a likely objection',
      'Land the takeaway',
    ],
    [
      'Name the problem in one sentence',
      'Walk through the approach step by step',
      'Prove it with a mini case study',
      'Give the viewer a checklist',
      'Close with what to do next',
    ],
    [
      'Set the scene',
      'Raise tension',
      'Deliver the insight',
      'Show the payoff',
      'Exit on a memorable line',
    ],
    [
      'Item 1 — the headline takeaway',
      'Item 2 — why it matters',
      'Item 3 — a quick example',
      'Item 4 — common mistake',
      'Item 5 — recap',
    ],
  ];

  const structureToPool: Record<ScriptWriterConfig['structure'], number> = {
    three_act: 0,
    problem_solution: 1,
    story_arc: 2,
    listicle: 3,
  };
  const pool = beatPools[structureToPool[config.structure]] ?? beatPools[0];
  const beatCount = Math.min(5, Math.max(3, 2 + pickIndex(seed, 4, 7)));
  const beats: string[] = [];
  for (let i = 0; i < beatCount; i++) {
    beats.push(pool[pickIndex(seed, pool.length, 13 + i)] ?? pool[0]);
  }

  const hook = config.includeHook
    ? hookTemplates[pickIndex(seed, hookTemplates.length, 5)] ?? hookTemplates[0]
    : '';

  const narration = [
    `Style: ${config.style}`,
    `Structure: ${config.structure} · Target ~${config.targetDurationSeconds}s`,
    promptSummary.length > 0 ? `Intent: ${promptSummary}` : 'Intent: (connect a prompt input)',
    `Beats: ${beats.map((b, i) => `${i + 1}. ${b}`).join(' ')}`,
  ].join('\n');

  const callToAction = config.includeCTA
    ? [
        'Subscribe for the next part',
        'Save this for your next edit',
        'Try one change today and note what improves',
        'Share what you would add in the comments',
      ][pickIndex(seed, 4, 17)] ?? 'Take one action from this video today.'
    : '';

  return {
    title: titleBase,
    hook,
    beats,
    narration,
    callToAction,
  };
}

function scriptPortPayload(args: {
  readonly script: ScriptPayload;
  readonly status: PortPayload['status'];
  readonly producedAt?: string;
}): PortPayload<ScriptPayload> {
  const previewText = [args.script.title, args.script.hook, args.script.beats[0]]
    .filter((s) => s && s.length > 0)
    .join(' · ')
    .slice(0, 220);

  return {
    value: args.script,
    status: args.status,
    schemaType: 'script',
    previewText,
    sizeBytesEstimate: JSON.stringify(args.script).length * 2,
    ...(args.producedAt !== undefined ? { producedAt: args.producedAt } : {}),
  };
}

function buildPreview(args: {
  readonly config: Readonly<ScriptWriterConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const promptPayload = args.inputs.prompt;
  const promptSummary = promptSummaryFromPayload(promptPayload);

  if (!promptPayload || promptSummary.length === 0) {
    return {
      script: {
        value: null,
        status: 'idle',
        schemaType: 'script',
        previewText: 'Connect a prompt to generate a script preview.',
      },
    };
  }

  const script = buildScriptPayload({ config: args.config, promptSummary });
  return {
    script: scriptPortPayload({ script, status: 'ready' }),
  };
}

async function mockExecute(
  args: MockNodeExecutionArgs<ScriptWriterConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const promptPayload = args.inputs.prompt;
  const promptSummary = promptSummaryFromPayload(promptPayload);

  if (!promptPayload || promptSummary.length === 0) {
    return {
      script: {
        value: null,
        status: 'error',
        schemaType: 'script',
        errorMessage: 'Missing required input: prompt',
      },
    };
  }

  const script = buildScriptPayload({ config: args.config, promptSummary });
  return {
    script: scriptPortPayload({
      script,
      status: 'success',
      producedAt: new Date().toISOString(),
    }),
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<ScriptWriterConfig>[] = [
  {
    id: 'educational-explainer',
    label: 'Educational explainer',
    config: {
      style: 'Friendly expert — short sentences, one idea per beat',
      structure: 'problem_solution',
      includeHook: true,
      includeCTA: true,
      targetDurationSeconds: 120,
    },
    previewInputs: {
      prompt: {
        value: {
          topic: 'How neural networks learn',
          goal: 'Build intuition without jargon',
          audience: 'CS students',
          tone: 'educational',
          durationSeconds: 120,
          generatedAt: '2026-04-02T00:00:00.000Z',
        },
        status: 'ready',
        schemaType: 'prompt',
      },
    },
  },
  {
    id: 'cinematic-short',
    label: 'Cinematic short',
    config: {
      style: 'Visual, sensory language with strong pacing',
      structure: 'story_arc',
      includeHook: true,
      includeCTA: false,
      targetDurationSeconds: 60,
    },
    previewInputs: {
      prompt: {
        value: {
          topic: 'Midnight train to the coast',
          goal: 'Create mood and tension',
          audience: 'Travel film fans',
          tone: 'cinematic',
          durationSeconds: 60,
          generatedAt: '2026-04-02T00:00:00.000Z',
        },
        status: 'ready',
        schemaType: 'prompt',
      },
    },
  },
];

// ============================================================
// Template
// ============================================================

export const scriptWriterTemplate: NodeTemplate<ScriptWriterConfig> = {
  type: 'scriptWriter',
  templateVersion: '1.0.0',
  title: 'Script Writer',
  category: 'script',
  description:
    'Turns a structured prompt into a script object with title, hook, beats, narration, and CTA. Mock execution is deterministic from prompt + config.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: ScriptWriterConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
