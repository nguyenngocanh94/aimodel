/**
 * Workflow import/export - AiModel-e0x.4
 * Versioned JSON import pipeline with validation.
 * Per plan section 12.5
 */

import type {
  WorkflowDocument,
} from '@/features/workflows/domain/workflow-types';
import { getTemplate } from '@/features/node-registry/node-registry';
import { checkCompatibility } from '@/features/workflows/domain/type-compatibility';
import {
  migrateDocument,
  isVersionSupported,
  CURRENT_SCHEMA_VERSION,
} from './workflow-migrations';

// ============================================================
// Export
// ============================================================

export interface ExportEnvelope {
  readonly format: 'ai-video-workflow';
  readonly version: number;
  readonly exportedAt: string;
  readonly document: WorkflowDocument;
}

/** Export a workflow document as a versioned JSON string. */
export function exportWorkflow(document: WorkflowDocument): string {
  const envelope: ExportEnvelope = {
    format: 'ai-video-workflow',
    version: CURRENT_SCHEMA_VERSION,
    exportedAt: new Date().toISOString(),
    document,
  };
  return JSON.stringify(envelope, null, 2);
}

/** Export a workflow document as a downloadable blob. */
export function exportWorkflowAsBlob(document: WorkflowDocument): Blob {
  const json = exportWorkflow(document);
  return new Blob([json], { type: 'application/json' });
}

// ============================================================
// Import report
// ============================================================

export type ImportIssueSeverity = 'error' | 'warning' | 'info';

export interface ImportIssue {
  readonly severity: ImportIssueSeverity;
  readonly message: string;
  readonly nodeId?: string;
  readonly edgeId?: string;
}

export type ImportStatus =
  | 'success'
  | 'migrated'
  | 'warnings'
  | 'errors';

export interface ImportReport {
  readonly status: ImportStatus;
  readonly document: WorkflowDocument | null;
  readonly issues: readonly ImportIssue[];
  readonly migrated: boolean;
  readonly migratedFrom?: number;
}

// ============================================================
// Import pipeline
// ============================================================

