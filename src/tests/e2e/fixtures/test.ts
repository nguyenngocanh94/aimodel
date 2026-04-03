/* eslint-disable react-hooks/rules-of-hooks */
import { test as base } from '@playwright/test';
import { AppShellPage } from '../pages/app-shell.page';

interface TestFixtures {
  app: AppShellPage;
}

export const test = base.extend<TestFixtures>({
  app: async ({ page }, use) => {
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();
    await use(app);
  },
});

export { expect } from '@playwright/test';
