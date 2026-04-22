/**
 * Graph Validator - AiModel-1n1.1
 *
 * Implements validation rules per plan sections 16.1-16.3:
 * - Workflow-level: unique ids, existing nodes, acyclic graph, disabled warnings
 * - Node-level: config schema, required inputs, orphan nodes
 * - Edge-level: port direction, compatibility, duplicates, self-loops
 */

import { getTemplate } from '@/features/node-registry/node-registry';
import type { NodeTemplate } from '@/features/node-registry/node-registry';
import type {
  WorkflowDocument,
  WorkflowNode,
  WorkflowEdge,
  ValidationIssue,
  ValidationSeverity,
} from './workflow-types';
import { checkCompatibility } from './type-compatibility';

// ============================================================
// Validation Context
// ============================================================

interface ValidationContext {
  readonly document: WorkflowDocument;
  readonly nodeMap: Map<string, WorkflowNode>;
  readonly incomingEdges: Map<string, WorkflowEdge[]>;
  readonly outgoingEdges: Map<string, WorkflowEdge[]>;
  readonly templates: Map<string, NodeTemplate<unknown>>;
  issueCounter: number;
}

function createValidationContext(document: WorkflowDocument): ValidationContext {
  const nodeMap = new Map<string, WorkflowNode>();
  for (const node of document.nodes) {
    // Last-wins for nodeMap; duplicate detection is separate
    nodeMap.set(node.id, node);
  }

  const incomingEdges = new Map<string, WorkflowEdge[]>();
  const outgoingEdges = new Map<string, WorkflowEdge[]>();

  for (const edge of document.edges) {
    const incoming = incomingEdges.get(edge.targetNodeId) ?? [];
    incoming.push(edge);
    incomingEdges.set(edge.targetNodeId, incoming);

    const outgoing = outgoingEdges.get(edge.sourceNodeId) ?? [];
    outgoing.push(edge);
    outgoingEdges.set(edge.sourceNodeId, outgoing);
  }

  const templates = new Map<string, NodeTemplate<unknown>>();
  for (const node of document.nodes) {
    if (!templates.has(node.id)) {
      const template = getTemplate(node.type);
      if (template) {
        templates.set(node.id, template);
      }
    }
  }

  return {
    document,
    nodeMap,
    incomingEdges,
    outgoingEdges,
    templates,
    issueCounter: 0,
  };
}

// ============================================================
// Issue Factory
// ============================================================

function createIssue(
  ctx: ValidationContext,
  severity: ValidationSeverity,
  scope: ValidationIssue['scope'],
  code: ValidationIssue['code'],
  message: string,
  options: {
    nodeId?: string;
    edgeId?: string;
    portKey?: string;
    suggestion?: string;
  } = {},
): ValidationIssue {
  ctx.issueCounter += 1;
  return {
    id: `val-${ctx.issueCounter}`,
    severity,
    scope,
    code,
    message,
    ...(options.nodeId !== undefined ? { nodeId: options.nodeId } : {}),
    ...(options.edgeId !== undefined ? { edgeId: options.edgeId } : {}),
    ...(options.portKey !== undefined ? { portKey: options.portKey } : {}),
    ...(options.suggestion !== undefined ? { suggestion: options.suggestion } : {}),
  };
}

// ============================================================
// Workflow-Level Validation (16.1)
// ============================================================

