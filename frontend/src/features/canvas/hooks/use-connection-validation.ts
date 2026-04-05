import { useCallback } from 'react';
import type { Connection, Edge, IsValidConnection } from '@xyflow/react';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { getTemplate } from '@/features/node-registry/node-registry';
import { checkCompatibility } from '@/features/workflows/domain/type-compatibility';
import type { CompatibilityResult } from '@/features/workflows/domain/workflow-types';

export interface ConnectionValidationResult {
  readonly valid: boolean;
  readonly compatibility: CompatibilityResult | null;
}

/**
 * Validate a candidate connection by looking up port DataTypes
 * from the node registry and checking via the compatibility matrix.
 */
export function validateConnection(
  connection: Connection,
  getNodeType: (nodeId: string) => string | undefined,
): ConnectionValidationResult {
  const { source, target, sourceHandle, targetHandle } = connection;
  if (!source || !target) {
    return { valid: false, compatibility: null };
  }

  // Prevent self-connections
  if (source === target) {
    return { valid: false, compatibility: null };
  }

  const sourceNodeType = getNodeType(source);
  const targetNodeType = getNodeType(target);
  if (!sourceNodeType || !targetNodeType) {
    return { valid: false, compatibility: null };
  }

  const sourceTemplate = getTemplate(sourceNodeType);
  const targetTemplate = getTemplate(targetNodeType);
  if (!sourceTemplate || !targetTemplate) {
    return { valid: false, compatibility: null };
  }

  const sourcePort = sourceTemplate.outputs.find(
    (p) => p.key === (sourceHandle ?? 'output'),
  );
  const targetPort = targetTemplate.inputs.find(
    (p) => p.key === (targetHandle ?? 'input'),
  );
  if (!sourcePort || !targetPort) {
    return { valid: false, compatibility: null };
  }

  const compatibility = checkCompatibility(sourcePort.dataType, targetPort.dataType);
  return { valid: compatibility.compatible, compatibility };
}

/**
 * useConnectionValidation - Hook providing React Flow's isValidConnection callback
 *
 * Checks type compatibility before allowing connections.
 * Also checks for duplicate single-port connections.
 */
export function useConnectionValidation(): IsValidConnection {
  const isValidConnection: IsValidConnection = useCallback(
    (connection: Connection | Edge) => {
      const state = useWorkflowStore.getState();
      const doc = state.document;

      const getNodeType = (nodeId: string) =>
        doc.nodes.find((n) => n.id === nodeId)?.type;

      // Normalize Edge's undefined handles to null for Connection compatibility
      const conn: Connection = {
        source: connection.source,
        target: connection.target,
        sourceHandle: connection.sourceHandle ?? null,
        targetHandle: connection.targetHandle ?? null,
      };

      const result = validateConnection(conn, getNodeType);
      if (!result.valid) return false;

      // Check for duplicate connections to non-multiple target ports
      const targetNodeType = getNodeType(conn.target!);
      if (targetNodeType) {
        const targetTemplate = getTemplate(targetNodeType);
        const targetPort = targetTemplate?.inputs.find(
          (p) => p.key === (conn.targetHandle ?? 'input'),
        );
        if (targetPort && !targetPort.multiple) {
          const existing = doc.edges.find(
            (e) =>
              e.targetNodeId === conn.target &&
              e.targetPortKey === (conn.targetHandle ?? 'input'),
          );
          if (existing) return false;
        }
      }

      return true;
    },
    [],
  );

  return isValidConnection;
}
