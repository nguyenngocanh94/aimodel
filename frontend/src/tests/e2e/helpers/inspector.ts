import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

export type InspectorTab = 'config' | 'preview' | 'data' | 'validation' | 'meta';

export async function selectInspectorTab(
  page: Page,
  tabName: InspectorTab
): Promise<void> {
  const tab = page.getByTestId(`inspector-tab-${tabName}`);
  await tab.click();
}

export async function verifyPayload(
  page: Page,
  expected: Record<string, unknown>
): Promise<void> {
  await selectInspectorTab(page, 'data');

  const payloadContent = page.locator('[data-testid="inspector-payload-content"]');
  const text = await payloadContent.textContent();
  const parsed = text ? JSON.parse(text) : null;

  expect(parsed).toEqual(expected);
}

export async function inspectEdge(
  page: Page,
  edgeId: string
): Promise<void> {
  const edge = page.getByTestId(`edge-${edgeId}`);
  await edge.click();
  await selectInspectorTab(page, 'data');
}
