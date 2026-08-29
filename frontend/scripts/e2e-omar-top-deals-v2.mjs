import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const base = process.env.OMAR_TOP_DEALS_BASE_URL || 'http://127.0.0.1:4197/showcase/omar-top-deals';
const localStatic = base.includes('127.0.0.1') || base.includes('localhost');
const v2Url = localStatic ? `${base}/v2/index.html` : `${base}/v2/`;
const ownerUrl = localStatic ? `${base}/owner/index.html` : `${base}/owner/`;
const evidenceDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../docs/evidence/omar-top-deals-flyer-storefront-v2/screenshots');
const browser = await chromium.launch({ headless: true });

async function verifyViewport(viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const consoleErrors = [];
  page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', (error) => consoleErrors.push(error.message));

  await page.goto(v2Url, { waitUntil: 'networkidle' });
  assert.equal(await page.title(), 'Omar Top Deals V2 — The Digital Handbill');
  assert.equal(await page.locator('body').evaluate((body) => body.scrollWidth === body.clientWidth), true, `v2 ${viewport.width}px view must not overflow`);
  assert.equal(await page.locator('.product-card').count(), 4);
  assert.match(await page.locator('#drop').textContent(), /DEMO VALUES, NOT LIVE PRICES/);
  assert.equal(await page.locator('.product-card img').evaluateAll((images) => images.every((image) => image.complete && image.naturalWidth > 0)), true, 'all product images should load');
  await page.screenshot({ path: path.join(evidenceDir, `public-v2-${viewport.width <= 390 ? 'mobile' : 'desktop'}.png`), fullPage: true });
  if (viewport.width <= 390) {
    await page.screenshot({ path: path.join(evidenceDir, 'public-v2-mobile-fold.png') });
    await page.locator('.product-card').first().screenshot({ path: path.join(evidenceDir, 'public-v2-mobile-product.png') });
  }

  if (viewport.width <= 390) {
    await page.locator('.product-card').first().getByRole('button').click();
    assert.equal(await page.locator('#hold-item').inputValue(), 'Statement Bucket Hats');
    await page.locator('#hold-form [name="name"]').fill('V2 Demo Guest');
    await page.locator('#hold-form [name="reply"]').fill('device-only@example.test');
    await page.locator('#hold-form [name="note"]').fill('Looking for a red and black pattern.');
    await page.locator('#hold-form button[type="submit"]').click();
    assert.match(await page.locator('#toast').textContent(), /Nothing was sent or charged/i);
    await page.goto(ownerUrl, { waitUntil: 'networkidle' });
    assert.match(await page.locator('#today-holds').textContent(), /V2 Demo Guest/);
    await page.locator('[data-owner-tab="table"]').last().click();
    await page.locator('.item-setting[data-item-id="headwear"] [name="value"]').fill('$27');
    await page.locator('.item-setting[data-item-id="headwear"] [name="valueNote"]').fill('owner-adjusted demo');
    await page.locator('#save-items').click();
    await page.goto(v2Url, { waitUntil: 'networkidle' });
    assert.equal(await page.locator('[data-demo-value="headwear"]').textContent(), '$27');
    assert.equal(await page.locator('[data-demo-note="headwear"]').textContent(), 'owner-adjusted demo');
  }

  assert.equal(consoleErrors.length, 0, `unexpected console errors: ${consoleErrors.join('; ')}`);
  await context.close();
}

try {
  await verifyViewport({ width: 1440, height: 1100 });
  await verifyViewport({ width: 390, height: 844 });
  console.log('PASS Omar Top Deals V2 E2E: distinct flyer storefront, 4 product values, desktop/mobile no-overflow, V2 hold -> existing owner desk, zero external effects.');
} finally {
  await browser.close();
}
