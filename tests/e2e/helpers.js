const fs = require('fs');
const AxeBuilder = require('@axe-core/playwright').default;

function fixture() {
  return JSON.parse(fs.readFileSync(process.env.KONTOR_E2E_STATE, 'utf8'));
}

function monitorBrowser(page) {
  const failures = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      failures.push(`console: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => failures.push(`page: ${error.message}`));
  page.on('requestfailed', (request) => {
    failures.push(`request: ${request.method()} ${request.url()} (${request.failure()?.errorText || 'failed'})`);
  });
  page.on('response', (response) => {
    if (response.status() >= 400) {
      failures.push(`response: ${response.status()} ${response.request().method()} ${response.url()}`);
    }
  });

  return {
    assertClean(label) {
      if (failures.length > 0) {
        throw new Error(`${label}: browser diagnostics failed:\n${failures.join('\n')}`);
      }
    },
  };
}

async function login(page, user, password) {
  await page.goto('./');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Login' }).click();
  await page.waitForURL(/\/processwire\/page\/\?login=1$/);
}

async function assertAccessible(page, label) {
  const result = await new AxeBuilder({ page }).include('.kontor-shell').analyze();
  const violations = result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact));
  if (violations.length > 0) {
    throw new Error(`${label}: ${violations.map((violation) => {
      const targets = violation.nodes.slice(0, 3).flatMap((node) => node.target).join(', ');
      return `${violation.id} (${violation.nodes.length}: ${targets})`;
    }).join(', ')}`);
  }
}

async function assertResponsive(page, label) {
  const overflow = await page.evaluate(() => Math.max(
    0,
    document.documentElement.scrollWidth - document.documentElement.clientWidth,
  ));
  if (overflow > 2) {
    throw new Error(`${label}: horizontal overflow ${overflow}px`);
  }
}

module.exports = { fixture, login, monitorBrowser, assertAccessible, assertResponsive };
