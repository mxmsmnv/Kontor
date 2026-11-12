const { test, expect } = require('@playwright/test');
const { fixture, login, monitorBrowser } = require('./helpers');

test('staff completes and reopens a task from the Closed list', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const title = `E2E Reopen Task ${Date.now().toString(36)}`;
  const diagnostics = monitorBrowser(page);

  await login(page, state.journey_users.tasks, process.env.KONTOR_E2E_EDITOR_PASS);
  await page.goto('./kontor/task/');
  await page.getByLabel('Task title').fill(title);
  await page.getByRole('button', { name: 'Create task' }).click();
  await expect(page.getByText('Task created.')).toBeVisible();
  await page.getByRole('button', { name: /Complete$/ }).click();
  await expect(page.getByRole('button', { name: 'Reopen task' })).toBeVisible();
  await page.goto('./kontor/tasks/?scope=closed');
  await page.getByRole('link', { name: title, exact: true }).click();
  await page.getByRole('button', { name: 'Reopen task' }).click();
  await expect(page.getByText('Task reopened.')).toBeVisible();
  await expect(page.getByRole('button', { name: /Complete$/ })).toBeVisible();
  diagnostics.assertClean('task reopen journey');
  await page.goto('./login/logout/');
});

test('staff adds and reorders widgets on their own dashboard', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const diagnostics = monitorBrowser(page);
  await login(page, state.journey_users.dashboard, process.env.KONTOR_E2E_EDITOR_PASS);
  await page.goto('./kontor/');

  const create = page.getByRole('button', { name: 'Create dashboard' });
  if (await create.isVisible()) {
    await create.click();
    await expect(page.getByText('Personal dashboard created.')).toBeVisible();
  }
  const addForm = page.locator('#kontor-add-widget');
  const addWidget = page.getByRole('button', { name: 'Add widget' }).and(page.locator('[uk-toggle]'));
  const grid = page.locator('.uk-grid-match').filter({ has: page.getByRole('button', { name: 'Widget settings' }) });

  // Keep re-runs deterministic when the local acceptance database persists.
  while (await grid.getByRole('button', { name: 'Widget settings' }).count() > 0) {
    await grid.getByRole('button', { name: 'Widget settings' }).first().click();
    await grid.getByRole('button', { name: 'Remove widget' }).filter({ visible: true }).click();
    await expect(page.getByText('Dashboard layout updated.')).toBeVisible();
  }
  for (const widget of ['welcome', 'tasks.my_open']) {
    await addWidget.click();
    await addForm.getByLabel('Widget', { exact: true }).selectOption(widget);
    await addForm.getByRole('button', { name: 'Add widget' }).click();
    await expect(page.getByText('Dashboard layout updated.')).toBeVisible();
  }

  const widgets = grid.locator(':scope > div > article');
  await expect(widgets).toHaveCount(2);
  const firstTitle = await widgets.first().getByRole('heading').textContent();
  const secondTitle = await widgets.last().getByRole('heading').textContent();
  await widgets.last().getByRole('button', { name: 'Widget settings' }).click();
  await widgets.last().getByRole('button', { name: 'Earlier' }).click();
  await expect(widgets.first().getByRole('heading')).toHaveText(secondTitle);
  await expect(widgets.last().getByRole('heading')).toHaveText(firstTitle);
  diagnostics.assertClean('dashboard widget journey');
  await page.goto('./login/logout/');
});
