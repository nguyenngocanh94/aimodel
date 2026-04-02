import { describe, it, expect } from 'vitest';
import {
  ttsVoiceoverPlannerTemplate,
  TtsVoiceoverPlannerConfigSchema,
  type AudioPlanPayload,
} from './tts-voiceover-planner';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('ttsVoiceoverPlanner Node Template - AiModel-9wx.10', () => {
  const sampleScript: PortPayload = {
    value: {
      title: 'Ocean Currents Explained',
      hook: 'Have you ever wondered what drives ocean currents?',
      beats: [
        { timestamp: '0s', narration: 'Ocean currents are like rivers flowing through the sea.', durationSeconds: 15 },
        { timestamp: '15s', narration: 'They transport heat around the planet, affecting weather patterns.', durationSeconds: 20 },
        { timestamp: '35s', narration: 'The thermohaline circulation is a global conveyor belt of water.', durationSeconds: 25 },
      ],
      cta: 'Learn more about our amazing oceans today.',
      totalDurationSeconds: 100,
      style: 'educational',
      structure: 'problem-solution',
    },
    status: 'success',
    schemaType: 'script',
  };

  it('should match plan metadata and ports', () => {
    expect(ttsVoiceoverPlannerTemplate.type).toBe('ttsVoiceoverPlanner');
    expect(ttsVoiceoverPlannerTemplate.category).toBe('audio');
    expect(ttsVoiceoverPlannerTemplate.executable).toBe(true);
    expect(ttsVoiceoverPlannerTemplate.inputs).toHaveLength(1);
    expect(ttsVoiceoverPlannerTemplate.inputs[0].key).toBe('script');
    expect(ttsVoiceoverPlannerTemplate.inputs[0].dataType).toBe('script');
    expect(ttsVoiceoverPlannerTemplate.outputs).toHaveLength(1);
    expect(ttsVoiceoverPlannerTemplate.outputs[0].key).toBe('audioPlan');
    expect(ttsVoiceoverPlannerTemplate.outputs[0].dataType).toBe('audioPlan');
  });

  describe('Config Schema', () => {
    it('should validate valid configuration', () => {
      const validConfig = {
        voiceStyle: 'warm',
        pace: 'normal',
        genderStyle: 'neutral',
        includePauses: true,
      };
      const result = TtsVoiceoverPlannerConfigSchema.safeParse(validConfig);
      expect(result.success).toBe(true);
    });

    it('should accept any string for voiceStyle', () => {
      const result = TtsVoiceoverPlannerConfigSchema.safeParse({
        voiceStyle: 'robotic',
        pace: 'normal',
        genderStyle: 'neutral',
        includePauses: true,
      });
      expect(result.success).toBe(true);
    });

    it('should accept all pace variants', () => {
      ['slow', 'normal', 'fast'].forEach(pace => {
        const result = TtsVoiceoverPlannerConfigSchema.safeParse({
          voiceStyle: 'warm',
          pace,
          genderStyle: 'neutral',
          includePauses: true,
        });
        expect(result.success).toBe(true);
      });
    });

    it('should accept all genderStyle variants', () => {
      ['masculine', 'feminine', 'neutral'].forEach(gender => {
        const result = TtsVoiceoverPlannerConfigSchema.safeParse({
          voiceStyle: 'warm',
          pace: 'normal',
          genderStyle: gender,
          includePauses: true,
        });
        expect(result.success).toBe(true);
      });
    });

    it('should reject invalid pace', () => {
      const result = TtsVoiceoverPlannerConfigSchema.safeParse({
        voiceStyle: 'warm',
        pace: 'invalid',
        genderStyle: 'neutral',
        includePauses: true,
      });
      expect(result.success).toBe(false);
    });

    it('should reject invalid genderStyle', () => {
      const result = TtsVoiceoverPlannerConfigSchema.safeParse({
        voiceStyle: 'warm',
        pace: 'normal',
        genderStyle: 'unknown',
        includePauses: true,
      });
      expect(result.success).toBe(false);
    });
  });

  describe('buildPreview', () => {
    it('should return idle when script is missing', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: {},
      });
      expect(result.audioPlan.status).toBe('idle');
      expect(result.audioPlan.value).toBeNull();
    });

    it('should produce audioPlan with valid script', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
      });
      expect(result.audioPlan.status).toBe('ready');
      expect(result.audioPlan.schemaType).toBe('audioPlan');
      expect(result.audioPlan.value).not.toBeNull();
    });

    it('should extract segments from script structure', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
      });
      const audioPlan = result.audioPlan.value as AudioPlanPayload;
      // Hook + 3 beats + CTA = 5 segments
      expect(audioPlan.segments.length).toBe(5);
    });

    it('should include correct segment structure', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
      });
      const segments = (result.audioPlan.value as AudioPlanPayload).segments;
      
      segments.forEach((segment, index) => {
        expect(segment.index).toBe(index);
        expect(segment.text).toBeDefined();
        expect(segment.text.length).toBeGreaterThan(0);
        expect(segment.startSeconds).toBeGreaterThanOrEqual(0);
        expect(segment.durationSeconds).toBeGreaterThan(0);
        expect(segment.voiceStyle).toBe(ttsVoiceoverPlannerTemplate.defaultConfig.voiceStyle);
      });
    });

    it('should have sequential timing', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
      });
      const segments = (result.audioPlan.value as AudioPlanPayload).segments;
      
      // First segment starts at 0
      expect(segments[0].startSeconds).toBe(0);
      
      // Each subsequent segment starts after the previous
      for (let i = 1; i < segments.length; i++) {
        const prevEnd = segments[i - 1].startSeconds + segments[i - 1].durationSeconds;
        expect(segments[i].startSeconds).toBeGreaterThanOrEqual(prevEnd);
      }
    });

    it('should include metadata fields', () => {
      const result = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, voiceStyle: 'authoritative', pace: 'slow' },
        inputs: { script: sampleScript },
      });
      const audioPlan = result.audioPlan.value as AudioPlanPayload;
      
      expect(audioPlan.voiceStyle).toBe('authoritative');
      expect(audioPlan.pace).toBe('slow');
      expect(audioPlan.genderStyle).toBe('neutral');
      expect(audioPlan.totalDurationSeconds).toBeGreaterThan(0);
      expect(audioPlan.placeholderAudioUrl).toContain('placeholder://audio/');
    });

    it('should respect includePauses setting', () => {
      const withPauses = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, includePauses: true },
        inputs: { script: sampleScript },
      });
      const withoutPauses = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, includePauses: false },
        inputs: { script: sampleScript },
      });

      const durationWith = (withPauses.audioPlan.value as AudioPlanPayload).totalDurationSeconds;
      const durationWithout = (withoutPauses.audioPlan.value as AudioPlanPayload).totalDurationSeconds;
      
      // With pauses should take longer
      expect(durationWith).toBeGreaterThan(durationWithout);
    });

    it('should calculate different durations for different paces', () => {
      const slow = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, pace: 'slow' },
        inputs: { script: sampleScript },
      });
      const normal = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, pace: 'normal' },
        inputs: { script: sampleScript },
      });
      const fast = ttsVoiceoverPlannerTemplate.buildPreview({
        config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, pace: 'fast' },
        inputs: { script: sampleScript },
      });

      const slowDuration = (slow.audioPlan.value as AudioPlanPayload).totalDurationSeconds;
      const normalDuration = (normal.audioPlan.value as AudioPlanPayload).totalDurationSeconds;
      const fastDuration = (fast.audioPlan.value as AudioPlanPayload).totalDurationSeconds;

      expect(slowDuration).toBeGreaterThan(normalDuration);
      expect(normalDuration).toBeGreaterThan(fastDuration);
    });
  });

  describe('mockExecute', () => {
    it('should return success status with valid script', async () => {
      const result = await ttsVoiceoverPlannerTemplate.mockExecute!({
        nodeId: 'n1',
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.audioPlan.status).toBe('success');
    });

    it('should return error when script is missing', async () => {
      const result = await ttsVoiceoverPlannerTemplate.mockExecute!({
        nodeId: 'n1',
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: {},
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      expect(result.audioPlan.status).toBe('error');
      expect(result.audioPlan.errorMessage).toBeDefined();
    });

    it('should execute quickly without delay', async () => {
      const start = Date.now();
      const result = await ttsVoiceoverPlannerTemplate.mockExecute!({
        nodeId: 'n1',
        config: ttsVoiceoverPlannerTemplate.defaultConfig,
        inputs: { script: sampleScript },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      const duration = Date.now() - start;
      expect(result.audioPlan.status).toBe('success');
      expect(duration).toBeLessThan(100);
    });

    it('should produce deterministic output', async () => {
      const config = ttsVoiceoverPlannerTemplate.defaultConfig;
      
      const result1 = await ttsVoiceoverPlannerTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs: { script: sampleScript },
        signal: new AbortController().signal,
        runId: 'run-a',
      });
      
      const result2 = await ttsVoiceoverPlannerTemplate.mockExecute!({
        nodeId: 'n1',
        config,
        inputs: { script: sampleScript },
        signal: new AbortController().signal,
        runId: 'run-b',
      });

      const plan1 = result1.audioPlan.value as AudioPlanPayload;
      const plan2 = result2.audioPlan.value as AudioPlanPayload;
      
      expect(plan1.segments.length).toBe(plan2.segments.length);
      expect(plan1.totalDurationSeconds).toBe(plan2.totalDurationSeconds);
      expect(plan1.placeholderAudioUrl).toBe(plan2.placeholderAudioUrl);
    });
  });

  describe('Fixtures', () => {
    it('should have at least two fixtures', () => {
      expect(ttsVoiceoverPlannerTemplate.fixtures.length).toBeGreaterThanOrEqual(2);
    });

    it('should have unique fixture IDs', () => {
      const ids = ttsVoiceoverPlannerTemplate.fixtures.map(f => f.id);
      expect(new Set(ids).size).toBe(ids.length);
    });

    it('should have valid fixture configurations', () => {
      ttsVoiceoverPlannerTemplate.fixtures.forEach(f => {
        const merged = { ...ttsVoiceoverPlannerTemplate.defaultConfig, ...f.config };
        expect(() => TtsVoiceoverPlannerConfigSchema.parse(merged)).not.toThrow();
      });
    });

    it('fixtures should produce valid audioPlans', () => {
      ttsVoiceoverPlannerTemplate.fixtures.forEach(f => {
        const result = ttsVoiceoverPlannerTemplate.buildPreview({
          config: { ...ttsVoiceoverPlannerTemplate.defaultConfig, ...f.config },
          inputs: f.previewInputs || {},
        });
        expect(result.audioPlan.status).toBe('ready');
        expect(result.audioPlan.value).not.toBeNull();
        const v = result.audioPlan.value as AudioPlanPayload;
        expect(v.segments.length).toBeGreaterThan(0);
      });
    });
  });
});
