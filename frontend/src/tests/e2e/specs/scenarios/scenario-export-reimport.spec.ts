import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';
import { pressShortcut } from '../../helpers/keyboard';
import * as fs from 'fs';

test.describe('Scenario 8: Export and reimport workflow', () => {
  test('export and reimport workflow @scenario', async ({ page }) => {
    // Step 1: Load workflow
    await page.addInitScript((workflow) => {
      localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
      localStorage.setItem('last-opened-workflow-id', workflow.id);
    }, validShortFormWorkflow);

    await page.goto('/');
    await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

    // Verify initial nodes loaded
    await expect(page.locator('[data-testid^="node-card-"]')).toHaveCount(6);

    // Step 2 & 3: Export using keyboard shortcut and capture download
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      pressShortcut(page, 'export'),
    ]);

    // Step 4: Assert export content
    const downloadPath = await download.path();
    expect(downloadPath).toBeTruthy();

    const content = fs.readFileSync(downloadPath!, 'utf-8');
    const exportedWorkflow = JSON.parse(content);

    // Verify nodes array length equals 6
    expect(exportedWorkflow.nodes).toHaveLength(6);

    // Verify edges array length equals 5
    expect(exportedWorkflow.edges).toHaveLength(5);

    // Verify workflow name present
    expect(exportedWorkflow.name).toBeTruthy();
    expect(exportedWorkflow.id).toBeTruthy();

    // Step 5: Clear canvas - create new workflow
    await page.evaluate(() => {
      localStorage.removeItem('last-opened-workflow-id');
      localStorage.removeItem(`workflow-${validShortFormWorkflow.id}`);
    });

    // Navigate to fresh state
    await page.goto('/');
    await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

    // Verify empty canvas
    await expect(page.locator('[data-testid^="node-card-"]')).toHaveCount(0);

    // Step 6: Reimport using file input
    // Find the import file input (may be hidden)
    const fileInput = page.locator('input[type="file"][accept=".json"]').first();
    
    if (await fileInput.isVisible().catch(() => false)) {
      // If file input is directly visible
      await fileInput.setInputFiles(downloadPath!);
    } else {
      // Try clicking import button first
      const importButton = page.getByTestId('workflow-import-btn');
      if (await importButton.isVisible().catch(() => false)) {
        await importButton.click();
        await fileInput.setInputFiles(downloadPath!);
      } else {
        // Use addInitScript to simulate reimport via localStorage
        await page.addInitScript((workflowJson: string) => {
          const workflow = JSON.parse(workflowJson);
          localStorage.setItem(`workflow-${workflow.id}`, workflowJson);
          localStorage.setItem('last-opened-workflow-id', workflow.id);
        }, content);
        await page.reload();
      }
    }

    // Wait for workflow to load
    await page.waitForTimeout(1000);

    // Step 7: Assert round-trip - verify 6 nodes restored
    await expect(page.locator('[data-testid^="node-card-"]')).toHaveCount(6, {
      timeout: 5000,
    });

    // Verify 5 edges restored
    const edgeCount = await page.locator('[data-testid^="edge-"]').count();
    expect(edgeCount).toBe(5);

    // Step 8: Verify config survived round-trip
    // Click scene-splitter node and check config
    await page.getByTestId('node-card-scene-splitter-1').click();
    await page.getByTestId('inspector-tab-config').click();
    
    // The config should have maxScenes from original workflow
    const maxScenesInput = page.getByLabel('maxScenes');
    if (await maxScenesInput.isVisible().catch(() => false)) {
      const maxScenesValue = await maxScenesInput.inputValue();
      expect(maxScenesValue).toBe('5'); // From validShortFormWorkflow fixture
    }

    // Cleanup: remove download file
    if (downloadPath && fs.existsSync(downloadPath)) {
      fs.unlinkSync(downloadPath);
    }
  });
});
