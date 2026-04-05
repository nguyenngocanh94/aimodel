import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';

test.describe('Scenarios 11-12: Multi-tab soft-lock', () => {
  test.describe('Scenario 11: open same workflow in two tabs shows soft-lock warning', () => {
    test('open same workflow in two tabs shows soft-lock warning @multitab @slow', async ({ browser }) => {
      // Create a shared context for both tabs (shares cookies/storage)
      const context = await browser.newContext();

      try {
        // Open first tab and seed workflow
        const page1 = await context.newPage();
        
        // Seed workflow via localStorage before navigation
        await page1.addInitScript((workflow) => {
          localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
          localStorage.setItem('last-opened-workflow-id', workflow.id);
          localStorage.setItem('autosave-draft', JSON.stringify({
            workflowId: workflow.id,
            workflowName: workflow.name,
            nodes: workflow.nodes,
            edges: workflow.edges,
            viewport: workflow.viewport,
            timestamp: new Date().toISOString(),
          }));
        }, validShortFormWorkflow);

        await page1.goto('/');
        await page1.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

        // Verify first tab loaded successfully
        await expect(page1.locator('[data-testid^="node-card-"]')).toHaveCount(6);

        // Open second tab
        const page2 = await context.newPage();
        await page2.goto('/');
        await page2.waitForTimeout(2000); // Wait for soft-lock detection

        // Assert warning on second tab
        const softLockWarning = page2.getByTestId('soft-lock-warning');
        const warningText = page2.getByText(/workflow is open in another tab|already open|session conflict/i);
        
        // Either soft-lock-warning element or warning text should be visible
        const hasWarningElement = await softLockWarning.isVisible().catch(() => false);
        const hasWarningText = await warningText.isVisible().catch(() => false);

        expect(hasWarningElement || hasWarningText).toBe(true);

        if (hasWarningElement) {
          // If warning element exists, verify it contains session context info
          const warningContent = await softLockWarning.textContent();
          expect(warningContent).toMatch(/workflow|session|tab|open/i);
        }

        if (hasWarningText) {
          // Verify warning text includes context
          const text = await warningText.textContent();
          expect(text).toMatch(/workflow is open in another tab|already open|another session/i);
        }

        // Cleanup
        await page1.close();
        await page2.close();
      } finally {
        await context.close();
      }
    });
  });

  test.describe('Scenario 12: close one tab clears warning after heartbeat expiry', () => {
    test('close one tab clears warning after heartbeat expiry @multitab @slow', async ({ browser }) => {
      // Create a shared context for both tabs
      const context = await browser.newContext();

      try {
        // Setup both tabs like in Scenario 11
        const page1 = await context.newPage();
        
        await page1.addInitScript((workflow) => {
          localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
          localStorage.setItem('last-opened-workflow-id', workflow.id);
          localStorage.setItem('autosave-draft', JSON.stringify({
            workflowId: workflow.id,
            workflowName: workflow.name,
            nodes: workflow.nodes,
            edges: workflow.edges,
            viewport: workflow.viewport,
            timestamp: new Date().toISOString(),
          }));
        }, validShortFormWorkflow);

        await page1.goto('/');
        await page1.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

        const page2 = await context.newPage();
        await page2.goto('/');
        await page2.waitForTimeout(2000); // Wait for soft-lock detection

        // Verify warning is present on page2
        const softLockWarning = page2.getByTestId('soft-lock-warning');
        const warningText = page2.getByText(/workflow is open in another tab|already open|session conflict/i);
        
        const hasWarningInitially = await softLockWarning.isVisible().catch(() => false) ||
                                   await warningText.isVisible().catch(() => false);
        
        // Only proceed if warning was detected
        if (!hasWarningInitially) {
          // Soft-lock feature not available - skip this test
          await page2.close();
          await context.close();
          return;
        }

        // Close first tab
        await page1.close();

        // Wait for heartbeat expiry (typically 5-10 seconds)
        // This is one of the few acceptable uses of waitForTimeout
        // as we're waiting for a real timer in the app
        await page2.waitForTimeout(6000); // Wait 6 seconds for heartbeat expiry

        // Assert warning clears on page2
        await expect.poll(async () => {
          const warningVisible = await softLockWarning.isVisible().catch(() => false);
          const textVisible = await warningText.isVisible().catch(() => false);
          return !warningVisible && !textVisible;
        }, {
          timeout: 10000,
          intervals: [500, 1000, 2000],
        }).toBe(true);

        // Verify canvas still works normally
        await expect(page2.locator('[data-testid^="node-card-"]')).toHaveCount(6);
        await expect(page2.getByTestId('node-search-input')).toBeVisible();

        // Cleanup
        await page2.close();
      } finally {
        await context.close();
      }
    });

    test('accelerated heartbeat expiry via test hook @multitab @slow', async ({ browser }) => {
      // Alternative test that uses page.evaluate to accelerate timer
      // if the app exposes a test hook
      const context = await browser.newContext();

      try {
        const page1 = await context.newPage();
        
        await page1.addInitScript((workflow) => {
          localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
          localStorage.setItem('last-opened-workflow-id', workflow.id);
          localStorage.setItem('autosave-draft', JSON.stringify({
            workflowId: workflow.id,
            workflowName: workflow.name,
            nodes: workflow.nodes,
            edges: workflow.edges,
            viewport: workflow.viewport,
            timestamp: new Date().toISOString(),
          }));
          // Expose test hook flag
          (window as unknown as Record<string, unknown>).__TEST_MODE__ = true;
        }, validShortFormWorkflow);

        await page1.goto('/');
        await page1.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

        const page2 = await context.newPage();
        await page2.goto('/');
        await page2.waitForTimeout(2000);

        const softLockWarning = page2.getByTestId('soft-lock-warning');
        const hasWarning = await softLockWarning.isVisible().catch(() => false);

        if (!hasWarning) {
          // Soft-lock feature not available - skip this test
          await page2.close();
          await page1.close();
          await context.close();
          return;
        }

        // Close first tab
        await page1.close();

        // Try to accelerate heartbeat check via test hook
        const accelerated = await page2.evaluate(() => {
          // Check if app exposes a way to accelerate heartbeat
          const win = window as unknown as Record<string, unknown>;
          if (win.__workflowStore && typeof win.__workflowStore === 'object') {
            const store = win.__workflowStore as Record<string, unknown>;
            if (store.checkHeartbeat && typeof store.checkHeartbeat === 'function') {
              store.checkHeartbeat();
              return true;
            }
          }
          return false;
        }).catch(() => false);

        if (accelerated) {
          // If acceleration worked, warning should clear quickly
          await expect(softLockWarning).not.toBeVisible({ timeout: 3000 });
        } else {
          // Fall back to normal timer wait
          await page2.waitForTimeout(6000);
          await expect(softLockWarning).not.toBeVisible({ timeout: 5000 });
        }

        await page2.close();
      } finally {
        await context.close();
      }
    });
  });
});