function validateWorkflowLevel(ctx: ValidationContext): ValidationIssue[] {
  const issues: ValidationIssue[] = [];

  // 16.1.1 - Workflow must have a non-empty name
  if (!ctx.document.name || ctx.document.name.trim() === '') {
    issues.push(
      createIssue(ctx, 'warning', 'workflow', 'configInvalid', 'Workflow has no name', {
        suggestion: 'Add a descriptive name to your workflow',
      }),
    );
  }

  // 16.1.2 - Node IDs must be unique
  const nodeIdCounts = new Map<string, number>();
  for (const node of ctx.document.nodes) {
    nodeIdCounts.set(node.id, (nodeIdCounts.get(node.id) ?? 0) + 1);
  }
  for (const [id, count] of nodeIdCounts) {
    if (count > 1) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'workflow',
          'configInvalid',
          `Duplicate node ID: ${id}`,
          { suggestion: 'Each node must have a unique identifier' },
        ),
      );
    }
  }

  // 16.1.3 - Edge IDs must be unique
  const edgeIdCounts = new Map<string, number>();
  for (const edge of ctx.document.edges) {
    edgeIdCounts.set(edge.id, (edgeIdCounts.get(edge.id) ?? 0) + 1);
  }
  for (const [id, count] of edgeIdCounts) {
    if (count > 1) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'workflow',
          'configInvalid',
          `Duplicate edge ID: ${id}`,
          { suggestion: 'Each edge must have a unique identifier' },
        ),
      );
    }
  }

  // 16.1.4 - Source and target node IDs in edges must exist
  const nodeIds = new Set(ctx.document.nodes.map((n) => n.id));
  for (const edge of ctx.document.edges) {
    if (!nodeIds.has(edge.sourceNodeId)) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'edge',
          'configInvalid',
          `Edge references non-existent source node: ${edge.sourceNodeId}`,
          { edgeId: edge.id, suggestion: 'Remove or reconnect this edge' },
        ),
      );
    }
    if (!nodeIds.has(edge.targetNodeId)) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'edge',
          'configInvalid',
          `Edge references non-existent target node: ${edge.targetNodeId}`,
          { edgeId: edge.id, suggestion: 'Remove or reconnect this edge' },
        ),
      );
    }
  }

  // 16.1.5 - Ports referenced by edges must exist on respective node templates
  for (const edge of ctx.document.edges) {
    const sourceNode = ctx.nodeMap.get(edge.sourceNodeId);
    const targetNode = ctx.nodeMap.get(edge.targetNodeId);
    if (!sourceNode || !targetNode) continue;

    const sourceTemplate = ctx.templates.get(sourceNode.id);
    const targetTemplate = ctx.templates.get(targetNode.id);

    if (sourceTemplate) {
      const allSourcePorts = [...sourceTemplate.inputs, ...sourceTemplate.outputs];
      const portExists = allSourcePorts.some((p) => p.key === edge.sourcePortKey);
      if (!portExists) {
        issues.push(
          createIssue(
            ctx,
            'error',
            'edge',
            'configInvalid',
            `Source port "${edge.sourcePortKey}" does not exist on node "${sourceNode.label}"`,
            { edgeId: edge.id, suggestion: 'Check the source node template for valid ports' },
          ),
        );
      }
    }

    if (targetTemplate) {
      const allTargetPorts = [...targetTemplate.inputs, ...targetTemplate.outputs];
      const portExists = allTargetPorts.some((p) => p.key === edge.targetPortKey);
      if (!portExists) {
        issues.push(
          createIssue(
            ctx,
            'error',
            'edge',
            'configInvalid',
            `Target port "${edge.targetPortKey}" does not exist on node "${targetNode.label}"`,
            { edgeId: edge.id, suggestion: 'Check the target node template for valid ports' },
          ),
        );
      }
    }
  }

  // 16.1.6 - Cycle detection (DFS)
  const cycles = detectCycles(ctx);
  for (const cycle of cycles) {
    issues.push(
      createIssue(
        ctx,
        'error',
        'workflow',
        'cycleDetected',
        `Cycle detected: ${cycle.join(' \u2192 ')}`,
        {
          suggestion:
            'Workflows must be acyclic (DAG). Remove one of the edges creating the cycle.',
        },
      ),
    );
  }

  // 16.1.7 - Disabled nodes with connected required outputs
  for (const node of ctx.document.nodes) {
    if (node.disabled) {
      const outgoing = ctx.outgoingEdges.get(node.id) ?? [];
      for (const edge of outgoing) {
        const targetNode = ctx.nodeMap.get(edge.targetNodeId);
        if (targetNode) {
          issues.push(
            createIssue(
              ctx,
              'warning',
              'node',
              'disabledNode',
              `Disabled node "${node.label}" may affect downstream node "${targetNode.label}"`,
              {
                nodeId: node.id,
                suggestion: `Enable "${node.label}" or disconnect it from "${targetNode.label}"`,
              },
            ),
          );
        }
      }
    }
  }

  return issues;
}

// ============================================================
// Cycle Detection (DFS color-marking)
// ============================================================

function detectCycles(ctx: ValidationContext): string[][] {
  const cycles: string[][] = [];
  const WHITE = 0;
  const GRAY = 1;
  const BLACK = 2;
  const color = new Map<string, number>();

  for (const node of ctx.document.nodes) {
    color.set(node.id, WHITE);
  }

  function dfs(nodeId: string, path: string[]): void {
    color.set(nodeId, GRAY);
    path.push(nodeId);

    const outgoing = ctx.outgoingEdges.get(nodeId) ?? [];
    for (const edge of outgoing) {
      const targetId = edge.targetNodeId;
      const targetColor = color.get(targetId);

      if (targetColor === WHITE) {
        dfs(targetId, path);
      } else if (targetColor === GRAY) {
        // Back edge found: cycle from targetId back to itself through path
        const cycleStart = path.indexOf(targetId);
        const cycle = path.slice(cycleStart).concat([targetId]);
        cycles.push(cycle);
      }
      // BLACK nodes are fully processed, skip
    }

    path.pop();
    color.set(nodeId, BLACK);
  }

  for (const node of ctx.document.nodes) {
    if (color.get(node.id) === WHITE) {
      dfs(node.id, []);
    }
  }

  return cycles;
}

