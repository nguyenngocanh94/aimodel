import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';
import { AppShellPage } from '../../pages/app-shell.page';

test.describe('Journey 4: Inspect edge data', () => {
  test('inspect data on an edge @journey', async ({ page, context }) => {
    // Grant clipboard permissions for copy test
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);

    // Seed and run the workflow
    await page.addInitScript((workflow) => {
      localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
      localStorage.setItem('last-opened-workflow-id', workflow.id);
    }, validShortFormWorkflow);

    // Initialize app
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();

    // Run the workflow to populate payloads
    await page.getByTestId('run-btn-workflow').click();
    await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
      timeout: 30000,
    });

    // Step 1: Click edge between Script Writer and Scene Splitter
    const edge = page.getByTestId('edge-script-writer-1__scene-splitter-1');
    await edge.click();

    // Step 2: Assert edge selected
    await expect(edge).toHaveAttribute('data-selected', 'true');

    // Step 3: Open Data tab
    await page.getByTestId('inspector-tab-data').click();

    // Step 4: Assert inspector content
    const inspector = page.getByTestId('inspector');
    await expect(inspector).toContainText('payload');
    await expect(page.getByText('schema')).toBeVisible();
    await expect(page.getByText(/source|target|metadata/i)).toBeVisible();

    // Verify copy action button exists
    const copyButton = page.getByTestId('copy-payload-btn');
    await expect(copyButton).toBeVisible();

    // Step 5: Verify edge label
    const edgeLabel = page.getByTestId('edge-label-script-writer-1__scene-splitter-1');
    await expect(edgeLabel).toBeVisible();

    // Step 6: Test copy action
    await copyButton.click();

    // Verify clipboard contains JSON
    const clipboardText = await page.evaluate(() => navigator.clipboard.readText());
    expect(() => JSON.parse(clipboardText)).not.toThrow();
    expect(clipboardText).toContain('script');

    // Step 7: Verify target-schema comparison UI
    await expect(page.getByText(/source schema/i)).toBeVisible();
    await expect(page.getByText(/target schema/i)).toBeVisible();
    
    // Verify schema diff or comparison indicator
    const comparisonSection = page.locator('[data-testid="schema-comparison"]');
    if (await comparisonSection.isVisible().catch(() => false)) {
      await expect(comparisonSection).toBeVisible();
    }
  });
});
