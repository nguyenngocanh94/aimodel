import { test, expect } from '@playwright/test';
import { failedSubtitleWorkflow } from '../../fixtures/workflows';
import { AppShellPage } from '../../pages/app-shell.page';
import { verifyNodeState } from '../../helpers/assertions';

test.describe('Journey 3: Rerun from failed step', () => {
  test('rerun from a failed step @journey', async ({ page }) => {
    // Seed the workflow with failing subtitle node
    await page.addInitScript((workflow) => {
      localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
      localStorage.setItem('last-opened-workflow-id', workflow.id);
    }, failedSubtitleWorkflow);

    // Initialize app
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();

    // Step 1: Run workflow
    await page.getByTestId('run-btn-workflow').click();
    
    // Wait for run to complete (will fail)
    await expect(page.getByTestId('run-status-chip')).toContainText('Failed', {
      timeout: 30000,
    });

    // Step 2: Assert failure state
    await verifyNodeState(page, 'subtitle-formatter-1', 'invalid');
    await expect(page.getByTestId('toast-run-error')).toBeVisible();

    // Step 3: Assert validation details
    await page.getByTestId('node-card-subtitle-formatter-1').click();
    await page.getByTestId('inspector-tab-validation').click();
    await expect(page.getByTestId('validation-item-config-max-chars')).toBeVisible();

    // Step 4: Fix config
    await page.getByTestId('inspector-tab-config').click();
    await page.getByLabel('maxCharsPerLine').clear();
    await page.getByLabel('maxCharsPerLine').fill('32');

    // Verify config updated
    await expect(page.getByLabel('maxCharsPerLine')).toHaveValue('32');

    // Step 5: Run from here
    await page.getByTestId('run-btn-from-here').click();

    // Step 6: Assert upstream NOT rerun (should remain in success state)
    const upstreamNode = page.getByTestId('node-card-script-writer-1');
    
    // Poll to ensure upstream never shows running state
    await expect.poll(async () => {
      const hasRunningAttr = await upstreamNode.getAttribute('data-running');
      return hasRunningAttr === 'true';
    }, {
      intervals: [100, 200, 500],
      timeout: 10000,
    }).toBe(false);

    // Verify upstream has success/stale state (not invalid)
    const upstreamInvalid = await upstreamNode.getAttribute('data-invalid');
    expect(upstreamInvalid).not.toBe('true');

    // Step 7: Assert downstream rerun
    const downstreamNode = page.getByTestId('node-card-subtitle-formatter-1');
    
    // Should transition through running
    await expect(downstreamNode).toHaveAttribute('data-running', 'true', {
      timeout: 5000,
    });

    // Then to success
    await expect.poll(async () => {
      const hasRunning = await downstreamNode.getAttribute('data-running');
      return hasRunning !== 'true';
    }, {
      timeout: 30000,
    }).toBe(true);

    // Verify no longer invalid
    await expect(downstreamNode).not.toHaveAttribute('data-invalid', 'true');

    // Step 8: Assert final success
    await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
      timeout: 10000,
    });

    // Verify toast error is gone
    await expect(page.getByTestId('toast-run-error')).not.toBeVisible();
  });
});
