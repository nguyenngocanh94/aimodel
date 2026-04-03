import type { Page } from '@playwright/test';

export class AppShellPage {
  constructor(private readonly page: Page) {}

  async waitUntilReady(): Promise<void> {
    await this.page.waitForSelector('[data-testid="canvas-empty-cta"]', {
      timeout: 10000,
    });
  }

  async detectDialogs(): Promise<{
    recovery: boolean;
    quickAdd: boolean;
    connect: boolean;
  }> {
    const recovery = await this.page.locator('[data-testid="recovery-dialog"]').isVisible().catch(() => false);
    const quickAdd = await this.page.locator('[data-testid="quick-add-dialog"]').isVisible().catch(() => false);
    const connect = await this.page.locator('[data-testid="connect-dialog"]').isVisible().catch(() => false);

    return { recovery, quickAdd, connect };
  }

  async openTemplate(name: string): Promise<void> {
    const templateCard = this.page.locator(`[data-testid="template-card-${name}"]`);
    await templateCard.click();
  }
}
