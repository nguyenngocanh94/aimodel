import { test, expect } from '@playwright/test';
import { emptyWorkflow } from '../../fixtures/workflows';
import { dragNodeToCanvas, connectPorts } from '../../helpers/canvas';

test.describe('Scenarios 2-3: Incompatible connection and adapter insertion', () => {
  test.describe('Scenario 2: reject an incompatible connection', () => {
    test('reject an incompatible connection @scenario', async ({ page }) => {
      // Step 1: Seed empty workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, emptyWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

      // Step 2: Drag Script Writer
      await dragNodeToCanvas(page, 'Script Writer', { x: 600, y: 180 });

      // Step 3: Drag Video Composer
      await dragNodeToCanvas(page, 'Video Composer', { x: 980, y: 180 });

      // Step 4: Connect incompatible ports (script -> assets)
      await connectPorts(page, {
        sourceNodeId: 'script-writer-1',
        sourcePortKey: 'script',
        targetNodeId: 'video-composer-1',
        targetPortKey: 'assets',
      });

      // Step 5: Assert edge is invalid
      const invalidEdge = page.getByTestId('edge-script-writer-1__video-composer-1');
      await expect(invalidEdge).toHaveAttribute('data-invalid', 'true');

      // Step 6: Click edge and open validation tab
      await invalidEdge.click();
      await page.getByTestId('inspector-tab-validation').click();

      // Step 7: Assert compatibility result shows invalid
      await expect(page.getByText('Compatibility result')).toBeVisible();
      await expect(page.getByText('invalid')).toBeVisible();
    });
  });

  test.describe('Scenario 3: insert adapter node to fix connection', () => {
    test('insert adapter node to fix connection @scenario', async ({ page }) => {
      // Step 1: Start from Scenario 2 state
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, emptyWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid="canvas-empty-cta"]', { timeout: 10000 });

      // Setup: Create invalid connection first
      await dragNodeToCanvas(page, 'Script Writer', { x: 600, y: 180 });
      await dragNodeToCanvas(page, 'Video Composer', { x: 980, y: 180 });
      await connectPorts(page, {
        sourceNodeId: 'script-writer-1',
        sourcePortKey: 'script',
        targetNodeId: 'video-composer-1',
        targetPortKey: 'assets',
      });

      // Verify invalid edge exists
      const invalidEdge = page.getByTestId('edge-script-writer-1__video-composer-1');
      await expect(invalidEdge).toHaveAttribute('data-invalid', 'true');

      // Select edge and open validation tab
      await invalidEdge.click();
      await page.getByTestId('inspector-tab-validation').click();

      // Step 2: Click quick action to Insert Image Asset Mapper
      const insertButton = page.getByRole('button', { name: /insert image asset mapper/i });
      await insertButton.click();

      // Step 3: Assert adapter node appears
      const adapterNode = page.getByTestId('node-card-image-asset-mapper-1');
      await expect(adapterNode).toBeVisible();

      // Step 4: Assert old invalid edge removed
      await expect(invalidEdge).not.toBeVisible();

      // Step 5: Assert two new valid edges exist
      const edgeCount = await page.locator('[data-testid^="edge-"]').count();
      expect(edgeCount).toBeGreaterThanOrEqual(2);

      // Step 6: Assert no data-invalid edges remain
      const invalidEdges = page.locator('[data-invalid="true"]');
      await expect(invalidEdges).toHaveCount(0);
    });
  });
});
