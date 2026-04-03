import { test, expect } from '@playwright/test';
import { savedBrokenConnectionWorkflow } from '../../fixtures/workflows';
import { AppShellPage } from '../../pages/app-shell.page';

test.describe('Journey 2: Diagnose broken connection', () => {
  test('diagnose a broken connection @journey', async ({ page }) => {
    // Seed the workflow with broken connection
    await page.addInitScript((workflow) => {
      localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
      localStorage.setItem('last-opened-workflow-id', workflow.id);
    }, savedBrokenConnectionWorkflow);

    // Initialize app
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();

    // Step 1: Select the invalid edge
    const invalidEdge = page.getByTestId('edge-script-writer-1__video-composer-1');
    await invalidEdge.click();

    // Step 2: Assert invalid state
    await expect(invalidEdge).toHaveAttribute('data-invalid', 'true');

    // Step 3: Open validation tab
    await page.getByTestId('inspector-tab-validation').click();

    // Step 4: Assert inspector shows validation details
    await expect(page.getByText('Compatibility result')).toBeVisible();
    await expect(page.getByText('invalid')).toBeVisible();
    await expect(page.getByText(/source schema/i)).toBeVisible();
    await expect(page.getByText(/target schema/i)).toBeVisible();
    await expect(page.getByText(/suggested fixes/i)).toBeVisible();

    // Step 5: Apply quick fix - Insert Image Asset Mapper
    const fixButton = page.getByRole('button', { name: /insert image asset mapper/i });
    await fixButton.click();

    // Step 6: Assert adapter node appears
    const adapterNode = page.getByTestId('node-card-image-asset-mapper-1');
    await expect(adapterNode).toBeVisible();

    // Step 7: Assert old invalid edge removed
    await expect(invalidEdge).not.toBeVisible();

    // Step 8: Assert two new valid edges exist without data-invalid
    const edges = page.locator('[data-testid^="edge-"]');
    const edgeCount = await edges.count();
    expect(edgeCount).toBe(3); // original edge from user-prompt + 2 new edges

    // Check that no edges have data-invalid
    const invalidEdges = page.locator('[data-invalid="true"]');
    await expect(invalidEdges).toHaveCount(0);

    // Step 9: Verify no remaining invalid edges
    const allInvalidElements = page.locator('[data-invalid="true"]');
    const invalidCount = await allInvalidElements.count();
    expect(invalidCount).toBe(0);
  });
});