/** Parse and validate a workflow JSON string through the full import pipeline. */
export function importWorkflow(jsonString: string): ImportReport {
  const issues: ImportIssue[] = [];

  // Step 1: Parse JSON
  let parsed: unknown;
  try {
    parsed = JSON.parse(jsonString);
  } catch {
    return {
      status: 'errors',
      document: null,
      issues: [{ severity: 'error', message: 'Invalid JSON: could not parse' }],
      migrated: false,
    };
  }

  // Step 2: Validate outer shape
  if (!parsed || typeof parsed !== 'object') {
    return {
      status: 'errors',
      document: null,
      issues: [{ severity: 'error', message: 'Invalid format: expected an object' }],
      migrated: false,
    };
  }

  // Support both raw document and envelope format
  let rawDocument: unknown;
  const envelope = parsed as Record<string, unknown>;

  if (envelope.format === 'ai-video-workflow' && envelope.document) {
    rawDocument = envelope.document;
  } else if (envelope.id && envelope.schemaVersion && envelope.nodes) {
    rawDocument = parsed;
  } else {
    return {
      status: 'errors',
      document: null,
      issues: [{ severity: 'error', message: 'Invalid format: missing required fields' }],
      migrated: false,
    };
  }

  const doc = rawDocument as Record<string, unknown>;

  // Step 3: Check version
  const schemaVersion = doc.schemaVersion;
  if (typeof schemaVersion !== 'number') {
    return {
      status: 'errors',
      document: null,
      issues: [{ severity: 'error', message: 'Missing or invalid schemaVersion' }],
      migrated: false,
    };
  }

  if (!isVersionSupported(schemaVersion)) {
    return {
      status: 'errors',
      document: null,
      issues: [{
        severity: 'error',
        message: `Unsupported schema version ${schemaVersion} (latest: ${CURRENT_SCHEMA_VERSION})`,
      }],
      migrated: false,
    };
  }

  // Step 4: Run migrations
  let document: WorkflowDocument;
  let migrated = false;
  let migratedFrom: number | undefined;

  try {
    const migrationResult = migrateDocument(rawDocument as WorkflowDocument);
    document = migrationResult.document;
    migrated = migrationResult.migrated;
    if (migrated) {
      migratedFrom = migrationResult.fromVersion;
      issues.push({
        severity: 'info',
        message: `Migrated from v${migrationResult.fromVersion} to v${migrationResult.toVersion} (${migrationResult.steps.join(', ')})`,
      });
    }
  } catch (error) {
    return {
      status: 'errors',
      document: null,
      issues: [{
        severity: 'error',
        message: `Migration failed: ${error instanceof Error ? error.message : 'Unknown error'}`,
      }],
      migrated: false,
    };
  }

  // Step 5: Validate nodes against registry
  for (const node of document.nodes) {
    const template = getTemplate(node.type);
    if (!template) {
      issues.push({
        severity: 'warning',
        nodeId: node.id,
        message: `Unknown node type "${node.type}" — will be imported as unsupported node`,
      });
    }
  }

  // Step 6: Validate edge references
  const nodeIds = new Set(document.nodes.map((n) => n.id));
  for (const edge of document.edges) {
    if (!nodeIds.has(edge.sourceNodeId)) {
      issues.push({
        severity: 'error',
        edgeId: edge.id,
        message: `Edge references non-existent source node "${edge.sourceNodeId}"`,
      });
    }
    if (!nodeIds.has(edge.targetNodeId)) {
      issues.push({
        severity: 'error',
        edgeId: edge.id,
        message: `Edge references non-existent target node "${edge.targetNodeId}"`,
      });
    }
  }

  // Step 7: Validate semantic compatibility for edges
  for (const edge of document.edges) {
    const sourceNode = document.nodes.find((n) => n.id === edge.sourceNodeId);
    const targetNode = document.nodes.find((n) => n.id === edge.targetNodeId);
    if (!sourceNode || !targetNode) continue;

    const sourceTemplate = getTemplate(sourceNode.type);
    const targetTemplate = getTemplate(targetNode.type);
    if (!sourceTemplate || !targetTemplate) continue;

    const sourcePort = sourceTemplate.outputs.find((p) => p.key === edge.sourcePortKey);
    const targetPort = targetTemplate.inputs.find((p) => p.key === edge.targetPortKey);

    if (!sourcePort) {
      issues.push({
        severity: 'warning',
        edgeId: edge.id,
        message: `Source port "${edge.sourcePortKey}" not found on node "${sourceNode.type}"`,
      });
      continue;
    }

    if (!targetPort) {
      issues.push({
        severity: 'warning',
        edgeId: edge.id,
        message: `Target port "${edge.targetPortKey}" not found on node "${targetNode.type}"`,
      });
      continue;
    }

    const compat = checkCompatibility(sourcePort.dataType, targetPort.dataType);
    if (!compat.compatible) {
      issues.push({
        severity: 'warning',
        edgeId: edge.id,
        message: `Incompatible edge: ${sourcePort.dataType} → ${targetPort.dataType}`,
      });
    } else if (compat.coercionApplied) {
      issues.push({
        severity: 'info',
        edgeId: edge.id,
        message: `Coercion applied: ${sourcePort.dataType} → ${targetPort.dataType}`,
      });
    }
  }

  // Step 8: Validate configs
  for (const node of document.nodes) {
    const template = getTemplate(node.type);
    if (!template) continue;

    const result = template.configSchema.safeParse(node.config);
    if (!result.success) {
      issues.push({
        severity: 'warning',
        nodeId: node.id,
        message: `Config validation failed for "${node.label}": ${result.error.issues.map((i) => i.message).join(', ')}`,
      });
    }
  }

  // Step 9: Determine import status
  const hasErrors = issues.some((i) => i.severity === 'error');
  const hasWarnings = issues.some((i) => i.severity === 'warning');

  let status: ImportStatus;
  if (hasErrors) {
    status = 'errors';
  } else if (hasWarnings) {
    status = 'warnings';
  } else if (migrated) {
    status = 'migrated';
  } else {
    status = 'success';
  }

  return {
    status,
    document: hasErrors ? null : document,
    issues,
    migrated,
    migratedFrom,
  };
}
