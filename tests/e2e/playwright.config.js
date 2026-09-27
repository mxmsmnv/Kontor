const { defineConfig, devices } = require('@playwright/test');
const path = require('path');

const artifacts = process.env.KONTOR_E2E_ARTIFACTS || path.join(__dirname, '../../artifacts/e2e');

module.exports = defineConfig({
  testDir: __dirname,
  testMatch: '**/*.spec.js',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 45000,
  expect: { timeout: 10000 },
  outputDir: path.join(artifacts, 'results'),
  reporter: [
    ['list'],
    ['json', { outputFile: path.join(artifacts, 'playwright.json') }],
    ['html', { outputFolder: path.join(artifacts, 'html'), open: 'never' }],
  ],
  use: {
    baseURL: process.env.KONTOR_E2E_BASE_URL || 'http://127.0.0.1:8767/processwire/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'chromium-mobile', use: { ...devices['Pixel 7'] } },
    { name: 'firefox-desktop', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit-mobile', use: { ...devices['iPhone 15'] } },
  ],
});
