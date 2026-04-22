/**
 * humanGate Node Template Tests (NM3 trim: configSchema/defaultConfig assertions removed)
 *
 * Config validation is now backend-authoritative (NM1 + manifest).
 * Tests cover registry shape, buildPreview, mockExecute, and fixtures.
 */

import { describe, it, expect } from 'vitest';
import {
  humanGateTemplate,
  type HumanGateConfig,
} from './human-gate';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

// Minimal valid config (replaces removed defaultConfig)
const baseConfig: HumanGateConfig = {
  messageTemplate: '',
  channel: 'ui',
  timeoutSeconds: 0,
  autoFallbackResponse: null,
  options: null,
};

describe('humanGate Node Template', () => {
  const sampleData: PortPayload = {
    value: {
      question: 'Which option?',
      items: ['A', 'B', 'C'],
    },
    status: 'success',
    schemaType: 'json',
  };

  it('should have correct metadata', () => {
    expect(humanGateTemplate.type).toBe('humanGate');
    expect(humanGateTemplate.templateVersion).toBe('1.0.0');
    expect(humanGateTemplate.title).toBe('Human Gate');
    expect(humanGateTemplate.category).toBe('utility');
    expect(humanGateTemplate.executable).toBe(true);
    expect(humanGateTemplate.description).toContain('Pauses workflow execution');
  });

  it('should define data input and response output ports', () => {
    expect(humanGateTemplate.inputs).toHaveLength(1);
    expect(humanGateTemplate.inputs[0].key).toBe('data');
    expect(humanGateTemplate.inputs[0].dataType).toBe('json');
    expect(humanGateTemplate.inputs[0].required).toBe(true);

    expect(humanGateTemplate.outputs).toHaveLength(1);
    expect(humanGateTemplate.outputs[0].key).toBe('response');
    expect(humanGateTemplate.outputs[0].dataType).toBe('json');
  });

  it('should have no configSchema (NM3 pilot stripped, manifest-driven)', () => {
    // configSchema is intentionally absent — backend manifest is the source of truth
    expect(humanGateTemplate.configSchema).toBeUndefined();
    // defaultConfig is an empty sentinel ({}); real defaults come from manifest
    expect(Object.keys(humanGateTemplate.defaultConfig as object).length).toBe(0);
  });

  describe('buildPreview', () => {
    it('should return idle response when no input data', () => {
      const out = humanGateTemplate.buildPreview({
        config: baseConfig,
        inputs: {},
      });
      expect(out.response.status).toBe('idle');
      expect(out.response.previewText).toContain('Waiting for input data');
    });

    it('should return idle with gate paused message when data provided', () => {
      const out = humanGateTemplate.buildPreview({
        config: {
          ...baseConfig,
          messageTemplate: 'Review: {{question}}',
        },
        inputs: { data: sampleData },
      });
      expect(out.response.status).toBe('idle');
      expect(out.response.previewText).toContain('Gate paused');
      expect(out.response.previewText).toContain('Review: Which option?');
    });

    it('should show channel in preview when no message template', () => {
      const out = humanGateTemplate.buildPreview({
        config: {
          ...baseConfig,
          channel: 'telegram',
        },
        inputs: { data: sampleData },
      });
      expect(out.response.previewText).toContain('telegram');
    });
  });

  describe('mockExecute', () => {
    it('should return success response with mock data', async () => {
      const out = await humanGateTemplate.mockExecute!({
        nodeId: 'n1',
        config: baseConfig,
        inputs: { data: sampleData },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.response.status).toBe('success');
      expect(out.response.value).toBeDefined();
      const val = out.response.value as { approved: boolean; source: string };
      expect(val.approved).toBe(true);
      expect(val.source).toBe('mock');
    });

    it('should use first option when options are configured', async () => {
      const out = await humanGateTemplate.mockExecute!({
        nodeId: 'n1',
        config: {
          ...baseConfig,
          options: ['X', 'Y', 'Z'],
        },
        inputs: { data: sampleData },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.response.status).toBe('success');
      const val = out.response.value as { choice: string };
      expect(val.choice).toBe('X');
    });

    it('should use autoFallbackResponse when configured and no options', async () => {
      const out = await humanGateTemplate.mockExecute!({
        nodeId: 'n1',
        config: {
          ...baseConfig,
          autoFallbackResponse: '{"selected":"B"}',
        },
        inputs: { data: sampleData },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.response.status).toBe('success');
      const val = out.response.value as { selected: string };
      expect(val.selected).toBe('B');
    });

    it('should error when required data input missing', async () => {
      const out = await humanGateTemplate.mockExecute!({
        nodeId: 'n1',
        config: baseConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(out.response.status).toBe('error');
      expect(out.response.errorMessage).toContain('Missing required data input');
    });

    it('should respect abort signal', async () => {
      const controller = new AbortController();
      const promise = humanGateTemplate.mockExecute!({
        nodeId: 'n1',
        config: baseConfig,
        inputs: { data: sampleData },
        signal: controller.signal,
        runId: 'run-a',
      });
      controller.abort();
      await expect(promise).rejects.toThrow('cancelled');
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(humanGateTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('fixtures should produce valid preview outputs', () => {
      humanGateTemplate.fixtures.forEach((f) => {
        const merged = { ...baseConfig, ...f.config };
        const result = humanGateTemplate.buildPreview({
          config: merged,
          inputs: f.previewInputs || {},
        });
        expect(result.response).toBeDefined();
        expect(result.response.status).toBe('idle');
      });
    });
  });
});
