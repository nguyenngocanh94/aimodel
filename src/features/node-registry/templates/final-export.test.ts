import { describe, it, expect } from 'vitest';
import {
  finalExportTemplate,
  FinalExportConfigSchema,
  type ExportBundlePayload,
} from './final-export';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';
import { isExecutableNode } from '../node-registry';

describe('finalExport Node Template - AiModel-9wx.14', () => {
  /** Matches VideoAssetValue + composer-style videoAsset payload */
  const sampleVideoAsset: PortPayload = {
    value: {
      timeline: [
        { index: 0, type: 'titleCard', durationSeconds: 3 },
        { index: 1, type: 'image', assetRef: 'a', durationSeconds: 4 },
        { index: 2, type: 'image', assetRef: 'b', durationSeconds: 4 },
      ],
      totalDurationSeconds: 60,
      aspectRatio: '16:9',
      fps: 30,
      posterFrameUrl: 'placeholder://video/poster.png',
      storyboardPreview: { frameCount: 3, frameDurationMs: 4000, transitionStyle: 'fade' },
      hasAudio: false,
      hasSubtitles: false,
    },
    status: 'success',
    schemaType: 'videoAsset',
  };

  it('should match plan metadata and ports', () => {
    expect(finalExportTemplate.type).toBe('finalExport');
    expect(finalExportTemplate.category).toBe('output');
    expect(finalExportTemplate.executable).toBe(true);
    expect(finalExportTemplate.inputs).toHaveLength(1);
    expect(finalExportTemplate.inputs[0].key).toBe('videoAsset');
    expect(finalExportTemplate.inputs[0].dataType).toBe('videoAsset');
    expect(finalExportTemplate.outputs).toHaveLength(1);
    expect(finalExportTemplate.outputs[0].key).toBe('exportBundle');
    expect(finalExportTemplate.outputs[0].dataType).toBe('json');
  });

  describe('Config Schema', () => {
    it('should validate valid configuration', () => {
      const validConfig = {
        fileNamePattern: 'video-{name}-{date}',
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      };
      const result = FinalExportConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should enforce fileNamePattern length constraints', () => {
      const tooLong = FinalExportConfigSchema.safeParse({
        fileNamePattern: 'a'.repeat(201),
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      });
      expect(tooLong.success).toBe(false);

      const empty = FinalExportConfigSchema.safeParse({
        fileNamePattern: '',
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      });
      expect(empty.success).toBe(false);

      const valid = FinalExportConfigSchema.safeParse({
        fileNamePattern: 'valid-pattern',
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      });
      expect(valid.success).toBe(true);
    });

    it('should accept boolean values for metadata options', () => {
      const withMeta = FinalExportConfigSchema.safeParse({
        fileNamePattern: 'video',
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      });
      expect(withMeta.success).toBe(true);

      const withoutMeta = FinalExportConfigSchema.safeParse({
        fileNamePattern: 'video',
        includeMetadata: false,
        includeWorkflowSpecReference: false,
      });
      expect(withoutMeta.success).toBe(true);
    });
  });

  describe('buildPreview', () => {
    it('should return idle when videoAsset is missing', () => {
      const result = finalExportTemplate.buildPreview({
        config: finalExportTemplate.defaultConfig,
        inputs: {},
      });
      expect(result.exportBundle.status).toBe('idle');
      expect(result.exportBundle.value).toBeNull();
    });

    it('should produce exportBundle with valid videoAsset', () => {
      const result = finalExportTemplate.buildPreview({
        config: finalExportTemplate.defaultConfig,
        inputs: { videoAsset: sampleVideoAsset },
      });
      expect(result.exportBundle.status).toBe('ready');
      expect(result.exportBundle.schemaType).toBe('json');
      expect(result.exportBundle.value).not.toBeNull();
    });

    it('should include correct export structure', () => {
      const result = finalExportTemplate.buildPreview({
        config: finalExportTemplate.defaultConfig,
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.fileName).toContain('.mp4');
      expect(bundle.format).toBe('mp4');
      expect(bundle.fileSizeBytesEstimate).toBeGreaterThan(0);
      expect(bundle.durationSeconds).toBe(60);
      expect(bundle.resolution).toBe('1920x1080');
      expect(bundle.exportedAt).toBeDefined();
    });

    it('should include metadata when enabled', () => {
      const result = finalExportTemplate.buildPreview({
        config: { ...finalExportTemplate.defaultConfig, includeMetadata: true },
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.metadata).toBeDefined();
      expect(bundle.metadata?.exportVersion).toBe('1.0.0');
      // nodeCount = timeline.length + 1
      expect(bundle.metadata?.nodeCount).toBe(4);
    });

    it('should omit metadata when disabled', () => {
      const result = finalExportTemplate.buildPreview({
        config: { ...finalExportTemplate.defaultConfig, includeMetadata: false },
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.metadata).toBeUndefined();
    });

    it('should include workflow spec reference when enabled', () => {
      const result = finalExportTemplate.buildPreview({
        config: { ...finalExportTemplate.defaultConfig, includeWorkflowSpecReference: true },
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.workflowSpecRef).toBeDefined();
      expect(bundle.workflowSpecRef).toContain('spec://workflow/');
    });

    it('should omit workflow spec reference when disabled', () => {
      const result = finalExportTemplate.buildPreview({
        config: { ...finalExportTemplate.defaultConfig, includeWorkflowSpecReference: false },
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.workflowSpecRef).toBeUndefined();
    });

    it('should generate filename from pattern placeholders', () => {
      const result = finalExportTemplate.buildPreview({
        config: {
          ...finalExportTemplate.defaultConfig,
          fileNamePattern: 'my-export-{name}-{date}-{resolution}',
        },
        inputs: { videoAsset: sampleVideoAsset },
      });
      const bundle = result.exportBundle.value as ExportBundlePayload;

      expect(bundle.fileName).toContain('my-export-');
      expect(bundle.fileName).toContain('.mp4');
      expect(bundle.fileName).toContain('1920x1080');
    });
  });

  describe('mockExecute', () => {
    const runMock = isExecutableNode(finalExportTemplate)
      ? finalExportTemplate.mockExecute.bind(finalExportTemplate)
      : null;

    it('should return success status with valid videoAsset', async () => {
      expect(runMock).not.toBeNull();
      const result = await runMock!({
        nodeId: 'n1',
        config: finalExportTemplate.defaultConfig,
        inputs: { videoAsset: sampleVideoAsset },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.exportBundle.status).toBe('success');
    });

    it('should return error when videoAsset is missing', async () => {
      const result = await runMock!({
        nodeId: 'n1',
        config: finalExportTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.exportBundle.status).toBe('error');
      expect(result.exportBundle.errorMessage).toBeDefined();
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = runMock!({
        nodeId: 'n1',
        config: finalExportTemplate.defaultConfig,
        inputs: { videoAsset: sampleVideoAsset },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });

    it('should produce deterministic output for stable inputs', async () => {
      const config = finalExportTemplate.defaultConfig;

      const result1 = await runMock!({
        nodeId: 'n1',
        config,
        inputs: { videoAsset: sampleVideoAsset },
        signal: new AbortController().signal,
        runId: 'run-a',
      });

      const result2 = await runMock!({
        nodeId: 'n1',
        config,
        inputs: { videoAsset: sampleVideoAsset },
        signal: new AbortController().signal,
        runId: 'run-b',
      });

      const bundle1 = result1.exportBundle.value as ExportBundlePayload;
      const bundle2 = result2.exportBundle.value as ExportBundlePayload;

      expect(bundle1.durationSeconds).toBe(bundle2.durationSeconds);
      expect(bundle1.resolution).toBe(bundle2.resolution);
      expect(bundle1.format).toBe(bundle2.format);
      expect(bundle1.fileName).toBe(bundle2.fileName);
    });

    it('should include producedAt timestamp', async () => {
      const result = await runMock!({
        nodeId: 'n1',
        config: finalExportTemplate.defaultConfig,
        inputs: { videoAsset: sampleVideoAsset },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.exportBundle.producedAt).toBeDefined();
      expect(new Date(result.exportBundle.producedAt!).toISOString()).toBe(result.exportBundle.producedAt);
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(finalExportTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have unique fixture IDs', () => {
      const ids = finalExportTemplate.fixtures.map((f) => f.id);
      expect(new Set(ids).size).toBe(ids.length);
    });

    it('should have valid fixture configurations', () => {
      finalExportTemplate.fixtures.forEach((f) => {
        const merged = { ...finalExportTemplate.defaultConfig, ...f.config };
        expect(() => FinalExportConfigSchema.parse(merged)).not.toThrow();
      });
    });

    it('fixtures should produce valid exportBundles', () => {
      finalExportTemplate.fixtures.forEach((f) => {
        const result = finalExportTemplate.buildPreview({
          config: { ...finalExportTemplate.defaultConfig, ...f.config },
          inputs: f.previewInputs || {},
        });
        expect(result.exportBundle.status).toBe('ready');
        expect(result.exportBundle.value).not.toBeNull();
        const v = result.exportBundle.value as ExportBundlePayload;
        expect(v.fileName).toContain('.mp4');
      });
    });
  });
});
