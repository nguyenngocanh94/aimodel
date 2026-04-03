import type { Page } from '@playwright/test';

export class ToolbarPage {
  constructor(private readonly page: Page) {}

  async runWorkflow(): Promise<void> {
    const button = this.page.getByTestId('run-btn-workflow');
    await button.click();
  }

  async runSelectedNode(): Promise<void> {
    const button = this.page.getByTestId('run-btn-node');
    await button.click();
  }

  async runFromHere(): Promise<void> {
    const button = this.page.getByTestId('run-btn-from-here');
    await button.click();
  }

  async cancel(): Promise<void> {
    const button = this.page.getByTestId('run-btn-cancel');
    await button.click();
  }

  async save(): Promise<void> {
    const button = this.page.getByTestId('workflow-save-btn');
    await button.click();
  }

  async export(): Promise<void> {
    const button = this.page.getByTestId('workflow-export-btn');
    await button.click();
  }

  async readStatusChip(): Promise<string> {
    const chip = this.page.getByTestId('run-status-chip');
    const text = await chip.textContent();
    return text ?? '';
  }
}
