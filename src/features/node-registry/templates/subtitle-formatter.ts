/**
 * subtitleFormatter Node Template - AiModel-9wx.11
 *
 * Plan §5.8: Formats subtitles from script or audio plan.
 * Category: video. Inputs: script (required), audioPlan (optional).
 * Output: subtitleAsset.
 * Mock execution: deterministic from script + config (+ optional audioPlan timing).
 */

import { z } from 'zod';
import type { MockNodeExecutionArgs, NodeFixture, NodeTemplate } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// SubtitleAsset payload (v1 — subtitle segments + style metadata)
// ============================================================

export interface SubtitleSegment {
  readonly index: number;
  readonly text: string;
  readonly startSeconds: number;
  readonly endSeconds: number;
}

export interface SubtitleStyle {
  readonly maxCharsPerLine: number;
  readonly linesPerCard: number;
  readonly preset: 'default' | 'minimal' | 'cinematic' | 'bold';
  readonly burnMode: 'soft' | 'burnedPreview';
}

export interface SubtitleAssetPayload {
  readonly segments: readonly SubtitleSegment[];
  readonly style: SubtitleStyle;
  readonly totalSegments: number;
  readonly totalDurationSeconds: number;
}

// ============================================================
// Configuration Schema (plan §5.8)
// ============================================================

export const SubtitleFormatterConfigSchema = z.object({
  maxCharsPerLine: z
    .number()
    .int()
    .min(20)
    .max(80)
    .default(42)
    .describe('Maximum characters per subtitle line'),
  linesPerCard: z
    .number()
    .int()
    .min(1)
    .max(3)
    .default(2)
    .describe('Maximum lines displayed per subtitle card'),
  stylePreset: z
    .enum(['default', 'minimal', 'cinematic', 'bold'])
    .default('default')
    .describe('Visual style preset for subtitle rendering'),
  burnMode: z
    .enum(['soft', 'burnedPreview'])
    .default('soft')
    .describe('Whether subtitles are soft-overlaid or burned into the preview'),
});

export type SubtitleFormatterConfig = z.infer<typeof SubtitleFormatterConfigSchema>;

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
  {
    key: 'audioPlan',
    label: 'Audio Plan',
    direction: 'input',
    dataType: 'audioPlan',
    required: false,
    multiple: false,
    description: 'Optional audio plan for timing alignment; timing is derived from script when absent',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'subtitleAsset',
    label: 'Subtitle Asset',
    direction: 'output',
    dataType: 'subtitleAsset',
    required: true,
    multiple: false,
    description: 'Formatted subtitle segments with style metadata',
  },
];

