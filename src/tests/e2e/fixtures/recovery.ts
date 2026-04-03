import type { BrowserContext } from '@playwright/test';
import type { WorkflowDocument } from '../../../features/workflows/domain/workflow-types';

export interface RecoveryState {
  workflowId: string;
  workflowName: string;
  dirty: boolean;
  hasActiveRun: boolean;
  timestamp: string;
  nodes?: WorkflowDocument['nodes'];
  edges?: WorkflowDocument['edges'];
}

export const dirtyDraftState: RecoveryState = {
  workflowId: 'recovery-test-workflow',
  workflowName: 'Unsaved Draft',
  dirty: true,
  hasActiveRun: false,
  timestamp: new Date().toISOString(),
  nodes: [
    {
      id: 'user-prompt-1',
      type: 'user-prompt',
      label: 'User Prompt',
      position: { x: 100, y: 100 },
      config: { prompt: 'Test draft' },
    },
  ],
  edges: [],
};

export const runningWorkflowState: RecoveryState = {
  workflowId: 'running-test-workflow',
  workflowName: 'Active Run Workflow',
  dirty: true,
  hasActiveRun: true,
  timestamp: new Date().toISOString(),
  nodes: [
    {
      id: 'user-prompt-1',
      type: 'user-prompt',
      label: 'User Prompt',
      position: { x: 100, y: 100 },
      config: { prompt: 'Test run' },
    },
    {
      id: 'script-writer-1',
      type: 'script-writer',
      label: 'Script Writer',
      position: { x: 400, y: 100 },
      config: { tone: 'casual' },
    },
  ],
  edges: [
    {
      id: 'edge-1',
      sourceNodeId: 'user-prompt-1',
      sourcePortKey: 'output',
      targetNodeId: 'script-writer-1',
      targetPortKey: 'input',
    },
  ],
};

export async function seedRecoveryState(
  context: BrowserContext,
  state: RecoveryState
): Promise<void> {
  await context.addInitScript((recoveryData: RecoveryState) => {
    const draftKey = `workflow-${recoveryData.workflowId}`;
    
    localStorage.setItem(draftKey, JSON.stringify({
      id: recoveryData.workflowId,
      name: recoveryData.workflowName,
      description: 'Recovery test workflow',
      nodes: recoveryData.nodes ?? [],
      edges: recoveryData.edges ?? [],
      viewport: { x: 0, y: 0, zoom: 1 },
    }));

    if (recoveryData.dirty) {
      localStorage.setItem('workflow-dirty-indicator', 'true');
      localStorage.setItem('autosave-draft', JSON.stringify({
        workflowId: recoveryData.workflowId,
        workflowName: recoveryData.workflowName,
        nodes: recoveryData.nodes ?? [],
        edges: recoveryData.edges ?? [],
        viewport: { x: 0, y: 0, zoom: 1 },
        timestamp: recoveryData.timestamp,
      }));
    }

    if (recoveryData.hasActiveRun) {
      localStorage.setItem('active-run', JSON.stringify({
        workflowId: recoveryData.workflowId,
        status: 'running',
        startedAt: recoveryData.timestamp,
        currentNodeId: recoveryData.nodes?.[1]?.id ?? null,
        completedNodes: [recoveryData.nodes?.[0]?.id ?? ''],
      }));
    }
  }, state);
}
