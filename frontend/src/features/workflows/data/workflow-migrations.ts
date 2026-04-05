/**
 * Workflow document migrations - AiModel-e0x.5
 * Per plan section 12.6
 *
 * Migration layers (kept distinct per spec):
 * 1. Workflow JSON migrations → this file (migrateDocument)
 * 2. Node-template config migrations → this file (migrateNodeConfig)
 */

import type { WorkflowDocument, WorkflowNode } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Migration registry
// ============================================================

/** A migration function that upgrades a document from one version to the next. */
export type DocumentMigration = (doc: WorkflowDocument) => WorkflowDocument;

/**
 * Registry of document migrations.
 * Key is the source version, value migrates to (source + 1).
 * Currently empty — v1 is the initial version.
 * Example: { 1: migrateV1toV2 }
 */
const documentMigrations: Record<number, DocumentMigration> = {
  // When v2 is defined, add:
  // 1: migrateV1toV2,
};

/** The current latest schema version. */
export const CURRENT_SCHEMA_VERSION = 1;

// ============================================================
// Document migration
// ============================================================

export interface MigrationResult {
  readonly document: WorkflowDocument;
  readonly migrated: boolean;
  readonly fromVersion: number;
  readonly toVersion: number;
  readonly steps: readonly string[];
}

/**
 * Migrate a document to the current schema version.
 * Returns the document unchanged if already current.
 */
export function migrateDocument(doc: WorkflowDocument): MigrationResult {
  const fromVersion = doc.schemaVersion;
  const steps: string[] = [];
  let current = doc;

  let version = current.schemaVersion;
  while (version < CURRENT_SCHEMA_VERSION) {
    const migration = documentMigrations[version];
    if (!migration) {
      throw new Error(
        `No migration defined for schema version ${version} → ${version + 1}`,
      );
    }
    current = migration(current);
    steps.push(`v${version} → v${version + 1}`);
    version++;
  }

  return {
    document: current,
    migrated: steps.length > 0,
    fromVersion,
    toVersion: CURRENT_SCHEMA_VERSION,
    steps,
  };
}

// ============================================================
// Node-template config migrations
// ============================================================

/**
 * A node config migration function.
 * Returns the migrated config or null if no migration needed.
 */
export type NodeConfigMigration = (
  nodeType: string,
  config: unknown,
  templateVersion: string,
) => unknown | null;

/**
 * Registry of node-template config migrations.
 * Key format: "nodeType:fromVersion"
 */
const nodeConfigMigrations: Record<string, NodeConfigMigration> = {
  // Example:
  // 'scriptWriter:1.0.0': (nodeType, config, version) => ({ ...config, newField: 'default' }),
};

/**
 * Migrate a single node's config if needed.
 * Returns the node unchanged if no migration applies.
 */
export function migrateNodeConfig(
  node: WorkflowNode,
  currentTemplateVersion: string,
): WorkflowNode {
  // Look for a migration from any older version
  // For v1, there are no node config migrations
  const migrationKey = `${node.type}:${currentTemplateVersion}`;
  const migration = nodeConfigMigrations[migrationKey];

  if (!migration) return node;

  const migratedConfig = migration(node.type, node.config, currentTemplateVersion);
  if (migratedConfig === null) return node;

  return { ...node, config: migratedConfig as WorkflowNode['config'] };
}

// ============================================================
// Validation helpers for import
// ============================================================

/**
 * Check if a document's schema version is supported.
 */
export function isVersionSupported(schemaVersion: number): boolean {
  return schemaVersion >= 1 && schemaVersion <= CURRENT_SCHEMA_VERSION;
}

/**
 * Check if a document needs migration.
 */
export function needsMigration(schemaVersion: number): boolean {
  return schemaVersion < CURRENT_SCHEMA_VERSION;
}
