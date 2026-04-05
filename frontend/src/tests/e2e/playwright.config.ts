import { defineConfig, devices } from '@playwright/test';

const CI = process.env.CI === 'true' || process.env.CI === '1';

export default defineConfig({
  testDir: './specs',
  timeout: 30000,
  expect: {
    timeout: 5000,
  },
  fullyParallel: true,
  retries: CI ? 2 : 0,
  workers: CI ? 1 : undefined,
  reporter: [['html', { open: 'never' }], ['list']],
  use: {
    baseURL: 'http://127.0.0.1:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 5173',
    reuseExistingServer: !CI,
    timeout: 30000,
    url: 'http://127.0.0.1:5173',
  },
});
