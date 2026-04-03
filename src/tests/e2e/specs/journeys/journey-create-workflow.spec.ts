import { test, expect } from '@playwright/test';
import { AppShellPage } from '../../pages/app-shell.page';
import { dragNodeToCanvas, connectPorts } from '../../helpers/canvas';
import { verifyNodeState } from '../../helpers/assertions';

test.describe('Journey 1: Create workflow from scratch', () => {
  test('create a short-form workflow from scratch @journey @smoke', async ({ page }) => {
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();
    // Step 1: Navigate and verify empty canvas state
    await expect(page.getByTestId('canvas-empty-cta')).toBeVisible();
    await expect(page.getByTestId('node-search-input')).toBeVisible();

    // Step 2: Drag 6 nodes to canvas
    await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });
    await dragNodeToCanvas(page, 'Script Writer', { x: 680, y: 140 });
    await dragNodeToCanvas(page, 'Scene Splitter', { x: 940, y: 140 });
    await dragNodeToCanvas(page, 'Image Generator', { x: 1200, y: 140 });
    await dragNodeToCanvas(page, 'Video Composer', { x: 1460, y: 140 });
    await dragNodeToCanvas(page, 'Final Export', { x: 1720, y: 140 });

    // Verify empty CTA disappears after first node
    await expect(page.getByTestId('canvas-empty-cta')).not.toBeVisible();

    // Step 3: Connect 5 edges
    await connectPorts(page, {
      sourceNodeId: 'user-prompt-1',
      sourcePortKey: 'prompt',
      targetNodeId: 'script-writer-1',
      targetPortKey: 'prompt',
    });
    await connectPorts(page, {
      sourceNodeId: 'script-writer-1',
      sourcePortKey: 'script',
      targetNodeId: 'scene-splitter-1',
      targetPortKey: 'script',
    });
    await connectPorts(page, {
      sourceNodeId: 'scene-splitter-1',
      sourcePortKey: 'scenes',
      targetNodeId: 'image-generator-1',
      targetPortKey: 'scenes',
    });
    await connectPorts(page, {
      sourceNodeId: 'image-generator-1',
      sourcePortKey: 'images',
      targetNodeId: 'video-composer-1',
      targetPortKey: 'assets',
    });
    await connectPorts(page, {
      sourceNodeId: 'video-composer-1',
      sourcePortKey: 'video',
      targetNodeId: 'final-export-1',
      targetPortKey: 'video',
    });

    // Step 4: Configure scene-splitter node
    await page.getByTestId('node-card-scene-splitter-1').click();
    await expect(page.getByTestId('inspector')).toBeVisible();

    // Switch to config tab and set sceneCountTarget
    await page.getByTestId('inspector-tab-config').click();
    await page.getByLabel('sceneCountTarget').fill('6');

    // Switch to preview tab and verify
    await page.getByTestId('inspector-tab-preview').click();
    await expect(page.getByText('6 scenes')).toBeVisible();

    // Step 5: Run workflow
    await page.getByTestId('run-btn-workflow').click();

    // Verify run status transitions
    await expect(page.getByTestId('run-status-chip')).toContainText('Running');
    await verifyNodeState(page, 'script-writer-1', 'running');

    // Wait for success
    await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
      timeout: 30000,
    });

    // Step 6: Inspect edge data
    await page.getByTestId('edge-script-writer-1__scene-splitter-1').click();
    await page.getByTestId('inspector-tab-data').click();

    // Verify payload and schema are visible
    await expect(page.getByText('payload')).toBeVisible();
    await expect(page.getByText('schema')).toBeVisible();
  });
});
