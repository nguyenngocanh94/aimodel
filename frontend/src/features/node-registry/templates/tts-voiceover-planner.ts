/**
 * ttsVoiceoverPlanner Node Template - AiModel-9wx.10
 *
 * Plan §5.7: Plans voiceover segments and metadata without synthesizing
 * actual audio. Category: audio. Input: script. Output: audioPlan.
 * Mock execution: deterministic from script + config (no providers).
 */

import { z } from 'zod';
import type { MockNodeExecutionArgs, NodeFixture, NodeTemplate } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// AudioPlan payload (v1 — timing segments + voiceover metadata)
// ============================================================

export interface AudioPlanSegment {
  readonly index: number;
  readonly text: string;
  readonly startSeconds: number;
  readonly durationSeconds: number;
  readonly voiceStyle: string;
}

export interface AudioPlanPayload {
  readonly segments: readonly AudioPlanSegment[];
  readonly totalDurationSeconds: number;
  readonly voiceStyle: string;
  readonly pace: 'slow' | 'normal' | 'fast';
  readonly genderStyle: 'masculine' | 'feminine' | 'neutral';
  readonly placeholderAudioUrl: string;
}

// ============================================================
// Configuration Schema (plan §5.7)
// ============================================================

export const TtsVoiceoverPlannerConfigSchema = z.object({
  voiceStyle: z
    .string()
    .min(1)
    .max(200)
    .describe('Voice style label (e.g. warm, authoritative, casual)'),
  pace: z
    .enum(['slow', 'normal', 'fast'])
    .describe('Speaking pace'),
  genderStyle: z
    .enum(['masculine', 'feminine', 'neutral'])
    .describe('Gender presentation of the voice'),
  includePauses: z
    .boolean()
    .describe('Insert brief pauses between segments'),
});

export type TtsVoiceoverPlannerConfig = z.infer<typeof TtsVoiceoverPlannerConfigSchema>;

// ============================================================
// Ports
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'script',
    label: 'Script',
    direction: 'input',
    dataType: 'script',
    required: true,
    multiple: false,
    description: 'Structured script from upstream (e.g. scriptWriter)',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'audioPlan',
    label: 'Audio Plan',
    direction: 'output',
    dataType: 'audioPlan',
    required: true,
    multiple: false,
    description: 'Voiceover timing plan: segments, durations, and placeholder URL',
  },
];

