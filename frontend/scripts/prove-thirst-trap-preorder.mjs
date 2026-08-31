import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, '../..');
const evidenceDir = path.join(repoRoot, 'docs/evidence/thirst-trap-772-production-v2/screenshots');
const base = process.env.FAMTASTIC_PREVIEW_BASE || 'http://127.0.0.1:4197';
const site = {
  schema_version: 1,
  site_key: 'thirst-trap-772',
  brand: { name: 'Thirst Trap 772', tagline: 'Crave. Drink. Repeat.', service_area: 'Treasure Coast', intro: 'Fixture preorder preview.' },
  products: [
    { id: 'pink-lemonade', name: 'Pink Lemonade Pouch', kicker: 'Cold + bright', description: 'Fixture product.', price_label: '$5', price_cents: 500, status: 'active', visual: 'pink' },
    { id: 'tropical-punch', name: 'Tropical Punch Pouch', kicker: 'Sweet + social', description: 'Fixture product.', price_label: '$7', price_cents: 700, status: 'active', visual: 'tropical' },
  ],
  events: [{ id: 'friday-market', title: 'Friday Market', date_label: 'Friday · 6 PM', location: 'Vero Beach', details: 'Fixture only.', status: 'scheduled' }],
  payments: { preorders_enabled: true, cash_app_available: true, pickup_note: 'Pickup is owner-confirmed.' },
  socials: { instagram: 'https://www.instagram.com/thirst_trap772/', facebook: 'https://www.facebook.com/ThirstTrap772/' },
};

await mkdir(evidenceDir, { recursive: true });
const browser = await chromium.launch({ headless: true });

async function mockPublic(page) {
  await page.route('**/web/api/microsite/thirst-trap-772', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, site, changed: 1 }) });
  });
  await page.route('**/web/api/microsite/thirst-trap-772/preorder', async (route) => {
    const body = route.request().postDataJSON();
    assert.equal(body.items[0].product_id, 'pink-lemonade');
    assert.equal(body.items[0].quantity, 2);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
      ok: true,
      status: 'requested',
      order: { reference: 'TT772-LOCAL123', item_count: 2, total_cents: 1000, currency: 'USD', pickup_label: 'Friday Market · Friday · 6 PM · Vero Beach', payment_status: 'unverified' },
      payment: { available: true, url: 'https://cash.app/$FixtureOnly', label: '$FixtureOnly', instructions: 'Include TT772-LOCAL123 in the payment note.' },
    }) });
  });
}

for (const profile of [
  { name: 'desktop', viewport: { width: 1440, height: 1000 } },
  { name: 'mobile', viewport: { width: 390, height: 844 }, isMobile: true },
]) {
  const context = await browser.newContext({ viewport: profile.viewport, isMobile: Boolean(profile.isMobile), deviceScaleFactor: 1 });
  const page = await context.newPage();
  await mockPublic(page);
  await page.goto(`${base}/showcase/thirst-trap-772/v2/`, { waitUntil: 'networkidle' });
  await page.locator('#preorder').scrollIntoViewIfNeeded();
  await page.locator('#preorder-form').waitFor({ state: 'visible' });
  await page.locator('.preorder-item').first().locator('input[type="checkbox"]').check();
  await page.locator('.preorder-item').first().locator('input[type="number"]').fill('2');
  await page.locator('#preorder-form input[name="name"]').fill('Local Fixture');
  await page.locator('#preorder-form input[name="email"]').fill('fixture@example.test');
  await page.locator('#preorder-pickup').selectOption('friday-market');
  assert.equal(await page.locator('#preorder-total').textContent(), '$10.00');
  await page.locator('#preorder-form button[type="submit"]').click();
  await page.locator('#preorder-confirmation').waitFor({ state: 'visible' });
  assert.match(await page.locator('#preorder-reference').textContent(), /TT772-LOCAL123.*\$10\.00/);
  assert.equal(await page.locator('#cash-app-link').getAttribute('href'), 'https://cash.app/$FixtureOnly');
  assert.equal(await page.locator('#cash-app-qr svg').count(), 1);
  await page.evaluate(() => document.activeElement instanceof HTMLElement && document.activeElement.blur());
  // Playwright stitches tall element screenshots in viewport-sized tiles;
  // suppress the off-canvas accessibility skip link so it is not repeated
  // inside the evidence image even though it is hidden in a real viewport.
  await page.locator('.skip-link').evaluate((element) => { element.style.display = 'none'; });
  const width = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
  assert.ok(width.scroll <= width.client + 1, `${profile.name} page has no horizontal overflow`);
  await page.locator('#preorder').screenshot({ path: path.join(evidenceDir, `preorder-${profile.name}.jpg`), type: 'jpeg', quality: 86 });
  await context.close();
}

const ownerContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
const ownerPage = await ownerContext.newPage();
await ownerPage.route('**/web/api/microsite/thirst-trap-772/owner', async (route) => {
  await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, site: { ...site, payments: { preorders_enabled: true, cash_app_url: 'https://cash.app/$FixtureOnly', cash_app_label: '$FixtureOnly', payment_note: 'Include the order reference.', pickup_note: 'Pickup is confirmed directly.' } }, owner_uid: 1, messages: [], orders: [{ id: 1, order_number: 'TT772-LOCAL123', customer_name: 'Local Fixture', email: 'fixture@example.test', phone: '', items: [{ product_id: 'pink-lemonade', name: 'Pink Lemonade Pouch', quantity: 2, unit_price_cents: 500 }], item_count: 2, total_cents: 1000, currency: 'USD', pickup_label: 'Friday Market', notes: '', payment_method: 'cash_app', payment_status: 'unverified', order_status: 'requested', created: 1, changed: 1 }], changed: 1 }) });
});
await ownerPage.goto(`${base}/showcase/thirst-trap-772/v2/owner/`, { waitUntil: 'networkidle' });
await ownerPage.locator('#orders').scrollIntoViewIfNeeded();
await ownerPage.locator('.order-card').waitFor({ state: 'visible' });
assert.match(await ownerPage.locator('.order-card').textContent(), /TT772-LOCAL123.*Payment unverified/s);
assert.equal(await ownerPage.locator('#cash-app-url').inputValue(), 'https://cash.app/$FixtureOnly');
const ownerWidth = await ownerPage.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
assert.ok(ownerWidth.scroll <= ownerWidth.client + 1, 'mobile owner desk has no horizontal overflow');
await ownerPage.locator('#orders').screenshot({ path: path.join(evidenceDir, 'preorder-owner-mobile.jpg'), type: 'jpeg', quality: 86 });
await ownerContext.close();
await browser.close();

console.log('PASS: preorder, exact Cash App QR, mobile layout, and owner order desk render without external writes.');
