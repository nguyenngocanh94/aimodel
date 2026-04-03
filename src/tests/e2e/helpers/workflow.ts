import type { Page, BrowserContext } from '@playwright/test';

interface RecoveryState {
  workflowId: string;
  workflowName: string;
  dirty: boolean;
  hasActiveRun: boolean;
  timestamp: string;
}

export async function runWorkflow(page: Page): Promise<void> {
  const button = page.getByTestId('run-btn-workflow');
  await button.click();

  const statusChip = page.getByTestId('run-status-chip');
  await statusChip.waitFor();
  await statusChip.getByText('Running').waitFor();
}

export async function runSelectedNode(page: Page): Promise<void> {
  const button = page.getByTestId('run-btn-node');
  await button.click();

  const statusChip = page.getByTestId('run-status-chip');
  await statusChip.waitFor();
  await statusChip.getByText('Running').waitFor();
}

export async function runFromHere(page: Page): Promise<void> {
  const button = page.getByTestId('run-btn-from-here');
  await button.click();

  const statusChip = page.getByTestId('run-status-chip');
  await statusChip.waitFor();
  await statusChip.getByText('Running').waitFor();
}

export async function seedWorkflow(
  page: Page,
  fixtureName: string
): Promise<void> {
  // Load fixture and inject via localStorage or API
  const fixture = await import(`../fixtures/${fixtureName}.json`);
  
  await page.addInitScript((workflowData: unknown) => {
    localStorage.setItem('workflow-seed', JSON.stringify(workflowData));
  }, fixture);
}

export async function seedRecoveryState(
  context: BrowserContext,
  state: RecoveryState
): Promise<void> {
  await context.addInitScript((recoveryData: RecoveryState) => {
    localStorage.setItem('autosave-draft', JSON.stringify({
      workflowId: recoveryData.workflowId,
      workflowName: recoveryData.workflowName,
      nodes: [],
      edges: [],
      viewport: { x: 0, y: 0, zoom: 1 },
      timestamp: recoveryData.timestamp,
    }));
    
    if (recoveryData.hasActiveRun) {
      localStorage.setItem('active-run', JSON.stringify({
        workflowId: recoveryData.workflowId,
        status: 'running',
        startedAt: recoveryData.timestamp,
      }));
    }
  }, state);
}
