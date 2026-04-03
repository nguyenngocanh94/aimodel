import type { Page } from '@playwright/test';

export type InspectorTab = 'config' | 'preview' | 'data' | 'validation' | 'meta';

export class InspectorPage {
  constructor(private readonly page: Page) {}

  async switchTab(tabName: InspectorTab): Promise<void> {
    const tab = this.page.getByTestId(`inspector-tab-${tabName}`);
    await tab.click();
  }

  async editConfig(label: string, value: string): Promise<void> {
    const input = this.page.getByLabel(label);
    await input.fill(value);
  }

  async assertPayload(expected: Record<string, unknown>): Promise<void> {
    const dataTab = this.page.getByTestId('inspector-tab-data');
    await dataTab.click();

    const payloadContent = this.page.locator('[data-testid="inspector-payload-content"]');
    const text = await payloadContent.textContent();
    const parsed = text ? JSON.parse(text) : null;
    
    if (JSON.stringify(parsed) !== JSON.stringify(expected)) {
      throw new Error(
        `Payload mismatch. Expected: ${JSON.stringify(expected)}, Got: ${JSON.stringify(parsed)}`
      );
    }
  }

  async assertEmpty(): Promise<void> {
    const emptyMessage = this.page.getByText('Select a node to inspect');
    await emptyMessage.waitFor({ state: 'visible' });
  }
}
