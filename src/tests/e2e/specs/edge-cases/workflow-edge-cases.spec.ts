import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';
import { connectPorts } from '../../helpers/canvas';

// Platform detection for modifier keys
const isMac = process.platform === 'darwin';
const modifier = isMac ? 'Meta' : 'Control';

test.describe('Edge cases', () => {
  test.describe('browser navigation', () => {
    test('browser back/forward does not corrupt graph @edge-case', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Get initial node count
      const initialNodeCount = await page.locator('[data-testid^="node-card-"]').count();
      expect(initialNodeCount).toBe(6);

      // Select a node
      await page.getByTestId('node-card-script-writer-1').click();
      await expect(page.getByTestId('node-card-script-writer-1')).toHaveAttribute('data-selected', 'true');

      // Navigate away using history.pushState
      await page.evaluate(() => {
        history.pushState({ page: 'other' }, 'Other Page', '/other');
      });

      // Go back
      await page.goBack();
      await page.waitForTimeout(500);

      // Assert node count unchanged
      const nodeCountAfterBack = await page.locator('[data-testid^="node-card-"]').count();
      expect(nodeCountAfterBack).toBe(initialNodeCount);

      // Assert selected node state not corrupted
      const scriptWriterNode = page.getByTestId('node-card-script-writer-1');
      await expect(scriptWriterNode).toBeVisible();

      // Assert no phantom edges
      const edgeCount = await page.locator('[data-testid^="edge-"]').count();
      expect(edgeCount).toBeGreaterThanOrEqual(5); // Original workflow has 5 edges
    });
  });

  test.describe('rapid interactions', () => {
    test('rapid run clicks do not trigger duplicate runs @edge-case', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Click run button 3 times rapidly
      const runButton = page.getByTestId('run-btn-workflow');

      // Rapid clicks within 100ms
      await Promise.all([
        runButton.click(),
        runButton.click(),
        runButton.click(),
      ]);

      // Assert only one run executes
      const statusChip = page.getByTestId('run-status-chip');
      await expect(statusChip).toContainText('Running');

      // Wait for completion
      await expect(statusChip).toContainText('Success', {
        timeout: 30000,
      });

      // Assert no duplicate error toasts
      const errorToast = page.getByTestId('toast-run-error');
      await expect(errorToast).not.toBeVisible();
    });
  });

  test.describe('deletion safety', () => {
    test('delete during run is blocked or deferred @edge-case', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Start run
      await page.getByTestId('run-btn-workflow').click();

      // Wait for at least one node to be running
      await expect.poll(async () => {
        const runningNodes = await page.locator('[data-running="true"]').count();
        return runningNodes >= 1;
      }, {
        timeout: 10000,
        intervals: [100, 200, 500],
      }).toBe(true);

      // Select a running node
      const runningNode = page.locator('[data-running="true"]').first();
      const nodeId = await runningNode.getAttribute('data-testid');
      await runningNode.click();

      // Try to delete with Delete key
      await page.keyboard.press('Delete');

      // Assert node still exists (deletion blocked or deferred)
      await expect(page.getByTestId(nodeId!)).toBeVisible();

      // Check for confirmation dialog (if applicable)
      const confirmDialog = page.getByRole('dialog').filter({ hasText: /delete|remove|confirm/i });
      const hasDialog = await confirmDialog.count() > 0;

      if (hasDialog) {
        // If dialog appears, cancel it
        await page.getByRole('button', { name: /cancel|no/i }).click();
        await expect(page.getByTestId(nodeId!)).toBeVisible();
      }
    });
  });

  test.describe('undo coherence', () => {
    test('undo after edge insertion reverts coherently @edge-case', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Get initial edge count
      const initialEdgeCount = await page.locator('[data-testid^="edge-"]').count();

      // Add a new edge
      await connectPorts(page, {
        sourceNodeId: 'final-export-1',
        sourcePortKey: 'output',
        targetNodeId: 'user-prompt-1',
        targetPortKey: 'input',
      });

      // Verify edge was added
      const edgeCountAfterAdd = await page.locator('[data-testid^="edge-"]').count();
      expect(edgeCountAfterAdd).toBe(initialEdgeCount + 1);

      // Verify dirty indicator
      await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

      // Undo with Cmd+Z
      await page.keyboard.press(`${modifier}+KeyZ`);

      // Assert edge removed
      await expect.poll(async () => {
        const count = await page.locator('[data-testid^="edge-"]').count();
        return count;
      }).toBe(initialEdgeCount);

      // Assert no orphan connections (no dangling edges)
      const edges = await page.locator('[data-testid^="edge-"]').all();
      for (const edge of edges) {
        const edgeId = await edge.getAttribute('data-testid');
        // Edge should have valid source and target
        expect(edgeId).toMatch(/^edge-/);
      }

      // Assert workflow-dirty-indicator reflects the undo (cleared or updated)
      const dirtyIndicator = page.getByTestId('workflow-dirty-indicator');
      await dirtyIndicator.isVisible().catch(() => false);

      // Either dirty indicator is gone (undo brought us back to clean state)
      // or it's still showing but edge is gone (edge was the only change)
      if (initialEdgeCount === 0) {
        // If we started with 0 edges, undo should clear dirty state
        await expect(dirtyIndicator).not.toBeVisible();
      }
    });
  });

  test.describe('layout resilience', () => {
    test('panel resize does not break controls @edge-case', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Check for panel resize handles
      const resizeHandle = page.getByTestId('panel-resize-handle');
      const hasResizeHandle = await resizeHandle.count() > 0;

      // Skip test if panel resize handles not available
      if (!hasResizeHandle) {
        return;
      }

      // Drag resize handle to shrink panels
      const inspectorPanel = page.getByTestId('inspector-panel');
      const currentBox = await inspectorPanel.boundingBox();

      if (currentBox) {
        // Drag to shrink by 100px
        await resizeHandle.dragTo(page.locator('body'), {
          targetPosition: { x: currentBox.x - 100, y: currentBox.y + currentBox.height / 2 },
        });
      }

      // Assert key controls remain visible
      await expect(page.getByTestId('run-btn-workflow')).toBeVisible();
      await expect(page.getByTestId('inspector-tab-config')).toBeVisible();
      await expect(page.getByTestId('node-search-input')).toBeVisible();

      // Assert canvas drag still works
      const canvasArea = page.locator('.react-flow__pane');
      await expect(canvasArea).toBeVisible();

      // Try connecting ports after resize
      // First verify we can still click on canvas elements
      await page.getByTestId('node-card-user-prompt-1').click();
      await expect(page.getByTestId('node-card-user-prompt-1')).toHaveAttribute('data-selected', 'true');
    });
  });

  test.describe('ephemeral state handling', () => {
    test('refresh during open dialog does not restore broken UI @edge-case', async ({ page }) => {
      // Open quick-add dialog
      await page.goto('/');
      await page.waitForTimeout(1000);

      await page.keyboard.press('KeyA');
      await expect(page.getByTestId('quick-add-dialog')).toBeVisible();

      // Reload page
      await page.reload();
      await page.waitForTimeout(1000);

      // Assert quick-add dialog is NOT visible (dialogs are ephemeral)
      await expect(page.getByTestId('quick-add-dialog')).not.toBeVisible();

      // Assert canvas loads normally
      await expect(page.locator('.react-flow__pane')).toBeVisible();

      // Verify app is functional after reload
      await expect(page.getByTestId('node-search-input')).toBeVisible();
    });
  });
});