const defaultConfig: TtsVoiceoverPlannerConfig = {
  voiceStyle: 'warm',
  pace: 'normal',
  genderStyle: 'neutral',
  includePauses: true,
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

/** Words-per-minute lookup by pace. */
const WPM: Readonly<Record<TtsVoiceoverPlannerConfig['pace'], number>> = {
  slow: 120,
  normal: 150,
  fast: 185,
};

/** Estimate word count from a text string. */
function countWords(text: string): number {
  return text.trim().split(/\s+/).filter((w) => w.length > 0).length;
}

/** Estimate duration in seconds for a text chunk at the given pace. */
function estimateDuration(text: string, pace: TtsVoiceoverPlannerConfig['pace']): number {
  const words = countWords(text);
  const seconds = (words / WPM[pace]) * 60;
  return Math.max(0.5, Math.round(seconds * 100) / 100);
}

function scriptTextFromPayload(payload: PortPayload | undefined): readonly string[] {
  const v = payload?.value;
  if (v === null || v === undefined) {
    return [];
  }
  if (typeof v === 'string') {
    const trimmed = v.trim();
    return trimmed.length > 0 ? [trimmed] : [];
  }
  if (typeof v === 'object' && v !== null) {
    const o = v as Record<string, unknown>;
    const chunks: string[] = [];

    // Extract hook
    if (typeof o.hook === 'string' && o.hook.trim().length > 0) {
      chunks.push(o.hook.trim());
    }

    // Extract beats — may be string[] or { narration: string }[]
    if (Array.isArray(o.beats)) {
      for (const beat of o.beats) {
        if (typeof beat === 'string' && beat.trim().length > 0) {
          chunks.push(beat.trim());
        } else if (typeof beat === 'object' && beat !== null) {
          const b = beat as Record<string, unknown>;
          const narration = typeof b.narration === 'string' ? b.narration.trim() : '';
          if (narration.length > 0) {
            chunks.push(narration);
          }
        }
      }
    }

    // Extract narration blob (fallback / additive)
    if (typeof o.narration === 'string' && o.narration.trim().length > 0 && chunks.length === 0) {
      // Only use the narration blob when no structured beats were found
      chunks.push(o.narration.trim());
    }

    // Extract callToAction / cta
    const cta = typeof o.callToAction === 'string' ? o.callToAction : typeof o.cta === 'string' ? o.cta : '';
    if (cta.trim().length > 0) {
      chunks.push(cta.trim());
    }

    return chunks;
  }
  return [JSON.stringify(v)];
}

function buildAudioPlanPayload(args: {
  readonly config: Readonly<TtsVoiceoverPlannerConfig>;
  readonly scriptChunks: readonly string[];
}): AudioPlanPayload {
  const { config, scriptChunks } = args;
  const seed = stableHash(
    JSON.stringify({
      voiceStyle: config.voiceStyle,
      pace: config.pace,
      genderStyle: config.genderStyle,
      includePauses: config.includePauses,
      scriptChunks,
    }),
  );

  const pauseBase = config.includePauses ? 0.5 : 0;
  let cursor = 0;
  const segments: AudioPlanSegment[] = [];

  for (let i = 0; i < scriptChunks.length; i++) {
    const text = scriptChunks[i];
    const dur = estimateDuration(text, config.pace);
    segments.push({
      index: i,
      text,
      startSeconds: Math.round(cursor * 100) / 100,
      durationSeconds: dur,
      voiceStyle: config.voiceStyle,
    });
    cursor += dur;
    // Add pause between segments (not after the last one)
    if (config.includePauses && i < scriptChunks.length - 1) {
      cursor += pauseBase;
    }
  }

  const totalDurationSeconds = Math.round(cursor * 100) / 100;

  return {
    segments,
    totalDurationSeconds,
    voiceStyle: config.voiceStyle,
    pace: config.pace,
    genderStyle: config.genderStyle,
    placeholderAudioUrl: `placeholder://audio/tts-voiceover-${seed.slice(0, 8)}.mp3`,
  };
}

function audioPlanPortPayload(args: {
  readonly audioPlan: AudioPlanPayload;
  readonly status: PortPayload['status'];
  readonly producedAt?: string;
}): PortPayload<AudioPlanPayload> {
  const { audioPlan } = args;
  const previewText = [
    `${audioPlan.segments.length} segments`,
    `~${audioPlan.totalDurationSeconds}s`,
    audioPlan.voiceStyle,
    audioPlan.pace,
  ]
    .join(' · ')
    .slice(0, 220);

  return {
    value: audioPlan,
    status: args.status,
    schemaType: 'audioPlan',
    previewText,
    sizeBytesEstimate: JSON.stringify(audioPlan).length * 2,
    ...(args.producedAt !== undefined ? { producedAt: args.producedAt } : {}),
  };
}

function buildPreview(args: {
  readonly config: Readonly<TtsVoiceoverPlannerConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const scriptPayload = args.inputs.script;
  const scriptChunks = scriptTextFromPayload(scriptPayload);

  if (!scriptPayload || scriptChunks.length === 0) {
    return {
      audioPlan: {
        value: null,
        status: 'idle',
        schemaType: 'audioPlan',
        previewText: 'Connect a script to generate an audio plan preview.',
      },
    };
  }

  const audioPlan = buildAudioPlanPayload({ config: args.config, scriptChunks });
  return {
    audioPlan: audioPlanPortPayload({ audioPlan, status: 'ready' }),
  };
}

async function mockExecute(
  args: MockNodeExecutionArgs<TtsVoiceoverPlannerConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  if (args.signal.aborted) {
    throw new Error('Execution cancelled');
  }

  await Promise.resolve();

  if (args.signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const scriptPayload = args.inputs.script;
  const scriptChunks = scriptTextFromPayload(scriptPayload);

  if (!scriptPayload || scriptChunks.length === 0) {
    return {
      audioPlan: {
        value: null,
        status: 'error',
        schemaType: 'audioPlan',
        errorMessage: 'Missing required input: script',
      },
    };
  }

  const audioPlan = buildAudioPlanPayload({ config: args.config, scriptChunks });
  return {
    audioPlan: audioPlanPortPayload({
      audioPlan,
      status: 'success',
      producedAt: new Date().toISOString(),
    }),
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<TtsVoiceoverPlannerConfig>[] = [
  {
    id: 'warm-narration',
    label: 'Warm narration',
    config: {
      voiceStyle: 'warm',
      pace: 'normal',
      genderStyle: 'neutral',
      includePauses: true,
    },
    previewInputs: {
      script: {
        value: {
          title: 'How neural networks learn',
          hook: 'Open with a bold question about: How neural networks learn',
          beats: [
            'Establish context and stakes',
            'Explain the core idea in plain language',
            'Show a concrete example or analogy',
            'Land the takeaway',
          ],
          narration:
            'Style: Friendly expert — short sentences, one idea per beat\nStructure: problem_solution · Target ~120s\nIntent: How neural networks learn — Build intuition without jargon — CS students (educational)\nBeats: 1. Establish context 2. Explain core idea 3. Show example 4. Land takeaway',
          callToAction: 'Subscribe for the next part',
        },
        status: 'ready',
        schemaType: 'script',
      },
    },
  },
  {
    id: 'authoritative-slow',
    label: 'Authoritative slow voiceover',
    config: {
      voiceStyle: 'authoritative',
      pace: 'slow',
      genderStyle: 'masculine',
      includePauses: true,
    },
    previewInputs: {
      script: {
        value: {
          title: 'Midnight train to the coast',
          hook: 'Start with a relatable moment, then pivot to: Midnight train to the coast',
          beats: [
            'Set the scene',
            'Raise tension',
            'Deliver the insight',
            'Show the payoff',
            'Exit on a memorable line',
          ],
          narration:
            'Style: Visual, sensory language with strong pacing\nStructure: story_arc · Target ~60s\nIntent: Midnight train to the coast — Create mood and tension — Travel film fans (cinematic)',
          callToAction: '',
        },
        status: 'ready',
        schemaType: 'script',
      },
    },
  },
];

// ============================================================
// Template
// ============================================================

export const ttsVoiceoverPlannerTemplate: NodeTemplate<TtsVoiceoverPlannerConfig> = {
  type: 'ttsVoiceoverPlanner',
  templateVersion: '1.0.0',
  title: 'TTS Voiceover Planner',
  category: 'audio',
  description:
    'Plans voiceover segments and timing metadata from a structured script without synthesizing actual audio. Mock execution is deterministic from script + config.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: TtsVoiceoverPlannerConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