// ============================================================
// Node-Level Validation (16.2)
// ============================================================

function validateNodeLevel(ctx: ValidationContext): ValidationIssue[] {
  const issues: ValidationIssue[] = [];

  for (const node of ctx.document.nodes) {
    const template = ctx.templates.get(node.id);

    if (!template) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'node',
          'configInvalid',
          `Node "${node.label}" has unknown type: ${node.type}`,
          { nodeId: node.id, suggestion: 'Check that the node type is registered' },
        ),
      );
      continue;
    }

    // 16.2.1 - Config must satisfy node's Zod schema when available
    // Pilot templates (NM3+) rely on backend validation; configSchema may be absent.
    if (template.configSchema) {
      const configResult = template.configSchema.safeParse(node.config);
      if (!configResult.success) {
        const errorMessages = configResult.error.errors.map((e) => e.message).join(', ');
        issues.push(
          createIssue(
            ctx,
            'error',
            'config',
            'configInvalid',
            `Invalid configuration for "${node.label}": ${errorMessages}`,
            { nodeId: node.id, suggestion: 'Check the node configuration panel' },
          ),
        );
      }
    }

    // 16.2.2 - Required inputs must be connected
    const incoming = ctx.incomingEdges.get(node.id) ?? [];
    for (const inputPort of template.inputs) {
      if (inputPort.required) {
        const connected = incoming.filter((e) => e.targetPortKey === inputPort.key);
        if (connected.length === 0) {
          issues.push(
            createIssue(
              ctx,
              'error',
              'port',
              'missingRequiredInput',
              `Required input "${inputPort.label}" on "${node.label}" is not connected`,
              {
                nodeId: node.id,
                portKey: inputPort.key,
                suggestion: `Connect an edge to the "${inputPort.label}" port`,
              },
            ),
          );
        }
      }
    }

    // 16.2.3 - Orphan nodes (no connections at all)
    const outgoing = ctx.outgoingEdges.get(node.id) ?? [];
    if (incoming.length === 0 && outgoing.length === 0) {
      issues.push(
        createIssue(
          ctx,
          'warning',
          'node',
          'orphanNode',
          `Node "${node.label}" has no connections`,
          { nodeId: node.id, suggestion: 'Connect this node to other nodes or remove it' },
        ),
      );
    }
  }

  return issues;
}

// ============================================================
// Edge-Level Validation (16.3)
// ============================================================

