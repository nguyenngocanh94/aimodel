/**
 * finalExport Node Template - AiModel-9wx.14
 *
 * Plan §5.11: Packages the output into an export descriptor.
 * Category: output
 *
 * Input: videoAsset (videoAsset type)
 * Output: exportBundle (json type)
 *
 * Config:
 *   - fileNamePattern: string (e.g. '{name}-{date}-{resolution}')
 *   - includeMetadata: boolean (default true)
 *   - includeWorkflowSpecReference: boolean (default false)
 *
 * Behavior: Produces exportable JSON descriptor and synthetic file info.
 * Mock execution: deterministic from videoAsset + config.
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// ExportBundle Payload
// ============================================================

export interface ExportBundlePayload {
  readonly fileName: string;
  readonly format: 'mp4';
  readonly fileSizeBytesEstimate: number;
  readonly durationSeconds: number;
  readonly resolution: string;
  readonly exportedAt: string;
  readonly metadata?: {
    readonly workflowId: string;
    readonly workflowName: string;
    readonly exportVersion: string;
    readonly nodeCount: number;
  };
  readonly workflowSpecRef?: string;
}

// ============================================================
// Configuration Schema (plan §5.11)
// ============================================================

export const FinalExportConfigSchema = z.object({
  fileNamePattern: z.string().min(1).max(200)
    .describe('Filename pattern using placeholders like {name}, {date}, {resolution}'),
  includeMetadata: z.boolean()
    .describe('Include workflow metadata in the export bundle'),
  includeWorkflowSpecReference: z.boolean()
    .describe('Include a reference to the workflow specification'),
});

export type FinalExportConfig = z.infer<typeof FinalExportConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'videoAsset',
    label: 'Video Asset',
    direction: 'input',
    dataType: 'videoAsset',
    required: true,
    multiple: false,
    description: 'Composed video asset to package for export',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'exportBundle',
    label: 'Export Bundle',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Export descriptor with file info, metadata, and workflow reference',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: FinalExportConfig = {
  fileNamePattern: '{name}-{date}-{resolution}',
  includeMetadata: true,
  includeWorkflowSpecReference: false,
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
// Video Asset Extraction
// ============================================================

interface VideoAssetValue {
  readonly totalDurationSeconds: number;
  readonly aspectRatio: string;
  readonly fps: number;
  readonly timeline?: readonly unknown[];
  readonly hasAudio?: boolean;
  readonly hasSubtitles?: boolean;
}

function extractVideoAsset(
  inputsRecord: Readonly<Record<string, PortPayload>>,
): VideoAssetValue | null {
  const payload = inputsRecord.videoAsset;
  if (!payload || payload.value === null || payload.value === undefined) {
    return null;
  }
  return payload.value as VideoAssetValue;
}

// ============================================================
// Resolution Derivation
// ============================================================

const ASPECT_RATIO_RESOLUTIONS: Readonly<Record<string, string>> = {
  '16:9': '1920x1080',
  '9:16': '1080x1920',
  '1:1': '1080x1080',
  '4:3': '1440x1080',
};

function deriveResolution(aspectRatio: string): string {
  return ASPECT_RATIO_RESOLUTIONS[aspectRatio] ?? '1920x1080';
}

// ============================================================
// Filename Generation
// ============================================================

function generateFileName(pattern: string, seed: string, resolution: string): string {
  const dateStr = new Date().toISOString().slice(0, 10);
  const name = `export-${seed.slice(0, 6)}`;

  return pattern
    .replace('{name}', name)
    .replace('{date}', dateStr)
    .replace('{resolution}', resolution.replace(':', 'x'))
    + '.mp4';
}

// ============================================================
// Export Bundle Builder
// ============================================================

function buildExportBundle(args: {
  readonly videoAsset: VideoAssetValue;
  readonly config: Readonly<FinalExportConfig>;
  readonly seed: string;
}): ExportBundlePayload {
  const { videoAsset, config, seed } = args;

  const resolution = deriveResolution(videoAsset.aspectRatio);
  const fileName = generateFileName(config.fileNamePattern, seed, resolution);

  // Deterministic file size estimate: ~2MB per 10s at 1080p, scaled by seed
  const seedNum = parseInt(seed.slice(0, 4), 16) || 1;
  const baseSizeBytes = Math.round(
    (videoAsset.totalDurationSeconds / 10) * 2_000_000 + (seedNum % 500_000),
  );

  const bundle: ExportBundlePayload = {
    fileName,
    format: 'mp4',
    fileSizeBytesEstimate: baseSizeBytes,
    durationSeconds: videoAsset.totalDurationSeconds,
    resolution,
    exportedAt: new Date().toISOString(),
    ...(config.includeMetadata
      ? {
          metadata: {
            workflowId: `wf-${seed.slice(0, 8)}`,
            workflowName: `Workflow ${seed.slice(0, 4)}`,
            exportVersion: '1.0.0',
            nodeCount: (videoAsset.timeline?.length ?? 0) + 1,
          },
        }
      : {}),
    ...(config.includeWorkflowSpecReference
      ? { workflowSpecRef: `spec://workflow/${seed.slice(0, 8)}/v1` }
      : {}),
  };

  return bundle;
}

// ============================================================
// Preview Text Builder
// ============================================================

function buildBundlePreviewText(bundle: ExportBundlePayload, config: FinalExportConfig): string {
  const parts: string[] = [
    bundle.fileName,
    `${bundle.durationSeconds}s`,
    bundle.resolution,
    `~${Math.round(bundle.fileSizeBytesEstimate / 1024)}KB`,
  ];

  if (config.includeMetadata) parts.push('meta');
  if (config.includeWorkflowSpecReference) parts.push('spec-ref');

  return parts.join(' · ');
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<FinalExportConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs: inputsRecord } = args;

  const videoAsset = extractVideoAsset(inputsRecord);
  if (!videoAsset) {
    return {
      exportBundle: {
        value: null,
        status: 'idle',
        schemaType: 'json',
        previewText: 'Waiting for video asset input...',
      } as PortPayload,
    };
  }

  const seed = stableHash(
    JSON.stringify({
      duration: videoAsset.totalDurationSeconds,
      aspectRatio: videoAsset.aspectRatio,
      fps: videoAsset.fps,
      config,
    }),
  );

  const bundle = buildExportBundle({ videoAsset, config, seed });
  const previewText = buildBundlePreviewText(bundle, config);

  return {
    exportBundle: {
      value: bundle,
      status: 'ready',
      schemaType: 'json',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(bundle).length * 2,
    } as PortPayload<ExportBundlePayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<FinalExportConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs: inputsRecord, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const videoAsset = extractVideoAsset(inputsRecord);
  if (!videoAsset) {
    return {
      exportBundle: {
        value: null,
        status: 'error',
        schemaType: 'json',
        errorMessage: 'Missing required videoAsset input',
      } as PortPayload,
    };
  }

  // Simulate packaging delay
  await new Promise(resolve => setTimeout(resolve, 80));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const seed = stableHash(
    JSON.stringify({
      duration: videoAsset.totalDurationSeconds,
      aspectRatio: videoAsset.aspectRatio,
      fps: videoAsset.fps,
      config,
    }),
  );

  const bundle = buildExportBundle({ videoAsset, config, seed });
  const previewText = buildBundlePreviewText(bundle, config);

  return {
    exportBundle: {
      value: bundle,
      status: 'success',
      schemaType: 'json',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(bundle).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<ExportBundlePayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleVideoAsset: PortPayload = {
  value: {
    timeline: [
      { index: 0, type: 'titleCard', durationSeconds: 3 },
      { index: 1, type: 'image', assetRef: 'asset-0-0', durationSeconds: 4, transition: 'fade' },
      { index: 2, type: 'transition', durationSeconds: 0.5, transition: 'fade' },
      { index: 3, type: 'image', assetRef: 'asset-1-1', durationSeconds: 4, transition: 'fade' },
      { index: 4, type: 'transition', durationSeconds: 0.5, transition: 'fade' },
      { index: 5, type: 'image', assetRef: 'asset-2-2', durationSeconds: 4, transition: 'fade' },
    ],
    totalDurationSeconds: 16,
    aspectRatio: '16:9',
    fps: 30,
    posterFrameUrl: 'placeholder://video/poster/abc12345.jpg',
    storyboardPreview: { frameCount: 4, frameDurationMs: 4000, transitionStyle: 'fade' },
    hasAudio: true,
    hasSubtitles: true,
    musicBed: 'none',
  },
  status: 'success',
  schemaType: 'videoAsset',
};

const sampleVideoAssetMinimal: PortPayload = {
  value: {
    timeline: [
      { index: 0, type: 'image', assetRef: 'asset-0-0', durationSeconds: 5 },
    ],
    totalDurationSeconds: 5,
    aspectRatio: '9:16',
    fps: 60,
    posterFrameUrl: 'placeholder://video/poster/min00001.jpg',
    storyboardPreview: { frameCount: 1, frameDurationMs: 5000, transitionStyle: 'cut' },
    hasAudio: false,
    hasSubtitles: false,
    musicBed: 'none',
  },
  status: 'success',
  schemaType: 'videoAsset',
};

const fixtures: readonly NodeFixture<FinalExportConfig>[] = [
  {
    id: 'default-export',
    label: 'Default Export with Metadata',
    config: {
      fileNamePattern: '{name}-{date}-{resolution}',
      includeMetadata: true,
      includeWorkflowSpecReference: false,
    },
    previewInputs: { videoAsset: sampleVideoAsset },
  },
  {
    id: 'full-export-with-spec-ref',
    label: 'Full Export with Spec Reference',
    config: {
      fileNamePattern: '{name}-{date}-{resolution}',
      includeMetadata: true,
      includeWorkflowSpecReference: true,
    },
    previewInputs: { videoAsset: sampleVideoAsset },
  },
  {
    id: 'minimal-vertical-export',
    label: 'Minimal Vertical Export',
    config: {
      fileNamePattern: '{name}-{resolution}',
      includeMetadata: false,
      includeWorkflowSpecReference: false,
    },
    previewInputs: { videoAsset: sampleVideoAssetMinimal },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * finalExport Node Template
 *
 * Executable: packages the composed video asset into an export descriptor
 * with file info, optional metadata, and optional workflow spec reference.
 */
export const finalExportTemplate: NodeTemplate<FinalExportConfig> = {
  type: 'finalExport',
  templateVersion: '1.0.0',
  title: 'Final Export',
  category: 'output',
  description:
    'Packages the composed video asset into an exportable JSON descriptor with synthetic file info, optional workflow metadata, and optional workflow specification reference. Produces deterministic output from videoAsset + config.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: FinalExportConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
