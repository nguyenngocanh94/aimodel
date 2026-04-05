/**
 * Execution planning types - AiModel-ecs.2
 * Per plan section 11.3.1
 */

import type { ExecutionRun } from '@/features/workflows/domain/workflow-types';

export interface ExecutionPlan {
  readonly runId: string;
  readonly workflowId: string;
  readonly trigger: ExecutionRun['trigger'];
  readonly targetNodeId?: string;
  /** All nodes in the execution scope (executable + required providers). */
  readonly scopeNodeIds: readonly string[];
  /** Topologically ordered node IDs for execution. */
  readonly orderedNodeIds: readonly string[];
  /** Node IDs that will be skipped (disabled, missing inputs). */
  readonly skippedNodeIds: readonly string[];
}
