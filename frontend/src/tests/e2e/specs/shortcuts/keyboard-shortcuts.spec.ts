import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';
import { dragNodeToCanvas } from '../../helpers/canvas';

// Platform detection for modifier keys
const isMac = process.platform === 'darwin';
const modifier = isMac ? 'Meta' : 'Control';

test.describe('Keyboard shortcuts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });
  });

  test.describe('save and export shortcuts', () => {
    test('Cmd+S saves workflow @shortcut', async ({ page }) => {
      // Make a change to dirty the workflow
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });

      // Verify dirty indicator appears
      await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

      // Press Cmd+S to save
      await page.keyboard.press(`${modifier}+KeyS`);

      // Assert dirty indicator clears (or save confirmation appears)
      await expect(page.getByTestId('workflow-dirty-indicator')).not.toBeVisible();
    });

    test('Cmd+Shift+E triggers export @shortcut', async ({ page }) => {
      // Wait for download event when export shortcut is pressed
      const downloadPromise = page.waitForEvent('download');

      // Press Cmd+Shift+E to export
      await page.keyboard.press(`${modifier}+Shift+KeyE`);

      // Assert download was triggered
      const download = await downloadPromise;
      expect(download.suggestedFilename()).toMatch(/\.json$/);
    });
  });

  test.describe('undo and redo shortcuts', () => {
    test('Cmd+Z undoes last change @shortcut', async ({ page }) => {
      // Add a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });

      // Verify node is present
      await expect(page.getByTestId('node-card-user-prompt-1')).toBeVisible();

      // Press Cmd+Z to undo
      await page.keyboard.press(`${modifier}+KeyZ`);

      // Assert node is removed
      await expect(page.getByTestId('node-card-user-prompt-1')).not.toBeVisible();
    });

    test('Cmd+Shift+Z redoes last undo @shortcut', async ({ page }) => {
      // Add a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });

      // Undo it
      await page.keyboard.press(`${modifier}+KeyZ`);
      await expect(page.getByTestId('node-card-user-prompt-1')).not.toBeVisible();

      // Redo with Cmd+Shift+Z
      await page.keyboard.press(`${modifier}+Shift+KeyZ`);

      // Assert node is back
      await expect(page.getByTestId('node-card-user-prompt-1')).toBeVisible();
    });

    test('Delete removes selected node @shortcut', async ({ page }) => {
      // Add a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });
      await expect(page.getByTestId('node-card-user-prompt-1')).toBeVisible();

      // Select the node
      await page.getByTestId('node-card-user-prompt-1').click();
      await expect(page.getByTestId('node-card-user-prompt-1')).toHaveAttribute('data-selected', 'true');

      // Press Delete
      await page.keyboard.press('Delete');

      // Assert node is removed
      await expect(page.getByTestId('node-card-user-prompt-1')).not.toBeVisible();
    });

    test('Backspace removes selected node @shortcut', async ({ page }) => {
      // Add a node
      await dragNodeToCanvas(page, 'Script Writer', { x: 680, y: 140 });
      await expect(page.getByTestId('node-card-script-writer-1')).toBeVisible();

      // Select the node
      await page.getByTestId('node-card-script-writer-1').click();
      await expect(page.getByTestId('node-card-script-writer-1')).toHaveAttribute('data-selected', 'true');

      // Press Backspace
      await page.keyboard.press('Backspace');

      // Assert node is removed
      await expect(page.getByTestId('node-card-script-writer-1')).not.toBeVisible();
    });
  });

  test.describe('quick-add, connect, inspect shortcuts', () => {
    test('A opens quick-add dialog @shortcut', async ({ page }) => {
      // Press A
      await page.keyboard.press('KeyA');

      // Assert quick-add dialog is visible
      await expect(page.getByTestId('quick-add-dialog')).toBeVisible();
    });

    test('C opens connect dialog @shortcut', async ({ page }) => {
      // Press C
      await page.keyboard.press('KeyC');

      // Assert connect dialog is visible
      await expect(page.getByTestId('connect-dialog')).toBeVisible();
    });

    test('Enter opens inspector for selected node @shortcut', async ({ page }) => {
      // Add and select a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });
      await page.getByTestId('node-card-user-prompt-1').click();
      await expect(page.getByTestId('node-card-user-prompt-1')).toHaveAttribute('data-selected', 'true');

      // Press Enter
      await page.keyboard.press('Enter');

      // Assert inspector is visible
      await expect(page.getByTestId('inspector')).toBeVisible();
    });
  });

  test.describe('run shortcuts', () => {
    test('R runs selected node @shortcut', async ({ page }) => {
      // Load workflow with nodes
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.reload();
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Select a node
      await page.getByTestId('node-card-script-writer-1').click();
      await expect(page.getByTestId('node-card-script-writer-1')).toHaveAttribute('data-selected', 'true');

      // Press R
      await page.keyboard.press('KeyR');

      // Assert node run was triggered
      await expect(page.getByTestId('run-status-chip')).toContainText('Running');
      await expect(page.locator('[data-running="true"]')).toHaveCount(1, { timeout: 5000 });
    });

    test('Shift+R runs entire workflow @shortcut', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.reload();
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Press Shift+R
      await page.keyboard.press('Shift+KeyR');

      // Assert workflow run was triggered
      await expect(page.getByTestId('run-status-chip')).toContainText('Running');
    });
  });

  test.describe('Space for pan mode', () => {
    test('Space enters pan mode @shortcut', async ({ page }) => {
      const canvas = page.locator('.react-flow__pane');

      // Get initial cursor
      const initialCursor = await canvas.evaluate((el) => getComputedStyle(el).cursor);

      // Press and hold Space
      await page.keyboard.press('Space');

      // Assert cursor changed to indicate pan mode
      await expect.poll(async () => {
        const cursor = await canvas.evaluate((el) => getComputedStyle(el).cursor);
        return cursor !== initialCursor;
      }).toBe(true);
    });
  });

  test.describe('Escape precedence', () => {
    test('Escape closes topmost UI first @shortcut', async ({ page }) => {
      // Open quick-add dialog with A
      await page.keyboard.press('KeyA');
      await expect(page.getByTestId('quick-add-dialog')).toBeVisible();

      // Press Escape - dialog should close
      await page.keyboard.press('Escape');
      await expect(page.getByTestId('quick-add-dialog')).not.toBeVisible();

      // Open connect dialog with C
      await page.keyboard.press('KeyC');
      await expect(page.getByTestId('connect-dialog')).toBeVisible();

      // Press Escape - dialog should close
      await page.keyboard.press('Escape');
      await expect(page.getByTestId('connect-dialog')).not.toBeVisible();

      // Add and select a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });
      await page.getByTestId('node-card-user-prompt-1').click();
      await expect(page.getByTestId('node-card-user-prompt-1')).toHaveAttribute('data-selected', 'true');

      // Press Escape - selection should clear
      await page.keyboard.press('Escape');
      await expect(page.getByTestId('node-card-user-prompt-1')).toHaveAttribute('data-selected', 'false');
    });
  });

  test.describe('shortcuts suppressed in text inputs', () => {
    test('shortcuts do not trigger when focus in text input @shortcut', async ({ page }) => {
      // Focus on node search input
      const searchInput = page.getByTestId('node-search-input');
      await searchInput.click();
      await expect(searchInput).toBeFocused();

      // Press A - quick-add should NOT open
      await page.keyboard.press('KeyA');
      await expect(page.getByTestId('quick-add-dialog')).not.toBeVisible();

      // Blur and reopen search
      await searchInput.fill('test');

      // Press Delete - no node should be deleted (search text cleared instead)
      await page.keyboard.press('Delete');
      const nodeCount = await page.locator('[data-testid^="node-card-"]').count();
      expect(nodeCount).toBe(0); // No nodes were added yet
    });

    test('shortcuts suppressed in node config inputs @shortcut', async ({ page }) => {
      // Add a node
      await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });

      // Select and open inspector
      await page.getByTestId('node-card-user-prompt-1').click();
      await page.getByTestId('inspector-tab-config').click();

      // Focus on a config input (e.g., prompt field)
      const promptInput = page.getByLabel('prompt');
      await promptInput.click();
      await expect(promptInput).toBeFocused();

      // Type 'A' - should NOT open quick-add
      await promptInput.fill('A');
      await expect(page.getByTestId('quick-add-dialog')).not.toBeVisible();

      // Press Delete - should NOT delete the node
      await page.keyboard.press('Delete');
      await expect(page.getByTestId('node-card-user-prompt-1')).toBeVisible();
    });
  });
});
