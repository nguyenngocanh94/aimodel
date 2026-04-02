import { describe, it, expect } from 'vitest';
import {
  checkCompatibility,
  getCompatibleTargets,
  isListType,
  getScalarType,
  getListType,
} from './type-compatibility';
import type { DataType } from './workflow-types';

describe('Type Compatibility Registry - AiModel-9wx.3', () => {
  describe('Compatibility Classes', () => {
    it('should allow exact type matches', () => {
      const exactMatches: Array<[DataType, DataType]> = [
        ['text', 'text'],
        ['prompt', 'prompt'],
        ['script', 'script'],
        ['scene', 'scene'],
        ['imageFrame', 'imageFrame'],
        ['imageAsset', 'imageAsset'],
        ['audioPlan', 'audioPlan'],
        ['audioAsset', 'audioAsset'],
        ['subtitleAsset', 'subtitleAsset'],
        ['videoAsset', 'videoAsset'],
        ['reviewDecision', 'reviewDecision'],
        ['json', 'json'],
        ['textList', 'textList'],
        ['promptList', 'promptList'],
        ['sceneList', 'sceneList'],
        ['imageFrameList', 'imageFrameList'],
        ['imageAssetList', 'imageAssetList'],
      ];

      exactMatches.forEach(([source, target]) => {
        const result = checkCompatibility(source, target);
        expect(result.compatible).toBe(true);
        expect(result.coercionApplied).toBe(false);
        expect(result.severity).toBe('none');
        expect(result.reason).toContain('Exact type match');
      });
    });

    it('should allow safe scalar-to-list wrapping with warning', () => {
      const safeWrappings: Array<[DataType, DataType]> = [
        ['text', 'textList'],
        ['prompt', 'promptList'],
        ['scene', 'sceneList'],
        ['imageFrame', 'imageFrameList'],
        ['imageAsset', 'imageAssetList'],
      ];

      safeWrappings.forEach(([source, target]) => {
        const result = checkCompatibility(source, target);
        expect(result.compatible).toBe(true);
        expect(result.coercionApplied).toBe(true);
        expect(result.severity).toBe('warning');
        expect(result.reason).toContain('wrap');
      });
    });

    it('should reject list-to-scalar coercion (no destructive coercions)', () => {
      const destructiveCoercions: Array<[DataType, DataType]> = [
        ['textList', 'text'],
        ['promptList', 'prompt'],
        ['sceneList', 'scene'],
        ['imageFrameList', 'imageFrame'],
        ['imageAssetList', 'imageAsset'],
      ];

      destructiveCoercions.forEach(([source, target]) => {
        const result = checkCompatibility(source, target);
        expect(result.compatible).toBe(false);
        expect(result.coercionApplied).toBe(false);
        expect(result.severity).toBe('error');
        expect(result.reason?.toLowerCase()).toMatch(/cannot be|destructive|list cannot/);
      });
    });

    it('should suggest adapter nodes for incompatible media types', () => {
      const adapterCases: Array<[DataType, DataType, string]> = [
        ['script', 'subtitleAsset', 'subtitleFormatter'],
        ['script', 'sceneList', 'sceneSplitter'],
        ['imageFrame', 'imageAsset', 'imageAssetMapper'],
        ['imageFrameList', 'imageAssetList', 'imageAssetMapper'],
        ['imageAssetList', 'videoAsset', 'videoComposer'],
        ['audioAsset', 'videoAsset', 'videoComposer'],
      ];

      adapterCases.forEach(([source, target, expectedAdapter]) => {
        const result = checkCompatibility(source, target);
        expect(result.suggestedAdapterNodeType).toBe(expectedAdapter);
        // Note: script -> sceneList is compatible with warning via sceneSplitter
        if (source === 'script' && target === 'sceneList') {
          expect(result.compatible).toBe(true);
          expect(result.severity).toBe('warning');
        } else {
          expect(result.compatible).toBe(false);
          expect(result.severity).toBe('error');
        }
      });
    });

    it('should reject incompatible media type conversions', () => {
      const incompatibleConversions: Array<[DataType, DataType]> = [
        ['text', 'imageFrame'],
        ['prompt', 'videoAsset'],
        ['imageAsset', 'audioAsset'],
        ['audioAsset', 'imageAsset'],
        ['videoAsset', 'script'],
      ];

      incompatibleConversions.forEach(([source, target]) => {
        const result = checkCompatibility(source, target);
        expect(result.compatible).toBe(false);
        expect(result.severity).toBe('error');
      });
    });

    it('should allow safe JSON wrapping with warning', () => {
      const jsonSafe: DataType[] = [
        'text', 'prompt', 'script', 'scene', 'imageFrame', 'imageAsset',
        'audioPlan', 'audioAsset', 'subtitleAsset', 'videoAsset',
        'reviewDecision', 'textList', 'promptList', 'sceneList',
        'imageFrameList', 'imageAssetList'
      ];

      jsonSafe.forEach((source) => {
        const result = checkCompatibility(source, 'json');
        expect(result.compatible).toBe(true);
        expect(result.coercionApplied).toBe(true);
        expect(result.severity).toBe('warning');
      });
    });

    it('should allow JSON to text with warning (stringification)', () => {
      const result = checkCompatibility('json', 'text');
      expect(result.compatible).toBe(true);
      expect(result.coercionApplied).toBe(true);
      expect(result.severity).toBe('warning');
      expect(result.reason).toContain('stringified');
    });
  });

  describe('Specific Matrix Cases from Plan 9.3', () => {
    it('should match plan section 9.3 examples', () => {
      // prompt -> prompt: yes
      expect(checkCompatibility('prompt', 'prompt').compatible).toBe(true);

      // prompt -> promptList: yes, auto-wrap
      const promptToList = checkCompatibility('prompt', 'promptList');
      expect(promptToList.compatible).toBe(true);
      expect(promptToList.coercionApplied).toBe(true);

      // promptList -> prompt: no
      expect(checkCompatibility('promptList', 'prompt').compatible).toBe(false);

      // sceneList -> promptList: no, use promptRefiner
      const sceneListToPromptList = checkCompatibility('sceneList', 'promptList');
      expect(sceneListToPromptList.compatible).toBe(false);
      expect(sceneListToPromptList.suggestedAdapterNodeType).toBe('promptRefiner');

      // imageFrameList -> imageAssetList: no, use imageAssetMapper
      const frameListToAssetList = checkCompatibility('imageFrameList', 'imageAssetList');
      expect(frameListToAssetList.compatible).toBe(false);
      expect(frameListToAssetList.suggestedAdapterNodeType).toBe('imageAssetMapper');

      // script -> subtitleAsset: no, use subtitleFormatter
      const scriptToSubtitle = checkCompatibility('script', 'subtitleAsset');
      expect(scriptToSubtitle.compatible).toBe(false);
      expect(scriptToSubtitle.suggestedAdapterNodeType).toBe('subtitleFormatter');
    });
  });

  describe('getCompatibleTargets', () => {
    it('should return all compatible targets for a source type', () => {
      const targets = getCompatibleTargets('text');
      
      // Should include exact match and safe coercions
      const targetTypes = targets.map(t => t.target);
      expect(targetTypes).toContain('text');
      expect(targetTypes).toContain('textList');
      expect(targetTypes).toContain('json');
      
      // Should not include incompatible types
      expect(targetTypes).not.toContain('imageFrame');
      expect(targetTypes).not.toContain('videoAsset');
    });

    it('should return empty array for unknown source type', () => {
      // @ts-expect-error Testing invalid input
      const targets = getCompatibleTargets('unknownType');
      expect(targets).toHaveLength(0);
    });
  });

  describe('List Type Utilities', () => {
    it('should identify list types correctly', () => {
      expect(isListType('textList')).toBe(true);
      expect(isListType('promptList')).toBe(true);
      expect(isListType('sceneList')).toBe(true);
      expect(isListType('imageFrameList')).toBe(true);
      expect(isListType('imageAssetList')).toBe(true);
      
      expect(isListType('text')).toBe(false);
      expect(isListType('prompt')).toBe(false);
      expect(isListType('script')).toBe(false);
      expect(isListType('videoAsset')).toBe(false);
      expect(isListType('json')).toBe(false);
    });

    it('should get scalar type from list type', () => {
      expect(getScalarType('textList')).toBe('text');
      expect(getScalarType('promptList')).toBe('prompt');
      expect(getScalarType('sceneList')).toBe('scene');
      expect(getScalarType('imageFrameList')).toBe('imageFrame');
      expect(getScalarType('imageAssetList')).toBe('imageAsset');
      
      expect(getScalarType('text')).toBeUndefined();
      expect(getScalarType('script')).toBeUndefined();
      expect(getScalarType('videoAsset')).toBeUndefined();
    });

    it('should get list type from scalar type', () => {
      expect(getListType('text')).toBe('textList');
      expect(getListType('prompt')).toBe('promptList');
      expect(getListType('scene')).toBe('sceneList');
      expect(getListType('imageFrame')).toBe('imageFrameList');
      expect(getListType('imageAsset')).toBe('imageAssetList');
      
      expect(getListType('textList')).toBeUndefined();
      expect(getListType('script')).toBeUndefined(); // No scriptList
      expect(getListType('audioPlan')).toBeUndefined(); // No audioPlanList
    });
  });

  describe('Error Handling', () => {
    it('should handle unknown type combinations gracefully', () => {
      // This shouldn't happen with proper types, but test defensively
      // @ts-expect-error Testing invalid input
      const result = checkCompatibility('invalidType', 'text');
      expect(result.compatible).toBe(false);
      expect(result.severity).toBe('error');
      expect(result.reason).toContain('Unknown');
    });
  });

  describe('Full Matrix Coverage', () => {
    const allTypes: DataType[] = [
      'text', 'textList', 'prompt', 'promptList', 'script', 'scene', 'sceneList',
      'imageFrame', 'imageFrameList', 'imageAsset', 'imageAssetList',
      'audioPlan', 'audioAsset', 'subtitleAsset', 'videoAsset',
      'reviewDecision', 'json'
    ];

    it('should have compatibility entry for all type pairs', () => {
      allTypes.forEach(source => {
        allTypes.forEach(target => {
          const result = checkCompatibility(source, target);
          
          // Every combination should return a valid result
          expect(result).toHaveProperty('compatible');
          expect(result).toHaveProperty('coercionApplied');
          expect(result).toHaveProperty('severity');
          expect(result).toHaveProperty('reason');
          
          // Severity should match compatibility
          if (result.compatible) {
            expect(['none', 'warning']).toContain(result.severity);
          } else {
            expect(result.severity).toBe('error');
          }
        });
      });
    });
  });
});