function validateEdgeLevel(ctx: ValidationContext): ValidationIssue[] {
  const issues: ValidationIssue[] = [];

  for (const edge of ctx.document.edges) {
    // 16.3.5 - No self-loop edges
    if (edge.sourceNodeId === edge.targetNodeId) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'edge',
          'configInvalid',
          `Self-loop detected on node "${ctx.nodeMap.get(edge.sourceNodeId)?.label ?? edge.sourceNodeId}"`,
          { edgeId: edge.id, suggestion: 'Remove this edge or connect to a different node' },
        ),
      );
      continue;
    }

    const sourceNode = ctx.nodeMap.get(edge.sourceNodeId);
    const targetNode = ctx.nodeMap.get(edge.targetNodeId);

    if (!sourceNode || !targetNode) continue;

    const sourceTemplate = ctx.templates.get(sourceNode.id);
    const targetTemplate = ctx.templates.get(targetNode.id);

    if (!sourceTemplate || !targetTemplate) continue;

    // 16.3.1 - Source port must be an output port
    const sourcePort = sourceTemplate.outputs.find((p) => p.key === edge.sourcePortKey);
    if (!sourcePort) {
      // Check if it exists as an input port (wrong direction)
      const asInput = sourceTemplate.inputs.find((p) => p.key === edge.sourcePortKey);
      if (asInput) {
        issues.push(
          createIssue(
            ctx,
            'error',
            'edge',
            'configInvalid',
            `Source port "${edge.sourcePortKey}" on "${sourceNode.label}" is an input port, not an output`,
            { edgeId: edge.id, suggestion: 'Connect from an output port instead' },
          ),
        );
      }
      // Port not found at all is already handled in 16.1.5
      continue;
    }

    // 16.3.2 - Target port must be an input port
    const targetPort = targetTemplate.inputs.find((p) => p.key === edge.targetPortKey);
    if (!targetPort) {
      // Check if it exists as an output port (wrong direction)
      const asOutput = targetTemplate.outputs.find((p) => p.key === edge.targetPortKey);
      if (asOutput) {
        issues.push(
          createIssue(
            ctx,
            'error',
            'edge',
            'configInvalid',
            `Target port "${edge.targetPortKey}" on "${targetNode.label}" is an output port, not an input`,
            { edgeId: edge.id, suggestion: 'Connect to an input port instead' },
          ),
        );
      }
      continue;
    }

    // 16.3.3 - Type compatibility check
    const compatibility = checkCompatibility(sourcePort.dataType, targetPort.dataType);
    if (!compatibility.compatible) {
      issues.push(
        createIssue(
          ctx,
          'error',
          'edge',
          'incompatiblePortTypes',
          `Incompatible types: ${sourcePort.dataType} \u2192 ${targetPort.dataType}`,
          {
            edgeId: edge.id,
            suggestion: compatibility.suggestedAdapterNodeType
              ? `Try inserting a "${compatibility.suggestedAdapterNodeType}" node between them`
              : 'Connect compatible port types',
          },
        ),
      );
    } else if (compatibility.coercionApplied) {
      issues.push(
        createIssue(
          ctx,
          'warning',
          'edge',
          'coercionApplied',
          `Type coercion applied: ${sourcePort.dataType} \u2192 ${targetPort.dataType}`,
          {
            edgeId: edge.id,
            suggestion: compatibility.reason,
          },
        ),
      );
    }
  }

  // 16.3.4 - No duplicate edges to single-value (multiple:false) input ports
  const targetPortEdges = new Map<string, WorkflowEdge[]>();
  for (const edge of ctx.document.edges) {
    // Skip self-loops (already reported)
    if (edge.sourceNodeId === edge.targetNodeId) continue;
    const key = `${edge.targetNodeId}:${edge.targetPortKey}`;
    const edges = targetPortEdges.get(key) ?? [];
    edges.push(edge);
    targetPortEdges.set(key, edges);
  }

  for (const [key, edges] of targetPortEdges) {
    if (edges.length > 1) {
      const separatorIdx = key.indexOf(':');
      const nodeId = key.slice(0, separatorIdx);
      const portKey = key.slice(separatorIdx + 1);
      const targetNode = ctx.nodeMap.get(nodeId);
      const template = targetNode ? ctx.templates.get(targetNode.id) : undefined;
      const port = template?.inputs.find((p) => p.key === portKey);

      if (port && !port.multiple) {
        issues.push(
          createIssue(
            ctx,
            'error',
            'edge',
            'configInvalid',
            `Port "${port.label}" on "${targetNode?.label}" receives multiple connections but does not accept multiple inputs`,
            {
              edgeId: edges[1].id,
              suggestion: 'Disconnect extra edges or use a node that supports multiple inputs',
            },
          ),
        );
      }
    }
  }

  return issues;
}

// ============================================================
// Sort Helper
// ============================================================

const SEVERITY_ORDER: Record<ValidationSeverity, number> = {
  error: 0,
  warning: 1,
  info: 2,
};

function sortIssues(issues: ValidationIssue[]): ValidationIssue[] {
  return issues.slice().sort((a, b) => SEVERITY_ORDER[a.severity] - SEVERITY_ORDER[b.severity]);
}

// ============================================================
// Main Validator
// ============================================================

/**
 * Validate a workflow document and return all issues found.
 *
 * Issues are returned sorted by severity: errors first, then warnings, then info.
 *
 * @param document The workflow document to validate
 * @returns Readonly array of validation issues
 */
export function validateWorkflow(document: WorkflowDocument): readonly ValidationIssue[] {
  const ctx = createValidationContext(document);

  const issues: ValidationIssue[] = [
    ...validateWorkflowLevel(ctx),
    ...validateNodeLevel(ctx),
    ...validateEdgeLevel(ctx),
  ];

  return sortIssues(issues);
}

/**
 * Check if a workflow has any error-level issues.
 * @param document The workflow document to check
 * @returns True if there are errors
 */
export function hasErrors(document: WorkflowDocument): boolean {
  const issues = validateWorkflow(document);
  return issues.some((i) => i.severity === 'error');
}

/**
 * Get a summary of validation results.
 * @param document The workflow document to check
 * @returns Summary object with counts and validity flag
 */
export function getValidationSummary(document: WorkflowDocument): {
  readonly errorCount: number;
  readonly warningCount: number;
  readonly infoCount: number;
  readonly isValid: boolean;
} {
  const issues = validateWorkflow(document);
  return {
    errorCount: issues.filter((i) => i.severity === 'error').length,
    warningCount: issues.filter((i) => i.severity === 'warning').length,
    infoCount: issues.filter((i) => i.severity === 'info').length,
    isValid: !issues.some((i) => i.severity === 'error'),
  };
}
