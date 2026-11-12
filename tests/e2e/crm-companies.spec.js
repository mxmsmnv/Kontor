const { test, expect } = require('@playwright/test');
const { fixture, login, monitorBrowser } = require('./helpers');

test('CRM editor creates a company and finds it by a configured field', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const runId = Date.now().toString(36);
  const companyName = `E2E Customer Company ${runId}`;
  const reference = `REFERENCE-${runId}`;
  const diagnostics = monitorBrowser(page);

  await login(page, state.journey_users.company, process.env.KONTOR_E2E_EDITOR_PASS);
  await page.goto('./kontor/company/');
  await page.locator('input[name="legal_name"]').fill(companyName);
  await page.getByLabel('E2E reference').fill(reference);
  await page.getByRole('button', { name: 'Save company' }).last().click();
  await expect(page.getByText('Company saved.')).toBeVisible();
  await expect(page.getByLabel('E2E reference')).toHaveValue(reference);

  await page.goto(`./kontor/companies/?q=${encodeURIComponent(reference)}`);
  const row = page.getByRole('row').filter({ has: page.getByRole('link', { name: companyName }) });
  await expect(page.getByRole('columnheader', { name: 'E2E reference' })).toBeVisible();
  await expect(row.getByText(reference)).toBeVisible();
  await row.getByRole('link', { name: companyName }).click();
  await expect(page.getByLabel('E2E reference')).toHaveValue(reference);
  diagnostics.assertClean('company custom field journey');
  await page.goto('./login/logout/');
});
