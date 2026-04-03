import { test, expect } from '@playwright/test';
import { dirtyDraftState } from '../../fixtures/recovery';

test.describe('Journey 6: Recovery after refresh', () => {
  test('recover after refresh @journey @recovery', async ({ page }) => {
    // Step 1: Seed state before navigation
    await page.addInitScript((state) => {
      // Store workflow data
      localStorage.setItem(`workflow-${state.workflowId}`, JSON.stringify({
        id: state.workflowId,
        name: state.workflowName,
        description: 'Test workflow',
        nodes: state.nodes ?? [],
        edges: state.edges ?? [],
        viewport: { x: 0, y: 0, zoom: 1 },
        schemaVersion: 1,
        tags: [],
        createdAt: state.timestamp,
        updatedAt: state.timestamp,
      }));

      // Store dirty indicator
      localStorage.setItem('workflow-dirty-indicator', 'true');

      // Store autosave draft
      localStorage.setItem('autosave-draft', JSON.stringify({
        workflowId: state.workflowId,
        workflowName: state.workflowName,
        nodes: state.nodes ?? [],
        edges: state.edges ?? [],
        viewport: { x: 0, y: 0, zoom: 1 },
        timestamp: state.timestamp,
      }));

      // Store last opened workflow
      localStorage.setItem('last-opened-workflow-id', state.workflowId);

      // Store interrupted run metadata
      if (state.hasActiveRun) {
        localStorage.setItem('active-run', JSON.stringify({
          workflowId: state.workflowId,
          status: 'interrupted',
          startedAt: state.timestamp,
          currentNodeId: state.nodes?.[0]?.id ?? null,
          completedNodes: [],
        }));
      }
    }, dirtyDraftState);

    // Step 2: Navigate to app
    await page.goto('/');

    // Step 3: Assert recovery dialog visible
    const recoveryDialog = page.getByTestId('recovery-dialog');
    await expect(recoveryDialog).toBeVisible();

    // Step 4: Assert dialog content
    // Verify autosave timestamp shown (date/time pattern)
    const dialogContent = await recoveryDialog.textContent();
    expect(dialogContent).toMatch(/\d{1,2}:\d{2}|\d{4}-\d{2}-\d{2}/);

    // Verify workflow name displayed
    await expect(recoveryDialog).toContainText(dirtyDraftState.workflowName);

    // Verify interrupted run badge visible
    const runBadge = page.getByTestId('recovery-run-badge');
    await expect(runBadge).toBeVisible();

    // Step 5: Click Restore draft
    await page.getByRole('button', { name: 'Restore draft' }).click();

    // Step 6: Assert workflow restored
    // Verify nodes from seeded state are visible
    const nodes = dirtyDraftState.nodes ?? [];
    for (const node of nodes) {
      const nodeCard = page.getByTestId(`node-card-${node.id}`);
      await expect(nodeCard).toBeVisible();
    }

    // Verify run status shows interrupted
    await expect(page.getByTestId('run-status-chip')).toContainText(/interrupted|failed/i);

    // Step 7: Assert recovery dialog dismissed
    await expect(recoveryDialog).not.toBeVisible();

    // Step 8: Cleanup
    await page.evaluate(() => localStorage.clear());
  });
});