const defaultConfig: SubtitleFormatterConfig = {
  maxCharsPerLine: 42,
  linesPerCard: 2,
  stylePreset: 'default',
  burnMode: 'soft',
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

/** Words-per-minute used when deriving timing from script text (no audioPlan). */
const FALLBACK_WPM = 150;

/** Estimate word count from a text string. */
function countWords(text: string): number {
  return text.trim().split(/\s+/).filter((w) => w.length > 0).length;
}

/** Estimate duration in seconds for a text chunk at a fixed pace. */
function estimateDurationFromText(text: string): number {
  const words = countWords(text);
  const seconds = (words / FALLBACK_WPM) * 60;
  return Math.max(0.5, Math.round(seconds * 100) / 100);
}

// ============================================================
// Script text extraction
// ============================================================

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

    // Extract narration blob (fallback when no structured beats found)
    if (typeof o.narration === 'string' && o.narration.trim().length > 0 && chunks.length === 0) {
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

// ============================================================
// AudioPlan timing extraction
// ============================================================

interface AudioPlanTiming {
  readonly startSeconds: number;
  readonly endSeconds: number;
}

function extractAudioPlanTimings(payload: PortPayload | undefined): readonly AudioPlanTiming[] | null {
  if (!payload || payload.value === null || payload.value === undefined) {
    return null;
  }
  const v = payload.value as Record<string, unknown>;
  if (!Array.isArray(v.segments)) {
    return null;
  }

  const timings: AudioPlanTiming[] = [];
  for (const seg of v.segments) {
    if (typeof seg === 'object' && seg !== null) {
      const s = seg as Record<string, unknown>;
      const start = typeof s.startSeconds === 'number' ? s.startSeconds : 0;
      const dur = typeof s.durationSeconds === 'number' ? s.durationSeconds : 0;
      timings.push({
        startSeconds: Math.round(start * 100) / 100,
        endSeconds: Math.round((start + dur) * 100) / 100,
      });
    }
  }
  return timings.length > 0 ? timings : null;
}

// ============================================================
// Subtitle card text wrapping
// ============================================================

/**
 * Wraps a text chunk into subtitle-card-sized lines respecting
 * maxCharsPerLine and linesPerCard. If the text exceeds the card
 * capacity it is split into multiple cards represented as separate
 * segments sharing the same time window (evenly divided).
 */
function wrapTextIntoCards(
  text: string,
  maxCharsPerLine: number,
  linesPerCard: number,
): readonly string[] {
  const words = text.split(/\s+/).filter((w) => w.length > 0);
  if (words.length === 0) return [];

  // Build lines
  const lines: string[] = [];
  let currentLine = '';
  for (const word of words) {
    const candidate = currentLine.length === 0 ? word : `${currentLine} ${word}`;
    if (candidate.length <= maxCharsPerLine) {
      currentLine = candidate;
    } else {
      if (currentLine.length > 0) lines.push(currentLine);
      currentLine = word;
    }
  }
  if (currentLine.length > 0) lines.push(currentLine);

  // Group lines into cards
  const cards: string[] = [];
  for (let i = 0; i < lines.length; i += linesPerCard) {
    const cardLines = lines.slice(i, i + linesPerCard);
    cards.push(cardLines.join('\n'));
  }

  return cards;
}

// ============================================================
// Core subtitle building
// ============================================================

function buildSubtitleAssetPayload(args: {
  readonly config: Readonly<SubtitleFormatterConfig>;
  readonly scriptChunks: readonly string[];
  readonly audioTimings: readonly AudioPlanTiming[] | null;
}): SubtitleAssetPayload {
  const { config, scriptChunks, audioTimings } = args;

  // Deterministic seed — ensures stable output given the same inputs
  void stableHash(
    JSON.stringify({
      maxCharsPerLine: config.maxCharsPerLine,
      linesPerCard: config.linesPerCard,
      stylePreset: config.stylePreset,
      burnMode: config.burnMode,
      scriptChunks,
      audioTimings,
    }),
  );

  const allCards: { readonly text: string; readonly start: number; readonly end: number }[] = [];

  for (let chunkIdx = 0; chunkIdx < scriptChunks.length; chunkIdx++) {
    const text = scriptChunks[chunkIdx];
    const cards = wrapTextIntoCards(text, config.maxCharsPerLine, config.linesPerCard);

    if (cards.length === 0) continue;

    // Determine time window for this chunk
    let chunkStart: number;
    let chunkEnd: number;

    if (audioTimings && chunkIdx < audioTimings.length) {
      // Use audioPlan timing
      chunkStart = audioTimings[chunkIdx].startSeconds;
      chunkEnd = audioTimings[chunkIdx].endSeconds;
    } else {
      // Derive timing from script text
      const prevEnd = allCards.length > 0 ? allCards[allCards.length - 1].end : 0;
      chunkStart = prevEnd;
      chunkEnd = chunkStart + estimateDurationFromText(text);
    }

    // Evenly divide the chunk window across cards
    const cardDuration = (chunkEnd - chunkStart) / cards.length;
    for (let cardIdx = 0; cardIdx < cards.length; cardIdx++) {
      const start = Math.round((chunkStart + cardIdx * cardDuration) * 100) / 100;
      const end = Math.round((chunkStart + (cardIdx + 1) * cardDuration) * 100) / 100;
      allCards.push({ text: cards[cardIdx], start, end });
    }
  }

  const segments: readonly SubtitleSegment[] = allCards.map((card, idx) => ({
    index: idx,
    text: card.text,
    startSeconds: card.start,
    endSeconds: card.end,
  }));

  const totalDurationSeconds =
    segments.length > 0
      ? Math.round(segments[segments.length - 1].endSeconds * 100) / 100
      : 0;

  return {
    segments,
    style: {
      maxCharsPerLine: config.maxCharsPerLine,
      linesPerCard: config.linesPerCard,
      preset: config.stylePreset,
      burnMode: config.burnMode,
    },
    totalSegments: segments.length,
    totalDurationSeconds,
  };
}

// ============================================================
// PortPayload helper
// ============================================================

function subtitleAssetPortPayload(args: {
  readonly subtitleAsset: SubtitleAssetPayload;
  readonly status: PortPayload['status'];
  readonly producedAt?: string;
}): PortPayload<SubtitleAssetPayload> {
  const { subtitleAsset } = args;

  const previewText = [
    `${subtitleAsset.totalSegments} cards`,
    `~${subtitleAsset.totalDurationSeconds}s`,
    subtitleAsset.style.preset,
    subtitleAsset.style.burnMode,
  ]
    .join(' \u00b7 ')
    .slice(0, 220);

  return {
    value: subtitleAsset,
    status: args.status,
    schemaType: 'subtitleAsset',
    previewText,
    sizeBytesEstimate: JSON.stringify(subtitleAsset).length * 2,
    ...(args.producedAt !== undefined ? { producedAt: args.producedAt } : {}),
  };
}

// ============================================================
// buildPreview
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<SubtitleFormatterConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const scriptPayload = args.inputs.script;
  const scriptChunks = scriptTextFromPayload(scriptPayload);

  if (!scriptPayload || scriptChunks.length === 0) {
    return {
      subtitleAsset: {
        value: null,
        status: 'idle',
        schemaType: 'subtitleAsset',
        previewText: 'Connect a script to generate a subtitle preview.',
      },
    };
  }

  const audioTimings = extractAudioPlanTimings(args.inputs.audioPlan);
  const subtitleAsset = buildSubtitleAssetPayload({
    config: args.config,
    scriptChunks,
    audioTimings,
  });

  return {
    subtitleAsset: subtitleAssetPortPayload({ subtitleAsset, status: 'ready' }),
  };
}

// ============================================================
// mockExecute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<SubtitleFormatterConfig>,
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
      subtitleAsset: {
        value: null,
        status: 'error',
        schemaType: 'subtitleAsset',
        errorMessage: 'Missing required input: script',
      },
    };
  }

  const audioTimings = extractAudioPlanTimings(args.inputs.audioPlan);
  const subtitleAsset = buildSubtitleAssetPayload({
    config: args.config,
    scriptChunks,
    audioTimings,
  });

  return {
    subtitleAsset: subtitleAssetPortPayload({
      subtitleAsset,
      status: 'success',
      producedAt: new Date().toISOString(),
    }),
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<SubtitleFormatterConfig>[] = [
  {
    id: 'default-soft-subtitles',
    label: 'Default soft subtitles',
    config: {
      maxCharsPerLine: 42,
      linesPerCard: 2,
      stylePreset: 'default',
      burnMode: 'soft',
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
            'Style: Friendly expert \u2014 short sentences, one idea per beat\nStructure: problem_solution \u00b7 Target ~120s\nIntent: How neural networks learn \u2014 Build intuition without jargon \u2014 CS students (educational)\nBeats: 1. Establish context 2. Explain core idea 3. Show example 4. Land takeaway',
          callToAction: 'Subscribe for the next part',
        },
        status: 'ready',
        schemaType: 'script',
      },
    },
  },
  {
    id: 'cinematic-burned-with-audio',
    label: 'Cinematic burned preview with audio plan',
    config: {
      maxCharsPerLine: 36,
      linesPerCard: 1,
      stylePreset: 'cinematic',
      burnMode: 'burnedPreview',
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
          ],
          narration:
            'Style: Visual, sensory language with strong pacing\nStructure: story_arc \u00b7 Target ~60s\nIntent: Midnight train to the coast \u2014 Create mood and tension \u2014 Travel film fans (cinematic)',
          callToAction: '',
        },
        status: 'ready',
        schemaType: 'script',
      },
      audioPlan: {
        value: {
          segments: [
            { index: 0, text: 'Start with a relatable moment, then pivot to: Midnight train to the coast', startSeconds: 0, durationSeconds: 4.5, voiceStyle: 'authoritative' },
            { index: 1, text: 'Set the scene', startSeconds: 5, durationSeconds: 3, voiceStyle: 'authoritative' },
            { index: 2, text: 'Raise tension', startSeconds: 8.5, durationSeconds: 3, voiceStyle: 'authoritative' },
            { index: 3, text: 'Deliver the insight', startSeconds: 12, durationSeconds: 3.5, voiceStyle: 'authoritative' },
          ],
          totalDurationSeconds: 15.5,
          voiceStyle: 'authoritative',
          pace: 'slow',
          genderStyle: 'masculine',
          placeholderAudioUrl: 'placeholder://audio/tts-voiceover-abc12345.mp3',
        },
        status: 'ready',
        schemaType: 'audioPlan',
      },
    },
  },
];

// ============================================================
// Template
// ============================================================

export const subtitleFormatterTemplate: NodeTemplate<SubtitleFormatterConfig> = {
  type: 'subtitleFormatter',
  templateVersion: '1.0.0',
  title: 'Subtitle Formatter',
  category: 'video',
  description:
    'Formats subtitle segments and style metadata from a structured script. Optionally aligns timing to an upstream audio plan. Mock execution is deterministic from script + config.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: SubtitleFormatterConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
