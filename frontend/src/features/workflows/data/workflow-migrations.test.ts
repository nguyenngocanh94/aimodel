import { describe, it, expect } from 'vitest';
import {
  migrateDocument,
  migrateNodeConfig,
  isVersionSupported,
  needsMigration,
  CURRENT_SCHEMA_VERSION,
} from './workflow-migrations';
import type { WorkflowDocument, WorkflowNode } from '@/features/workflows/domain/workflow-types';

function makeDocument(schemaVersion = 1): WorkflowDocument {
  return {
    id: 'wf-1',
    schemaVersion,
    name: 'Test',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };
}

function makeNode(type = 'scriptWriter'): WorkflowNode {
  return {
    id: 'node-1',
    type,
    label: 'Test Node',
    position: { x: 0, y: 0 },
    config: {},
  };
}

describe('workflow-migrations', () => {
  describe('CURRENT_SCHEMA_VERSION', () => {
    it('should be 1 for v1', () => {
      expect(CURRENT_SCHEMA_VERSION).toBe(1);
    });
  });

  describe('isVersionSupported', () => {
    it('should support version 1', () => {
      expect(isVersionSupported(1)).toBe(true);
    });

    it('should not support version 0', () => {
      expect(isVersionSupported(0)).toBe(false);
    });

    it('should not support future versions', () => {
      expect(isVersionSupported(CURRENT_SCHEMA_VERSION + 1)).toBe(false);
    });
  });

  describe('needsMigration', () => {
    it('should not need migration for current version', () => {
      expect(needsMigration(CURRENT_SCHEMA_VERSION)).toBe(false);
    });

    it('should need migration for older versions when they exist', () => {
      // Currently v1 is latest, so nothing needs migration
      expect(needsMigration(1)).toBe(false);
    });
  });

  describe('migrateDocument', () => {
    it('should return v1 document unchanged', () => {
      const doc = makeDocument(1);
      const result = migrateDocument(doc);
      expect(result.migrated).toBe(false);
      expect(result.fromVersion).toBe(1);
      expect(result.toVersion).toBe(CURRENT_SCHEMA_VERSION);
      expect(result.steps).toHaveLength(0);
      expect(result.document).toBe(doc);
    });

    it('should throw for unsupported future version that has no migration', () => {
      // If we set schemaVersion to 0 (which would need migration to 1),
      // there's no migration defined for 0→1 since 1 is the base version
      const doc = makeDocument(0);
      expect(() => migrateDocument(doc)).toThrow('No migration defined');
    });
  });

  describe('migrateNodeConfig', () => {
    it('should return node unchanged when no migration exists', () => {
      const node = makeNode('scriptWriter');
      const result = migrateNodeConfig(node, '1.0.0');
      expect(result).toBe(node);
    });

    it('should preserve node identity when no migration applies', () => {
      const node = makeNode('unknownType');
      const result = migrateNodeConfig(node, '1.0.0');
      expect(result).toBe(node);
    });
  });
});
