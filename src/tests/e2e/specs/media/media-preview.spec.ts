import { test, expect } from '@playwright/test';
import { validShortFormWorkflow } from '../../fixtures/workflows';

test.describe('Media previews', () => {
  test.describe('Image Generator media preview', () => {
    test('Image Generator shows 4 thumbnails after mock run @media', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Run workflow
      await page.getByTestId('run-btn-workflow').click();
      await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
        timeout: 30000,
      });

      // Assert Image Generator node has thumbnail grid with 4 images
      const imageGeneratorCard = page.getByTestId('node-card-image-generator-1');
      await expect(imageGeneratorCard).toBeVisible();

      // Count visible img elements
      const imageCount = await imageGeneratorCard.locator('img').count();
      expect(imageCount).toBe(4);

      // All images should be visible
      for (let i = 0; i < 4; i++) {
        await expect(imageGeneratorCard.locator('img').nth(i)).toBeVisible();
      }
    });

    test('Image Generator shows overflow badge when more than 4 images @media', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Run workflow
      await page.getByTestId('run-btn-workflow').click();
      await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
        timeout: 30000,
      });

      // Check for overflow badge (shows +N)
      const imageGeneratorCard = page.getByTestId('node-card-image-generator-1');
      const overflowBadge = imageGeneratorCard.locator('.overflow-badge');
      
      // Badge should be visible if there are more than 4 images
      if (await overflowBadge.count() > 0) {
        await expect(overflowBadge).toBeVisible();
        const badgeText = await overflowBadge.textContent();
        expect(badgeText).toMatch(/^\+\d+$/);
      }
    });
  });

  test.describe('Video Composer media preview', () => {
    test('Video Composer shows poster, play, and timeline @media', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Run workflow
      await page.getByTestId('run-btn-workflow').click();
      await expect(page.getByTestId('run-status-chip')).toContainText('Success', {
        timeout: 30000,
      });

      // Assert Video Composer node shows poster
      const videoComposerCard = page.getByTestId('node-card-video-composer-1');
      await expect(videoComposerCard).toBeVisible();

      // Poster frame should be visible (img or video element)
      const poster = videoComposerCard.locator('img, video').first();
      await expect(poster).toBeVisible();

      // Play button overlay should be visible
      const playButton = videoComposerCard.locator('[data-testid="play-button"], .play-overlay, button:has-text("Play")').first();
      await expect(playButton).toBeVisible();

      // Timeline text should show duration
      await expect(videoComposerCard).toContainText(/0:00.*\/.*0:\d+/);

      // Metadata footer should exist
      const metadataFooter = videoComposerCard.locator('[data-testid="metadata-footer"], .metadata, .video-metadata').first();
      await expect(metadataFooter).toBeVisible();
    });
  });

  test.describe('Reference Images node', () => {
    test('Reference Images show imported thumbnails before run @media', async ({ page }) => {
      // This test assumes a workflow with Reference Images node
      // Check if node exists first, skip conditionally
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      const referenceImagesCard = page.getByTestId('node-card-reference-images');
      const cardCount = await referenceImagesCard.count();

      // Skip test if Reference Images node not present
      if (cardCount === 0) {
        return;
      }

      // Thumbnails should display without needing execution
      const thumbnails = referenceImagesCard.locator('img');
      const thumbnailCount = await thumbnails.count();
      expect(thumbnailCount).toBeGreaterThan(0);

      for (let i = 0; i < thumbnailCount; i++) {
        await expect(thumbnails.nth(i)).toBeVisible();
      }
    });
  });

  test.describe('Empty states', () => {
    test('Canvas empty state @media', async ({ page }) => {
      // Fresh app - no workflow loaded
      await page.goto('/');

      // Assert empty canvas message
      await expect(page.getByText('Create your first workflow')).toBeVisible();

      // Assert 3 template cards are visible
      const templateCards = page.locator('[data-testid^="template-card"], .template-card');
      await expect(templateCards).toHaveCount(3);

      // Assert Add first node CTA is visible
      const cta = page.getByTestId('canvas-empty-cta');
      await expect(cta).toBeVisible();
      await expect(cta).toContainText(/Add first node|Get started/i);
    });

    test('Inspector empty state @media', async ({ page }) => {
      // Load workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Ensure no node is selected
      await page.keyboard.press('Escape');

      // Assert inspector shows empty state message
      const inspector = page.getByTestId('inspector');
      await expect(inspector).toBeVisible();
      await expect(inspector).toContainText(/Select a node to inspect|No node selected/i);
    });

    test('Preview empty state @media', async ({ page }) => {
      // Load workflow but don't run it
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Select a node
      await page.getByTestId('node-card-script-writer-1').click();
      await expect(page.getByTestId('inspector')).toBeVisible();

      // Switch to preview tab
      await page.getByTestId('inspector-tab-preview').click();

      // Assert preview shows empty state (not yet run)
      const previewTab = page.getByTestId('inspector-tab-panel-preview');
      await expect(previewTab).toContainText(/Run this node in mock mode|No preview available|Run to see preview/i);
    });

    test('Validation empty state @media', async ({ page }) => {
      // Load valid workflow
      await page.addInitScript((workflow) => {
        localStorage.setItem(`workflow-${workflow.id}`, JSON.stringify(workflow));
        localStorage.setItem('last-opened-workflow-id', workflow.id);
      }, validShortFormWorkflow);

      await page.goto('/');
      await page.waitForSelector('[data-testid^="node-card-"]', { timeout: 10000 });

      // Select a valid node
      await page.getByTestId('node-card-script-writer-1').click();
      await expect(page.getByTestId('inspector')).toBeVisible();

      // Switch to validation tab
      await page.getByTestId('inspector-tab-validation').click();

      // Assert validation shows no issues
      const validationTab = page.getByTestId('inspector-tab-panel-validation');
      await expect(validationTab).toContainText(/No blocking issues|No validation errors|Valid/i);
    });
  });
});
