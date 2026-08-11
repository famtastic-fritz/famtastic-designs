import { expect, test } from '@playwright/test';

const email = process.env.FAMTASTIC_E2E_CUSTOMER_EMAIL;
const password = process.env.FAMTASTIC_E2E_CUSTOMER_PASSWORD;

async function assertNoHorizontalOverflow(page) {
  await expect.poll(() => page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }))).toEqual(expect.objectContaining({ viewport: expect.any(Number) }));
  const sizes = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    content: document.documentElement.scrollWidth,
  }));
  expect(sizes.content, `horizontal overflow: ${JSON.stringify(sizes)}`).toBeLessThanOrEqual(sizes.viewport + 1);
}

async function signIn(page, redirect = '/portal') {
  test.skip(!email || !password, 'Controlled test-customer credentials are required.');
  await page.goto(`/login?redirect=${encodeURIComponent(redirect)}`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.locator('form button[type="submit"], form button:not([type])').first().click();
  await expect(page).toHaveURL(new RegExp(redirect.replace('/', '\\/')));
}

test('customer account, portal, support, settings, and purchase UI are mobile-safe', async ({ page }, testInfo) => {
  await signIn(page);
  for (const path of ['/portal', '/portal/support', '/portal/settings', '/buy']) {
    await page.goto(path);
    await expect(page.locator('body')).toBeVisible();
    await assertNoHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`${path.replaceAll('/', '-') || 'home'}.png`), fullPage: true });
  }
  await expect(page.getByRole('heading', { name: /Web Basics/i })).toBeVisible();
  await expect(page.getByText('$199.00', { exact: true })).toBeVisible();
  await expect(page.getByText(/\$9\.99\/month/i)).toBeVisible();
});

test('sandbox Commerce order reaches Stripe payment form', async ({ page }) => {
  test.skip(process.env.FAMTASTIC_RUN_SANDBOX_PAYMENT !== '1', 'Explicit sandbox-payment gate is required.');
  await signIn(page, '/buy');
  await page.getByLabel(/Connect a domain I already own/i).check();
  await page.getByLabel(/Additional revision round/i).check();
  await page.getByLabel(/I authorize basic hosting/i).check();
  await page.getByLabel(/I accept the recorded product scope/i).check();
  await page.getByRole('button', { name: /Continue to secure payment/i }).click();
  await expect(page).toHaveURL(/\/web\/checkout\/\d+\/order_information/);
  await expect(page.getByRole('heading', { name: 'Order information' })).toBeVisible();
  await assertNoHorizontalOverflow(page);
  await expect(page.getByText('$274.00', { exact: true }).first()).toBeVisible();
  await page.getByLabel('First name').fill('FAMtastic');
  await page.getByLabel('Last name').fill('Proof');
  await page.getByLabel('Company').fill('FAMtastic Launch Proof');
  await page.getByLabel('Street address', { exact: true }).fill('1729 NW St. Lucie West Blvd #1181');
  await page.getByLabel('City').fill('Port Saint Lucie');
  await page.getByLabel('State').selectOption('FL');
  await page.getByLabel(/Zip code/i).fill('34986');
  await page.getByRole('button', { name: /Continue to review/i }).click();
  await expect(page).toHaveURL(/\/review/);
  await expect(page.getByRole('heading', { name: 'Review' })).toBeVisible();
  await assertNoHorizontalOverflow(page);
  await expect(page.getByRole('button', { name: /Pay and complete purchase/i })).toBeVisible();
});
