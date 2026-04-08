/**
 * trendResearcher Node Template
 *
 * Purpose: Researches current trends, cultural context, and content angles
 *          using social-connected LLMs like Grok and Gemini.
 * Category: script
 *
 * Inputs:
 *   - context (json) — optional, product analysis or any context
 *   - topic (text) — optional, freeform topic to research
 *
 * Output: trendBrief (json) — structured trend report
 *
 * Config:
 *   - provider: API provider (stub, grok, gemini)
 *   - apiKey: API key
 *   - model: model identifier
 *   - market: target market
 *   - platform: target platform
 *   - language: response language
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Trend Brief Payload
// ============================================================

export interface TrendBriefPayload {
  readonly trendingFormats: readonly string[];
  readonly trendingHashtags: readonly string[];
  readonly trendingSounds: readonly string[];
  readonly culturalMoments: readonly string[];
  readonly contentAngles: readonly string[];
  readonly audienceInsights: Record<string, unknown>;
  readonly avoidList: readonly string[];
}

// ============================================================
// Configuration Schema
// ============================================================

export const TrendResearcherConfigSchema = z.object({
  provider: z.string()
    .describe('API provider for trend research (stub, grok, gemini)'),
  apiKey: z.string().optional()
    .describe('API key for the provider'),
  model: z.string().optional()
    .describe('Model identifier'),
  market: z.enum(['vietnam', 'global', 'sea'])
    .describe('Target market for trend research'),
  platform: z.enum(['tiktok', 'youtube', 'instagram', 'all'])
    .describe('Target platform'),
  language: z.string()
    .describe('Response language code'),
});

export type TrendResearcherConfig = z.infer<typeof TrendResearcherConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'context',
    label: 'Context',
    direction: 'input',
    dataType: 'json',
    required: false,
    multiple: false,
    description: 'Product analysis or any context to research trends for',
  },
  {
    key: 'topic',
    label: 'Topic',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Freeform topic to research',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'trendBrief',
    label: 'Trend Brief',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Structured trend report with formats, hashtags, sounds, cultural moments, and content angles',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: TrendResearcherConfig = {
  provider: 'stub',
  apiKey: '',
  model: 'grok-3',
  market: 'vietnam',
  platform: 'tiktok',
  language: 'vi',
};

// ============================================================
// Deterministic Helpers
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

// ============================================================
// Stub Trend Data
// ============================================================

const stubFormatPools: Record<string, readonly string[]> = {
  tiktok: ['POV videos', 'before/after reveals', 'get ready with me', 'day in my life', 'silent vlogs', 'storytimes', 'outfit transitions', 'recipe tutorials'],
  youtube: ['long-form vlogs', 'video essays', 'shorts compilations', 'reaction videos', 'behind the scenes', 'documentary style', 'how-to guides'],
  instagram: ['carousel posts', 'reels with text overlay', 'collab posts', 'aesthetic flat lays', 'mini vlogs', 'story series'],
  all: ['POV videos', 'before/after reveals', 'short-form tutorials', 'storytimes', 'documentary style', 'aesthetic compilations'],
};

const stubHashtagPools: Record<string, readonly string[]> = {
  vietnam: ['#tiktokvietnam', '#fyp', '#xuhuong', '#trending', '#viral', '#reviewsanpham', '#muasamonline', '#cuocsongvietnam'],
  global: ['#fyp', '#trending', '#viral', '#foryou', '#explorepage', '#contentcreator', '#lifestyle', '#trendalert'],
  sea: ['#southeastasia', '#sea', '#trending', '#fyp', '#asianstyle', '#foodie', '#lifestyle', '#viral'],
};

const stubSoundPools: readonly string[] = [
  'Trending remix of classic Vietnamese song',
  'Viral TikTok original sound',
  'Lo-fi beats background',
  'Dramatic orchestral trending audio',
  'Catchy pop hook — viral chorus',
];

const stubCulturalMomentPools: Record<string, readonly string[]> = {
  vietnam: ['Mid-Autumn Festival prep', 'Back to school season', 'Vietnamese Teachers Day', 'Year-end sale season', 'Tet holiday countdown'],
  global: ['Summer vibes content', 'Back to school', 'Holiday gift guides', 'New year goals', 'Earth Day awareness'],
  sea: ['Songkran celebrations', 'Ramadan content', 'SEA Games hype', 'Regional food festivals', 'Monsoon season aesthetics'],
};

const stubAnglePool: readonly string[] = [
  'Authentic daily-use showcase',
  'Problem-solution storytelling',
  'Trend-jacking with product integration',
  'Behind-the-scenes of the brand',
  'User-generated content compilation',
  'Expert review / unboxing',
  'Comparison with alternatives',
];

const stubAvoidPool: readonly string[] = [
  'Overly polished ads (audience fatigue)',
  'Controversial political commentary',
  'Outdated meme formats',
  'Hard sell / pushy CTA',
  'Misleading claims',
];

function buildStubTrendBrief(
  config: TrendResearcherConfig,
  contextSummary: string,
): TrendBriefPayload {
  const seed = stableHash(JSON.stringify({ config, contextSummary }));
  const platformKey = config.platform === 'all' ? 'all' : config.platform;
  const marketKey = config.market;

  const formats = stubFormatPools[platformKey] ?? stubFormatPools.all;
  const hashtags = stubHashtagPools[marketKey] ?? stubHashtagPools.global;
  const culturalMoments = stubCulturalMomentPools[marketKey] ?? stubCulturalMomentPools.global;

  const formatCount = 3 + pickIndex(seed, 3, 1);
  const hashtagCount = 4 + pickIndex(seed, 3, 2);
  const soundCount = 2 + pickIndex(seed, 2, 3);
  const momentCount = 2 + pickIndex(seed, 2, 4);
  const angleCount = 3 + pickIndex(seed, 3, 5);
  const avoidCount = 2 + pickIndex(seed, 2, 6);

  const pick = <T>(pool: readonly T[], count: number, baseSalt: number): T[] => {
    const result: T[] = [];
    for (let i = 0; i < Math.min(count, pool.length); i++) {
      result.push(pool[pickIndex(seed, pool.length, baseSalt + i)] as T);
    }
    return [...new Set(result)];
  };

  return {
    trendingFormats: pick(formats, formatCount, 10),
    trendingHashtags: pick(hashtags, hashtagCount, 20),
    trendingSounds: pick(stubSoundPools, soundCount, 30),
    culturalMoments: pick(culturalMoments, momentCount, 40),
    contentAngles: pick(stubAnglePool, angleCount, 50),
    audienceInsights: {
      primaryAge: '18-24',
      peakActivity: '19:00-22:00',
      preferredLength: config.platform === 'youtube' ? '8-15 min' : '15-60s',
      engagementDrivers: ['authenticity', 'humor', 'relatability'],
    },
    avoidList: pick(stubAvoidPool, avoidCount, 60),
  };
}

// ============================================================
// Preview Builder
// ============================================================

function summarizeInputs(inputMap: Readonly<Record<string, PortPayload>>): string {
  const parts: string[] = [];
  const ctx = inputMap.context;
  if (ctx?.value !== null && ctx?.value !== undefined) {
    parts.push(typeof ctx.value === 'string' ? ctx.value : JSON.stringify(ctx.value));
  }
  const topic = inputMap.topic;
  if (topic?.value !== null && topic?.value !== undefined) {
    parts.push(String(topic.value));
  }
  return parts.join(' | ');
}

function buildPreview(args: {
  readonly config: Readonly<TrendResearcherConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  return {
    trendBrief: {
      value: null,
      status: 'idle',
      schemaType: 'json',
      previewText: `Trend research for ${args.config.market} / ${args.config.platform}`,
    } as PortPayload,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<TrendResearcherConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  // Simulate API call delay
  await new Promise(resolve => setTimeout(resolve, 100));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const contextSummary = summarizeInputs(inputs);
  const trendBrief = buildStubTrendBrief(config, contextSummary);

  const previewText = [
    `${trendBrief.trendingFormats.length} formats`,
    `${trendBrief.trendingHashtags.length} hashtags`,
    `${trendBrief.contentAngles.length} angles`,
    config.market,
    config.platform,
  ].join(' · ');

  return {
    trendBrief: {
      value: trendBrief,
      status: 'success',
      schemaType: 'json',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(trendBrief).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<TrendBriefPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<TrendResearcherConfig>[] = [
  {
    id: 'vietnam-tiktok-trends',
    label: 'Vietnam TikTok Trends',
    config: {
      provider: 'stub',
      apiKey: '',
      model: 'grok-3',
      market: 'vietnam',
      platform: 'tiktok',
      language: 'vi',
    },
    previewInputs: {
      context: {
        value: { product: 'Vietnamese coffee brand', target: 'Gen Z' },
        status: 'success',
        schemaType: 'json',
      },
      topic: {
        value: 'Vietnamese coffee culture trends on TikTok',
        status: 'success',
        schemaType: 'text',
      },
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * trendResearcher Node Template
 *
 * Executable: fetches current trends and cultural context for content creation
 * using social-connected LLMs (Grok, Gemini). v1 mock returns stub trend data.
 */
export const trendResearcherTemplate: NodeTemplate<TrendResearcherConfig> = {
  type: 'trendResearcher',
  templateVersion: '1.0.0',
  title: 'Trend Researcher',
  category: 'script',
  description: 'Researches current trends, cultural context, and content angles using social-connected LLMs like Grok and Gemini.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: TrendResearcherConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
