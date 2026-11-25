import { defineConfig, devices } from '@playwright/test';

/**
 * Smoke tests de DetallesTereTS. No necesitan SQL Server: cubren portada,
 * login, CSRF, assets y manuales. Las pruebas que sí requieren BD están en
 * Requerimientos/SMOKING.md como checklist manual.
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:3100',
    trace: 'retain-on-failure',
    ...devices['Desktop Chrome'],
  },
  webServer: {
    command: 'npm run dev',
    url: 'http://127.0.0.1:3100',
    env: { PORT: '3100' },
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
