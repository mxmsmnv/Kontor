const { randomUUID } = require('node:crypto');
const { test, expect } = require('@playwright/test');
const { fixture, login, monitorBrowser } = require('./helpers');

test('staff issues a quote, bills the order, settles the invoice and verifies portal access', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const suffix = randomUUID().slice(0, 8);
  const item = `E2E Advisory ${suffix}`;
  const portalEmail = `customer-${suffix}@example.test`;
  const portalPassword = `E2E-Customer-${suffix}!`;
  const diagnostics = monitorBrowser(page);
  const confirmations = [];
  page.on('dialog', (dialog) => {
    confirmations.push(dialog.message());
    return dialog.accept();
  });

  await login(page, state.journey_users.commerce, process.env.KONTOR_E2E_EDITOR_PASS);
  await page.goto('./kontor/sales/');
  await page.getByRole('link', { name: /New quotation$/ }).click();
  await page.getByLabel('Customer', { exact: true }).selectOption(`contact:${state.commerce_contact_uid}`);
  await page.getByLabel('Item or service').fill(item);
  await page.getByLabel('Quantity').fill('2');
  await page.getByLabel('Unit', { exact: true }).fill('pcs');
  await page.getByLabel('Unit price').fill('50.00');
  await page.getByLabel('Tax rate').fill('0');
  await page.getByLabel('Currency').fill('USD');
  await page.getByRole('button', { name: /Create draft$/ }).click();
  await expect(page.getByText('Quotation draft created.')).toBeVisible();
  await page.getByRole('button', { name: /Issue quotation$/ }).click();
  await expect(page.getByText(/Quotation issued with an immutable snapshot/)).toBeVisible();
  expect(confirmations).toContain('Issue this quotation and lock its customer PDF?');
  const quotationNumber = (await page.locator('.kontor-pagehead h2').innerText()).trim();
  await page.getByRole('button', { name: /Record acceptance$/ }).click();
  await page.getByRole('button', { name: /Create sales order$/ }).click();
  await expect(page.getByText('Sales order created.')).toBeVisible();
  await page.getByRole('button', { name: /Confirm order$/ }).click();
  await page.getByRole('button', { name: /Create invoice draft$/ }).click();
  await expect(page.getByText('Invoice draft created from sales order.')).toBeVisible();
  await expect(page.getByRole('cell', { name: item, exact: true })).toBeVisible();
  await page.getByRole('button', { name: /Issue invoice$/ }).click();
  await expect(page.getByText(/Invoice issued with an immutable snapshot/)).toBeVisible();
  expect(confirmations).toContain('Issue this invoice and create its final PDF?');
  const invoiceNumber = (await page.locator('.kontor-pagehead h2').innerText()).trim();
  const invoiceUrl = page.url();
  await page.getByRole('button', { name: /Send invoice$/ }).click();
  const send = page.locator('#invoice-send');
  await send.getByLabel('From mailbox').selectOption({ label: 'E2E Billing · kontor-e2e-billing@example.test' });
  await expect(send.getByRole('checkbox')).toBeChecked();
  await send.getByRole('button', { name: /Send invoice$/ }).click();
  await expect(page.getByText(/Invoice delivery simulated/)).toBeVisible();

  await page.getByRole('button', { name: /Record payment$/ }).click();
  const payment = page.locator('#invoice-payment');
  await expect(payment.getByLabel('Amount')).toHaveValue('100.00');
  await payment.getByLabel('Reference').fill(`E2E-${suffix}`);
  await payment.getByRole('button', { name: /Record payment$/ }).click();
  await expect(page.getByText(/Payment recorded.*allocated/)).toBeVisible();
  const paymentNumber = (await page.locator('.kontor-pagehead h2').innerText()).trim();
  await page.goto(invoiceUrl);
  await expect(page.getByRole('heading', { name: 'Balance settled' })).toBeVisible();
  await expect(page.getByText(paymentNumber, { exact: true }).filter({ visible: true }).first()).toBeVisible();

  await page.goto('./kontor/portal/');
  await page.getByRole('button', { name: /Create access$/ }).filter({ visible: true }).click();
  const access = page.locator('#kontor-portal-account-modal');
  await access.getByLabel('Customer contact').selectOption(state.commerce_contact_uid);
  await access.getByLabel('Login email').fill(portalEmail);
  await access.getByLabel('Temporary password').fill(portalPassword);
  await access.getByRole('button', { name: /Create access$/ }).click();
  await expect(page.getByText('Portal account created.')).toBeVisible();
  await expect(page.getByRole('row').filter({ hasText: quotationNumber })).toBeVisible();
  await expect(page.getByRole('row').filter({ hasText: invoiceNumber })).toContainText(paymentNumber);
  await page.getByLabel('Phone', { exact: true }).fill('+1 555 0100');
  await page.getByRole('button', { name: /Save profile$/ }).click();
  await expect(page.getByLabel('Phone', { exact: true })).toHaveValue('+1 555 0100');

  for (const signInMessage of ['Sign-in works.', 'Sign-in failed.']) {
    await page.getByRole('link', { name: /All accounts/ }).click();
    await page.getByRole('button', { name: 'Help a customer sign in' }).click();
    await page.getByLabel('Login email', { exact: true }).filter({ visible: true }).fill(portalEmail);
    await page.getByLabel('Password', { exact: true }).fill(portalPassword);
    await page.getByRole('button', { name: /Test sign-in$/ }).click();
    await expect(page.getByText(signInMessage, { exact: true })).toBeVisible();
    if (signInMessage === 'Sign-in works.') {
      await page.getByRole('button', { name: /Pause access$/ }).click();
      await expect(page.getByText('Portal account disabled.')).toBeVisible();
    }
  }
  expect(confirmations).toContain('Pause this customer’s portal access?');
  diagnostics.assertClean('commerce and portal administration journey');
});
