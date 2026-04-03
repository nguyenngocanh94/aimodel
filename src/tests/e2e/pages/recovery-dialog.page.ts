import type { Page } from '@playwright/test';

export class RecoveryDialogPage {
  constructor(private readonly page: Page) {}

  async assertTimestamp(): Promise<string> {
    const timestamp = this.page.locator('[data-testid="recovery-timestamp"]');
    const text = await timestamp.textContent();
    if (!text) {
      throw new Error('Recovery timestamp not found');
    }
    return text;
  }

  async assertRunBadge(): Promise<boolean> {
    const badge = this.page.locator('[data-testid="recovery-run-badge"]');
    return await badge.isVisible().catch(() => false);
  }

  async assertWorkflowName(expectedName: string): Promise<void> {
    const name = this.page.locator('[data-testid="recovery-workflow-name"]');
    const text = await name.textContent();
    if (text !== expectedName) {
      throw new Error(
        `Workflow name mismatch. Expected: ${expectedName}, Got: ${text}`
      );
    }
  }

  async clickRestore(): Promise<void> {
    const button = this.page.getByRole('button', { name: 'Restore draft' });
    await button.click();
  }

  async clickDiscard(): Promise<void> {
    const button = this.page.getByRole('button', { name: 'Discard' });
    await button.click();
  }

  async clickStartFresh(): Promise<void> {
    const button = this.page.getByRole('button', { name: 'Start fresh' });
    await button.click();
  }
}
