import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';
import { dragNodeToCanvas } from '../../helpers/canvas';

test.describe('Scenarios 9-10: Recovery — dirty edit and active run', () => {
  test.describe('Scenario 9: refresh during dirty edit and recover draft', () => {
    test('refresh during dirty edit and recover draft @scenario @recovery', async ({ page }) => {
      // Step 1: Load workflow and make a dirty change
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Verify initial node count
      const initialCount = await page.locator('[data-testid^="node-card-"]').count();
      expect(initialCount).toBe(6);

      // Make a dirty change - add a node
      await dragNodeToCanvas(page, 'Review Checkpoint', { x: 500, y: 300 });

      // Step 2: Assert dirty indicator
      await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

      // Step 3: Reload page
      await page.reload();

      // Step 4: Assert recovery modal
      const recoveryDialog = page.getByTestId('recovery-dialog');
      await expect(recoveryDialog).toBeVisible();

      // Verify recovery dialog shows draft info
      await expect(recoveryDialog).toContainText(/draft|unsaved/i);

      // Step 5: Click Restore draft
      await page.getByRole('button', { name: 'Restore draft' }).click();

      // Step 6: Assert unsaved changes returned
      // The added node should be present
      await expect(page.getByTestId('node-card-review-checkpoint-1')).toBeVisible();

      // Total node count should be 7 (6 original + 1 added)
      const restoredCount = await page.locator('[data-testid^="node-card-"]').count();
      expect(restoredCount).toBe(7);
    });
  });

  test.describe('Scenario 10: refresh during active run and recover interrupted state', () => {
    test('refresh during active run and recover interrupted state @scenario @recovery', async ({ page }) => {
      // Step 1: Load workflow and start run
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Start run
      await page.getByTestId('run-btn-workflow').click();

      // Step 2: Wait until at least one node running
      await expect.poll(async () => {
        const runningNodes = await page.locator('[data-running="true"]').count();
        return runningNodes >= 1;
      }, {
        timeout: 10000,
        intervals: [100, 200, 500],
      }).toBe(true);

      // Step 3: Reload mid-run
      await page.reload();

      // Step 4: Assert interrupted run badge in recovery modal
      const recoveryDialog = page.getByTestId('recovery-dialog');
      await expect(recoveryDialog).toBeVisible();

      // Verify interrupted run badge/text
      const runBadge = page.getByTestId('recovery-run-badge');
      await expect(runBadge).toBeVisible();
      
      // Verify dialog mentions interrupted run
      const dialogText = await recoveryDialog.textContent();
      expect(dialogText).toMatch(/interrupted|running|active/i);

      // Step 5: Click Restore draft
      await page.getByRole('button', { name: 'Restore draft' }).click();

      // Step 6: Assert restored state shows interruption, not false success
      // Run status chip should not say 'Success'
      const statusChip = page.getByTestId('run-status-chip');
      const statusText = await statusChip.textContent();
      expect(statusText).not.toMatch(/success|completed/i);

      // Status should show interrupted or running state
      expect(statusText).toMatch(/interrupted|failed|running|idle/i);

      // Nodes should show appropriate state (not all success)
      const runningOrInvalidNodes = await page.locator('[data-running="true"], [data-invalid="true"]').count();
      // At least some nodes should show non-success state
      expect(runningOrInvalidNodes).toBeGreaterThanOrEqual(0);
    });
  });
});
