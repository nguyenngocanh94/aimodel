import { test, expect } from '@playwright/test';
import { failedSubtitleWorkflow } from '../../fixtures/workflows';
import { verifyNodeState } from '../../helpers/assertions';

test.describe('Scenarios 6-7: Fail node then fix and rerun', () => {
  test.describe('Scenario 6: fail a node due to invalid config', () => {
    test('fail a node due to invalid config @scenario', async ({ page }) => {
      // Step 1: Load workflow with Subtitle Formatter node
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, failedSubtitleWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

      // Step 2: Set maxCharsPerLine to 60 (already 60 in fixture, but ensure it's set)
      await page.getByTestId('node-card-subtitle-formatter-1').click();
      await page.getByTestId('inspector-tab-config').click();
      await page.getByLabel('maxCharsPerLine').fill('60');

      // Step 3: Run workflow
      await page.getByTestId('run-btn-workflow').click();

      // Wait for run to fail
      await expect(page.getByTestId('run-status-chip')).toContainText('Failed', {
        timeout: 30000,
      });

      // Step 4: Assert node error
      await verifyNodeState(page, 'subtitle-formatter-1', 'invalid');

      // Step 5: Assert error toast
      await expect(page.getByTestId('toast-run-error')).toBeVisible();

      // Step 6: Assert validation item
      await page.getByTestId('inspector-tab-validation').click();
      await expect(page.getByTestId('validation-item-config-max-chars')).toBeVisible();
    });
  });

  test.describe('Scenario 7: fix config and run from here', () => {
    test('fix config and run from here @scenario', async ({ page }) => {
      // Step 1: Start from Scenario 6 state
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, failedSubtitleWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

      // Setup: Create failure state
      await page.getByTestId('node-card-subtitle-formatter-1').click();
      await page.getByTestId('inspector-tab-config').click();
      await page.getByLabel('maxCharsPerLine').fill('60');
      await page.getByTestId('run-btn-workflow').click();
      await expect(page.getByTestId('run-status-chip')).toContainText('Failed', {
        timeout: 30000,
      });
      await verifyNodeState(page, 'subtitle-formatter-1', 'invalid');

      // Step 2: Fix config
      await page.getByTestId('inspector-tab-config').click();
      await page.getByLabel('maxCharsPerLine').clear();
      await page.getByLabel('maxCharsPerLine').fill('32');

      // Step 3: Select failed node and run from here
      await page.getByTestId('node-card-subtitle-formatter-1').click();
      await page.getByTestId('run-btn-from-here').click();

      // Step 4: Assert upstream not rerun
      const upstreamNode = page.getByTestId('node-card-script-writer-1');
      await expect.poll(async () => {
        const hasRunning = await upstreamNode.getAttribute('data-running');
        return hasRunning === 'true';
      }, {
        intervals: [100, 200, 500],
        timeout: 10000,
      }).toBe(false);

      // Verify upstream retained success/non-invalid state
      const upstreamInvalid = await upstreamNode.getAttribute('data-invalid');
      expect(upstreamInvalid).not.toBe('true');

      // Step 5: Assert downstream rerun
      const downstreamNode = page.getByTestId('node-card-subtitle-formatter-1');
      await expect(downstreamNode).toHaveAttribute('data-running', 'true', {
        timeout: 5000,
      });

      await expect.poll(async () => {
        const hasRunning = await downstreamNode.getAttribute('data-running');
        return hasRunning !== 'true';
      }, {
        timeout: 30000,
      }).toBe(true);

      await expect(downstreamNode).not.toHaveAttribute('data-invalid', 'true');

      // Step 6: Assert final success
      await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
        timeout: 10000,
      });
    });
  });
});
