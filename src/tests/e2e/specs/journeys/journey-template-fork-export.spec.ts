import { test, expect } from '@playwright/test';
import { AppShellPage } from '../../pages/app-shell.page';

test.describe('Journey 5: Template fork and export', () => {
  test('start from template and fork @journey @smoke', async ({ page }) => {
    // Initialize app
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();

    // Step 1: Assert empty state with template cards
    await expect(page.getByTestId('canvas-empty-cta')).toBeVisible();
    
    // Verify 3 template cards visible
    const templateCards = page.locator('[data-testid^="template-card-"]');
    await expect(templateCards).toHaveCount(3);

    // Step 2: Click Narrated Product Teaser template
    await page.getByText('Narrated Product Teaser').click();

    // Step 3: Assert 9 pre-connected nodes load
    const nodeCards = page.locator('[data-testid^="node-card-"]');
    await expect(nodeCards).toHaveCount(9, { timeout: 5000 });

    // Step 4: Remove TTS Voiceover Planner
    const ttsNode = page.getByTestId('node-card-tts-voiceover-planner-1');
    await ttsNode.click();
    await page.keyboard.press('Delete');

    // Verify node removed
    await expect(ttsNode).not.toBeVisible();

    // Step 5: Assert dirty indicator
    await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

    // Step 6: Rerun workflow
    await page.getByTestId('run-btn-workflow').click();
    await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
      timeout: 30000,
    });

    // Step 7: Export JSON
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByTestId('workflow-export-btn').click(),
    ]);

    // Step 8: Assert download
    const suggestedFilename = download.suggestedFilename();
    expect(suggestedFilename).toMatch(/\.json$/i);

    // Read and verify content
    const path = await download.path();
    expect(path).toBeTruthy();

    // Verify it's valid JSON with 8 nodes (9 minus deleted TTS)
    const content = await download.createReadStream().then(
      (stream) => new Promise<string>((resolve, reject) => {
        const chunks: Buffer[] = [];
        stream.on('data', (chunk) => chunks.push(Buffer.from(chunk)));
        stream.on('error', reject);
        stream.on('end', () => resolve(Buffer.concat(chunks).toString('utf-8')));
      })
    );

    const exportedWorkflow = JSON.parse(content);
    expect(exportedWorkflow.nodes).toHaveLength(8);
    expect(exportedWorkflow.edges.length).toBeGreaterThan(0);
    expect(exportedWorkflow.id).toBeTruthy();
    expect(exportedWorkflow.name).toBeTruthy();
  });
});
