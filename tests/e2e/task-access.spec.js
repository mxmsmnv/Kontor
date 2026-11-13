const { test, expect } = require('@playwright/test');
const { fixture, login } = require('./helpers');

test('task audiences isolate departments and assignees while a manager can view all', async ({ browser, baseURL }, testInfo) => {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'The mutating journey runs once.');
  const state = fixture();
  const actors = state.task_scope_users;
  const password = process.env.KONTOR_E2E_EDITOR_PASS;
  const suffix = `E2E scope ${Date.now().toString(36)}`;
  const titles = {
    media: `${suffix} media`,
    production: `${suffix} production`,
    private: `${suffix} private`,
  };

  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  const urls = {};
  try {
    await login(page, actors.dispatcher.name, password);
    for (const [kind, title] of Object.entries(titles)) {
      await page.goto('./kontor/task/');
      await page.getByLabel('Task title').fill(title);
      await expect(page.getByLabel('Visibility')).toHaveValue('assignee');
      if (kind === 'private') {
        await page.getByLabel('Assignee', { exact: true }).selectOption(actors.media.id);
      } else {
        await page.getByLabel('Visibility').selectOption('department');
        await page.getByLabel('Department', { exact: true }).selectOption(state.task_department_roles[kind]);
        await page.getByLabel('Assignee', { exact: true }).selectOption(actors[kind].id);
      }
      await page.getByRole('button', { name: 'Create task' }).click();
      await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
      urls[kind] = page.url();
    }

    const expected = {
      media: ['media', 'private'],
      mediaPeer: ['media'],
      production: ['production'],
      manager: Object.keys(titles),
    };
    for (const [actor, allowed] of Object.entries(expected)) {
      await page.goto('./login/logout/');
      await login(page, actors[actor].name, password);
      await page.goto(`./kontor/tasks/?q=${encodeURIComponent(suffix)}`);
      for (const [kind, title] of Object.entries(titles)) {
        await expect(page.getByRole('link', { name: title, exact: true })).toHaveCount(allowed.includes(kind) ? 1 : 0);
      }
      for (const [kind, url] of Object.entries(urls)) {
        const result = await page.goto(url);
        if (allowed.includes(kind)) {
          expect(result.status()).toBe(200);
          await expect(page.getByRole('heading', { name: titles[kind], exact: true })).toBeVisible();
          if (actor === 'manager') {
            await expect(page.getByRole('button', { name: 'Edit task' })).toHaveCount(0);
          }
        } else {
          expect(result.status()).toBe(404);
          await expect(page.getByText(/Task not found\.$/)).toBeVisible();
          await expect(page.getByText(titles[kind], { exact: true })).toHaveCount(0);
        }
      }
    }
  } finally {
    await context.close();
  }
});
