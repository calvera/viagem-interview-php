import { defineConfig, devices } from '@playwright/test';

/**
 * The application runs in Docker (`docker compose up -d`), so there is no webServer.
 * The owners list is slow (seconds per page), hence the generous timeouts.
 */
export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 60_000 },
  workers: 2,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    ...devices['Desktop Chrome'],
    navigationTimeout: 60_000,
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'php74', use: { baseURL: process.env.PHP74_URL ?? 'http://localhost:8074' } },
    { name: 'php85', use: { baseURL: process.env.PHP85_URL ?? 'http://localhost:8085' } },
  ],
});
