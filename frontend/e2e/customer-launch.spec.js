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
  for (const section of ['Support', 'Settings', 'Projects & approvals']) {
    const menu = page.getByRole('button', { name: 'Menu', exact: true });
    if (await menu.isVisible()) await menu.click();
    await page.getByRole('button', { name: section, exact: true }).click();
    await expect(page.getByRole('heading', { name: section, exact: true })).toBeVisible();
    await assertNoHorizontalOverflow(page);
    await page.screenshot({ path: testInfo.outputPath(`${section.toLowerCase().replaceAll(/[^a-z]+/g, '-')}.png`), fullPage: true });
  }
  await page.getByRole('button', { name: /Start a new website/i }).click();
  await expect(page.getByRole('heading', { name: /Tell us what you want to build/i })).toBeVisible();
  await page.getByLabel('Request name').fill('Mobile bakery website');
  await page.getByLabel('What are we building?').selectOption('online_store');
  await page.getByLabel('What should this website accomplish?').fill('Accept bakery orders for pickup.');
  await page.getByLabel('What does the business sell or provide?').fill('Cakes and pastries.');
  await assertNoHorizontalOverflow(page);
  await page.screenshot({ path: testInfo.outputPath('website-request-mobile-form.png'), fullPage: true });
  await page.goto('/buy');
  await assertNoHorizontalOverflow(page);
  await expect(page.getByRole('heading', { name: /Web Basics/i })).toBeVisible();
  await expect(page.getByText('$199.00', { exact: true })).toBeVisible();
  await expect(page.getByText(/\$9\.99\/month/i)).toBeVisible();
});

test('sandbox Commerce order completes with Stripe test payment', async ({ page }) => {
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
  const stripe = page.locator('iframe[title*="Secure payment input frame" i]').first().contentFrame();
  await stripe.getByLabel(/Card number/i).fill('4242424242424242');
  await stripe.getByLabel(/Expiration date/i).fill('1230');
  await stripe.getByLabel(/Security code/i).fill('123');
  const saveForFasterCheckout = stripe.getByLabel(/Save my information for faster checkout/i);
  if (await saveForFasterCheckout.isChecked().catch(() => false)) await saveForFasterCheckout.uncheck();
  await page.getByRole('button', { name: /Pay and complete purchase/i }).click();
  await expect(page).toHaveURL(/\/complete/, { timeout: 30_000 });
  await expect(page.getByRole('heading', { name: /complete|thank/i })).toBeVisible();
  await assertNoHorizontalOverflow(page);
});
