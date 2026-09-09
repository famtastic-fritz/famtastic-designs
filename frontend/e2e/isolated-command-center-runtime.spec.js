import { expect, test } from '@playwright/test';

const ADMIN = process.env.FAMTASTIC_RUNTIME_BACKEND_URL || 'http://127.0.0.1:18081';

async function noHorizontalOverflow(page) {
  const size = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }));
  expect(size.content, `horizontal overflow: ${JSON.stringify(size)}`).toBeLessThanOrEqual(size.viewport + 1);
}

function luminance([red, green, blue]) {
  const linear = [red, green, blue].map(value => {
    const channel = value / 255;
    return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
}

function contrastRatio(foreground, background) {
  const parse = color => (color.match(/\d+(?:\.\d+)?/g) || []).slice(0, 3).map(Number);
  const first = luminance(parse(foreground));
  const second = luminance(parse(background));
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
}

async function signIn(page, username = 'admin', password = 'admin') {
  await page.goto(`${ADMIN}/user/login`);
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page).toHaveURL(/user\/\d+/);
}

test('fresh Drupal runtime renders branded login/reset and protects staff routes', async ({ page }, testInfo) => {
  for (const width of [390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(`${ADMIN}/user/login`);
    await expect(page.getByLabel('Username')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(page.locator('link[href*="famtastic_admin"]')).toHaveCount(1);
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`drupal-login-${width}.png`), fullPage: true });
  }

  await page.goto(`${ADMIN}/user/password`);
  await expect(page.getByLabel('Username or email address')).toBeVisible();
  await expect(page.locator('link[href*="famtastic_admin"]')).toHaveCount(1);
  await noHorizontalOverflow(page);

  await page.goto(`${ADMIN}/admin/famtastic`);
  await expect(page).toHaveURL(/user\/login/);
});

test('native forms, tables, status states, focus, and custom operations render in the FAMtastic theme', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await signIn(page);
  await page.goto(`${ADMIN}/admin/famtastic`);
  await expect(page.getByRole('heading', { name: 'FAMtastic Operations' })).toBeVisible();
  await expect(page.locator('body')).toHaveClass(/famtastic-admin-shell/);
  await expect(page.locator('a[href="#"]')).toHaveCount(0);
  await noHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('operations-1280.png'), fullPage: true });

  await page.goto(`${ADMIN}/admin/content`);
  await expect(page.locator('table')).toBeVisible();
  await expect(page.locator('th')).not.toHaveCount(0);
  await noHorizontalOverflow(page);

  await page.goto(`${ADMIN}/node/add/article`);
  await expect(page.getByLabel('Title')).toBeVisible();
  await page.getByLabel('Title').focus();
  await expect(page.getByLabel('Title')).toBeFocused();
  await expect(page.getByLabel('Title')).toHaveCSS('min-height', /4[4-9]px|[5-9]\dpx/);
  await page.screenshot({ path: testInfo.outputPath('core-form-1280.png'), fullPage: true });

  await page.goto(`${ADMIN}/admin/famtastic/settings`);
  await expect(page.getByRole('heading', { name: 'FAMtastic notification settings' })).toBeVisible();
  await expect(page.locator('form')).toBeVisible();
  await noHorizontalOverflow(page);

  await page.goto(`${ADMIN}/user/login`);
  await page.getByLabel('Username').fill('not-a-user');
  await page.getByLabel('Password').fill('wrong-password');
  await page.getByRole('button', { name: 'Log in' }).click();
  await expect(page.getByRole('alert')).toBeVisible();
});

test('customer portal mocked runtime keeps reachable actions, focus, touch sizing, and 390/768/1280 containment', async ({ page }, testInfo) => {
  const workspace = {
    organization: { public_id: 'runtime-org', name: 'Runtime Studio', role: 'owner' },
    organizations: [], projects: [], orders: [], entitlements: [], website_requests: [],
    threads: [], activity: [], members: [], referrals: [], articles: [], faqs: [], offers: [],
    analytics: { entitled: false }, preferences: { project_email: true, support_email: true, billing_email: true, product_education: true, deals_promotions: true, analytics_digest: 'monthly', topics: [] }, topics: {},
  };
  await page.route('**/api/customer/session', route => route.fulfill({ json: { customer: { display_name: 'Runtime Operator', email: 'runtime@example.test' } } }));
  await page.route('**/api/customer/catalog', route => route.fulfill({ json: { products: [] } }));
  await page.route('**/api/customer/workspace*', route => route.fulfill({ json: workspace }));

  for (const width of [390, 768, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/portal');
    await expect(page.getByRole('button', { name: 'Open Projects', exact: true })).toBeVisible();
    const target = page.getByRole('button', { name: 'Open Projects', exact: true });
    const box = await target.boundingBox();
    expect(box?.height).toBeGreaterThanOrEqual(44);
    const colors = await target.evaluate(element => {
      const style = getComputedStyle(element);
      return { foreground: style.color, background: style.backgroundColor };
    });
    expect(contrastRatio(colors.foreground, colors.background)).toBeGreaterThanOrEqual(4.5);
    await target.focus();
    await expect(target).toBeFocused();
    await noHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`portal-${width}.png`), fullPage: true });
  }
});
