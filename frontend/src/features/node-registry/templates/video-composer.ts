/**
 * videoComposer Node Template - AiModel-9wx.12
 *
 * Purpose: Combines visual assets, optional subtitles, and optional audio plan
 *          into a composed video artifact descriptor.
 * Category: video
 *
 * Inputs:
 *   - visualAssets (imageAssetList) — required
 *   - audioPlan               — optional
 *   - subtitleAsset           — optional
 *
 * Output: videoAsset
 *
 * Config:
 *   - aspectRatio: '16:9' | '9:16' | '1:1' | '4:3'
 *   - transitionStyle: 'cut' | 'fade' | 'dissolve' | 'wipe'
 *   - fps: 24 | 30 | 60
 *   - includeTitleCard: boolean
 *   - musicBed: 'none' | 'placeholder'
 *
 * v1 does NOT render true MP4 — primary preview is animated storyboard player.
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const VideoComposerConfigSchema = z.object({
  aspectRatio: z.enum(['16:9', '9:16', '1:1', '4:3'])
    .describe('Output video aspect ratio'),
  transitionStyle: z.enum(['cut', 'fade', 'dissolve', 'wipe'])
    .describe('Transition style applied between visual segments'),
  fps: z.union([z.literal(24), z.literal(30), z.literal(60)])
    .describe('Frames per second for the composed video'),
  includeTitleCard: z.boolean()
    .describe('Whether to prepend a title card segment'),
  musicBed: z.enum(['none', 'placeholder'])
    .describe('Background music bed option'),
});

export type VideoComposerConfig = z.infer<typeof VideoComposerConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

interface ImageAsset {
  readonly assetId: string;
  readonly sceneIndex: number;
  readonly role: string;
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

interface TimingSegment {
  readonly segmentId: string;
  readonly text: string;
  readonly startTimeSeconds: number;
  readonly durationSeconds: number;
  readonly wordCount: number;
  readonly pauseAfterSeconds: number;
}

interface AudioPlanValue {
  readonly segments: readonly TimingSegment[];
  readonly totalDurationSeconds: number;
  readonly totalWordCount: number;
  readonly voiceStyle: string;
  readonly pace: string;
  readonly estimatedWordsPerMinute: number;
  readonly placeholderUrl: string;
  readonly generatedAt: string;
}

interface SubtitleCue {
  readonly index: number;
  readonly startTimeSeconds: number;
  readonly endTimeSeconds: number;
  readonly text: string;
}

interface SubtitleAssetValue {
  readonly cues: readonly SubtitleCue[];
  readonly totalCues: number;
  readonly format: string;
}

interface TimelineEntry {
  readonly index: number;
  readonly type: 'image' | 'transition' | 'titleCard';
  readonly assetRef?: string;
  readonly durationSeconds: number;
  readonly transition?: string;
}

interface StoryboardPreview {
  readonly frameCount: number;
  readonly frameDurationMs: number;
  readonly transitionStyle: string;
}

export interface VideoAssetPayload {
  readonly timeline: readonly TimelineEntry[];
  readonly totalDurationSeconds: number;
  readonly aspectRatio: string;
  readonly fps: number;
  readonly posterFrameUrl: string;
  readonly storyboardPreview: StoryboardPreview;
  readonly hasAudio: boolean;
  readonly hasSubtitles: boolean;
  readonly musicBed: string;
}

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'visualAssets',
    label: 'Visual Assets',
    direction: 'input',
    dataType: 'imageAssetList',
    required: true,
    multiple: false,
    description: 'Image asset list to compose into the video timeline',
  },
  {
    key: 'audioPlan',
    label: 'Audio Plan',
    direction: 'input',
    dataType: 'audioPlan',
    required: false,
    multiple: false,
    description: 'Optional TTS voiceover timing plan for audio track',
  },
  {
    key: 'subtitleAsset',
    label: 'Subtitle Asset',
    direction: 'input',
    dataType: 'subtitleAsset',
    required: false,
    multiple: false,
    description: 'Optional subtitle cues to overlay on the video',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'videoAsset',
    label: 'Video Asset',
    direction: 'output',
    dataType: 'videoAsset',
    required: true,
    multiple: false,
    description: 'Composed video asset descriptor with timeline, storyboard preview, and metadata',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: VideoComposerConfig = {
  aspectRatio: '16:9',
  transitionStyle: 'fade',
  fps: 30,
  includeTitleCard: true,
  musicBed: 'none',
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

// ============================================================
// Duration Helpers
// ============================================================

const DEFAULT_IMAGE_DURATION_SECONDS = 4;
const TITLE_CARD_DURATION_SECONDS = 3;
const TRANSITION_DURATION_SECONDS = 0.5;

function computeImageDuration(
  index: number,
  audioPlan: AudioPlanValue | null,
  totalAssets: number,
): number {
  if (!audioPlan || audioPlan.segments.length === 0) {
    return DEFAULT_IMAGE_DURATION_SECONDS;
  }

  // Distribute audio duration proportionally across assets
  const perAsset = audioPlan.totalDurationSeconds / totalAssets;
  // Slight variance based on segment index for realism
  const variance = (index % 2 === 0 ? 0.2 : -0.2);
  return Math.max(1, perAsset + variance);
}

// ============================================================
// Composition Logic
// ============================================================

function composeVideoAsset(
  assetList: ImageAssetListValue,
  config: VideoComposerConfig,
  audioPlan: AudioPlanValue | null,
  subtitleAsset: SubtitleAssetValue | null,
  seed: string,
): VideoAssetPayload {
  const timeline: TimelineEntry[] = [];
  let currentIndex = 0;
  let totalDuration = 0;

  // Title card
  if (config.includeTitleCard) {
    timeline.push({
      index: currentIndex,
      type: 'titleCard',
      durationSeconds: TITLE_CARD_DURATION_SECONDS,
    });
    totalDuration += TITLE_CARD_DURATION_SECONDS;
    currentIndex++;
  }

  // Build image + transition entries
  assetList.assets.forEach((asset, assetIdx) => {
    // Insert transition before every image except the first
    if (assetIdx > 0 && config.transitionStyle !== 'cut') {
      timeline.push({
        index: currentIndex,
        type: 'transition',
        durationSeconds: TRANSITION_DURATION_SECONDS,
        transition: config.transitionStyle,
      });
      totalDuration += TRANSITION_DURATION_SECONDS;
      currentIndex++;
    }

    const imageDuration = computeImageDuration(
      assetIdx,
      audioPlan,
      assetList.assets.length,
    );

    timeline.push({
      index: currentIndex,
      type: 'image',
      assetRef: asset.assetId,
      durationSeconds: imageDuration,
      transition: config.transitionStyle === 'cut' ? undefined : config.transitionStyle,
    });
    totalDuration += imageDuration;
    currentIndex++;
  });

  const frozenTimeline = Object.freeze(timeline);

  const frameCount = assetList.assets.length + (config.includeTitleCard ? 1 : 0);
  const frameDurationMs = frameCount > 0
    ? Math.round((totalDuration / frameCount) * 1000)
    : 0;

  return {
    timeline: frozenTimeline,
    totalDurationSeconds: Math.round(totalDuration * 100) / 100,
    aspectRatio: config.aspectRatio,
    fps: config.fps,
    posterFrameUrl: `placeholder://video/poster/${seed}.jpg`,
    storyboardPreview: {
      frameCount,
      frameDurationMs,
      transitionStyle: config.transitionStyle,
    },
    hasAudio: audioPlan !== null,
    hasSubtitles: subtitleAsset !== null,
    musicBed: config.musicBed,
  };
}

// ============================================================
// Input Extraction Helpers
// ============================================================

function extractAudioPlan(
  inputs: Readonly<Record<string, PortPayload>>,
): AudioPlanValue | null {
  const payload = inputs.audioPlan;
  if (!payload || payload.value === null || payload.value === undefined) {
    return null;
  }
  return payload.value as AudioPlanValue;
}

function extractSubtitleAsset(
  inputs: Readonly<Record<string, PortPayload>>,
): SubtitleAssetValue | null {
  const payload = inputs.subtitleAsset;
  if (!payload || payload.value === null || payload.value === undefined) {
    return null;
  }
  return payload.value as SubtitleAssetValue;
}

// ============================================================
// Preview Metadata Helpers
// ============================================================

function buildPreviewText(
  videoAsset: VideoAssetPayload,
  config: VideoComposerConfig,
): string {
  const parts: string[] = [
    `${videoAsset.timeline.filter(e => e.type === 'image').length} clips`,
    `${videoAsset.totalDurationSeconds}s`,
    config.aspectRatio,
    `${config.fps}fps`,
    config.transitionStyle,
  ];

  if (videoAsset.hasAudio) parts.push('audio');
  if (videoAsset.hasSubtitles) parts.push('subs');
  if (config.musicBed !== 'none') parts.push('music');

  return parts.join(' · ');
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<VideoComposerConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;

  const visualPayload = inputs.visualAssets;
  if (!visualPayload || visualPayload.value === null) {
    return {
      videoAsset: {
        value: null,
        status: 'idle',
        schemaType: 'videoAsset',
        previewText: 'Waiting for visual assets input...',
      } as PortPayload,
    };
  }

  const assetList = visualPayload.value as ImageAssetListValue;
  const audioPlan = extractAudioPlan(inputs);
  const subtitleAsset = extractSubtitleAsset(inputs);

  const seed = stableHash(
    JSON.stringify({
      assets: assetList.count,
      config,
      hasAudio: audioPlan !== null,
      hasSubs: subtitleAsset !== null,
    }),
  );

  const videoAsset = composeVideoAsset(assetList, config, audioPlan, subtitleAsset, seed);
  const previewText = buildPreviewText(videoAsset, config);

  return {
    videoAsset: {
      value: videoAsset,
      status: 'ready',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(videoAsset).length * 2,
    } as PortPayload<VideoAssetPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<VideoComposerConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const visualPayload = inputs.visualAssets;
  if (!visualPayload || visualPayload.value === null) {
    return {
      videoAsset: {
        value: null,
        status: 'error',
        schemaType: 'videoAsset',
        errorMessage: 'Missing required visualAssets input',
      } as PortPayload,
    };
  }

  // Simulate processing time
  await new Promise(resolve => setTimeout(resolve, 120));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const assetList = visualPayload.value as ImageAssetListValue;
  const audioPlan = extractAudioPlan(inputs);
  const subtitleAsset = extractSubtitleAsset(inputs);

  const seed = stableHash(
    JSON.stringify({
      assets: assetList.count,
      config,
      hasAudio: audioPlan !== null,
      hasSubs: subtitleAsset !== null,
    }),
  );

  const videoAsset = composeVideoAsset(assetList, config, audioPlan, subtitleAsset, seed);
  const previewText = buildPreviewText(videoAsset, config);

  return {
    videoAsset: {
      value: videoAsset,
      status: 'success',
      schemaType: 'videoAsset',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(videoAsset).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<VideoAssetPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleVisualAssets: PortPayload = {
  value: {
    assets: [
      {
        assetId: 'asset-0-0',
        sceneIndex: 0,
        role: 'background',
        placeholderUrl: 'placeholder://image/1024x1024/seed-100/frame-0.png',
        localFileName: 'scene-0-asset.png',
        resolution: '1024x1024',
        metadata: { prompt: 'Mountain landscape at sunrise', seed: 100, stylePreset: 'cinematic' },
      },
      {
        assetId: 'asset-1-1',
        sceneIndex: 1,
        role: 'foreground',
        placeholderUrl: 'placeholder://image/1024x1024/seed-200/frame-1.png',
        localFileName: 'scene-1-asset.png',
        resolution: '1024x1024',
        metadata: { prompt: 'Close-up of mountain flowers', seed: 200, stylePreset: 'cinematic' },
      },
      {
        assetId: 'asset-2-2',
        sceneIndex: 2,
        role: 'background',
        placeholderUrl: 'placeholder://image/1024x1024/seed-300/frame-2.png',
        localFileName: 'scene-2-asset.png',
        resolution: '1024x1024',
        metadata: { prompt: 'Wide valley panorama', seed: 300, stylePreset: 'cinematic' },
      },
    ],
    count: 3,
    resolution: '1024x1024',
  },
  status: 'success',
  schemaType: 'imageAssetList',
};

const sampleAudioPlan: PortPayload = {
  value: {
    segments: [
      { segmentId: 'seg-0', text: 'Welcome to this journey.', startTimeSeconds: 0, durationSeconds: 3, wordCount: 5, pauseAfterSeconds: 0.5 },
      { segmentId: 'seg-1', text: 'Nature unfolds before us.', startTimeSeconds: 3.5, durationSeconds: 3, wordCount: 5, pauseAfterSeconds: 0.5 },
      { segmentId: 'seg-2', text: 'The valley stretches wide.', startTimeSeconds: 7, durationSeconds: 3, wordCount: 5, pauseAfterSeconds: 0 },
    ],
    totalDurationSeconds: 10,
    totalWordCount: 15,
    voiceStyle: 'conversational',
    pace: 'normal',
    estimatedWordsPerMinute: 150,
    placeholderUrl: 'placeholder://audio/tts-voiceover.mp3',
    generatedAt: '2026-01-01T00:00:00.000Z',
  },
  status: 'success',
  schemaType: 'audioPlan',
};

const sampleSubtitleAsset: PortPayload = {
  value: {
    cues: [
      { index: 0, startTimeSeconds: 0, endTimeSeconds: 3, text: 'Welcome to this journey.' },
      { index: 1, startTimeSeconds: 3.5, endTimeSeconds: 6.5, text: 'Nature unfolds before us.' },
      { index: 2, startTimeSeconds: 7, endTimeSeconds: 10, text: 'The valley stretches wide.' },
    ],
    totalCues: 3,
    format: 'srt',
  },
  status: 'success',
  schemaType: 'subtitleAsset',
};

const fixtures: readonly NodeFixture<VideoComposerConfig>[] = [
  {
    id: 'basic-fade-16x9',
    label: 'Basic Fade 16:9',
    config: {
      aspectRatio: '16:9',
      transitionStyle: 'fade',
      fps: 30,
      includeTitleCard: true,
      musicBed: 'none',
    },
    previewInputs: { visualAssets: sampleVisualAssets },
  },
  {
    id: 'full-composition-with-audio',
    label: 'Full Composition with Audio & Subtitles',
    config: {
      aspectRatio: '16:9',
      transitionStyle: 'dissolve',
      fps: 24,
      includeTitleCard: true,
      musicBed: 'placeholder',
    },
    previewInputs: {
      visualAssets: sampleVisualAssets,
      audioPlan: sampleAudioPlan,
      subtitleAsset: sampleSubtitleAsset,
    },
  },
  {
    id: 'vertical-cut-no-title',
    label: 'Vertical Cut No Title Card',
    config: {
      aspectRatio: '9:16',
      transitionStyle: 'cut',
      fps: 60,
      includeTitleCard: false,
      musicBed: 'none',
    },
    previewInputs: { visualAssets: sampleVisualAssets },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * videoComposer Node Template
 *
 * Executable: combines visual assets, optional subtitles, and optional audio plan
 * into a composed video artifact descriptor. v1 produces an animated storyboard
 * preview recipe — does NOT render a true MP4.
 */
export const videoComposerTemplate: NodeTemplate<VideoComposerConfig> = {
  type: 'videoComposer',
  templateVersion: '1.0.0',
  title: 'Video Composer',
  category: 'video',
  description: 'Combines visual assets, optional subtitles, and optional audio plan into a composed video artifact. Produces a timeline summary, poster frame URL, animated storyboard preview recipe, and preview metadata for subtitles, transitions, and audio timing. v1 primary preview is an animated storyboard player.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: VideoComposerConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
