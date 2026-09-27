const { test, expect } = require('@playwright/test');
const { fixture, login, monitorBrowser, assertAccessible, assertResponsive } = require('./helpers');

test('CRM editor qualifies a contact lead and converts it to a deal', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const runId = Date.now().toString(36);
  const title = `E2E Qualified Opportunity ${runId}`;
  const contactName = `E2E Journey Contact ${runId}`;
  const diagnostics = monitorBrowser(page);

  await login(page, state.editor_user, process.env.KONTOR_E2E_EDITOR_PASS);
  await page.goto('./kontor/contact/');
  await page.locator('input[name="display_name"]').fill(contactName);
  await page.locator('input[name="first_name"]').fill('E2E');
  await page.locator('input[name="last_name"]').fill(`Journey ${runId}`);
  await page.locator('input[name="email"]').fill(`e2e-${runId}@example.test`);
  await page.getByRole('button', { name: 'Save contact' }).click();
  await expect(page.getByText('Contact saved.')).toBeVisible();

  await page.getByRole('link', { name: 'New lead' }).click();
  await page.locator('input[name="title"]').fill(title);
  await page.locator('textarea[name="description"]').fill('Deterministic local qualification journey.');
  await page.locator('select[name="crm_intake__source_channel"]').selectOption('referral');
  await page.locator('textarea[name="crm_intake__budget_context"]').fill('Approved local test budget');
  await page.locator('input[name="estimated_amount"]').fill('25000');
  await page.getByRole('button', { name: 'Create lead' }).click();
  await expect(page.getByText('Lead saved.')).toBeVisible();

  await page.locator('select[name="status"]').selectOption('qualified');
  await page.getByRole('button', { name: 'Save changes' }).click();
  await expect(page.getByRole('button', { name: 'Convert to deal' })).toBeVisible();
  page.once('dialog', (dialog) => dialog.accept());
  await page.getByRole('button', { name: 'Convert to deal' }).click();

  await expect(page).toHaveURL(/\/kontor\/crm-deal\/\?id=/);
  await expect(page.getByText('Lead converted to a deal.')).toBeVisible();
  await expect(page.getByRole('heading', { name: title }).first()).toBeVisible();
  await expect(page.getByText('25,000.00 EUR')).toBeVisible();
  await expect(page.getByRole('link', { name: contactName })).toBeVisible();
  await expect(page.getByText('Referral')).toBeVisible();
  await assertResponsive(page, 'converted deal');
  await assertAccessible(page, 'converted deal');
  diagnostics.assertClean('qualification journey');
  await page.goto('./login/logout/');
});

test('restricted CRM role cannot convert a qualified lead', async ({ page }, testInfo) => {
  const state = fixture();
  const diagnostics = monitorBrowser(page);
  await login(page, state.restricted_users[testInfo.project.name], process.env.KONTOR_E2E_RESTRICTED_PASS);
  await page.goto(`./kontor/crm-lead/?id=${state.restricted_lead_uid}`);
  await expect(page.locator('select[name="status"]')).toHaveValue('qualified');
  await expect(page.getByRole('button', { name: 'Convert to deal' })).toHaveCount(0);
  await assertResponsive(page, 'restricted lead');
  await assertAccessible(page, 'restricted lead');
  diagnostics.assertClean('restricted lead');
  await page.goto('./login/logout/');
});
