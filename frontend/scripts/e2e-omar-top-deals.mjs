import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const base = process.env.OMAR_TOP_DEALS_BASE_URL || 'http://127.0.0.1:4197/showcase/omar-top-deals';
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
const page = await context.newPage();
const consoleErrors = [];
page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', (error) => consoleErrors.push(error.message));

try {
  await page.goto(`${base}/`, { waitUntil: 'networkidle' });
  assert.equal(await page.title(), 'Omar Top Deals — The Pop-Up, Before and After the Pop-Up');
  assert.equal(await page.locator('body').evaluate((body) => body.scrollWidth === body.clientWidth), true, 'public mobile view must not overflow');
  await page.locator('[data-open-hold]:visible').first().click();
  await page.locator('#hold-form [name="name"]').fill('Demo Guest');
  await page.locator('#hold-form [name="reply"]').fill('local-only@example.test');
  await page.locator('#hold-form [name="note"]').fill('Looking for a blue cap.');
  await page.locator('#hold-form button[type="submit"]').click();
  await page.waitForTimeout(150);
  assert.match(await page.locator('#toast').textContent(), /Nothing was sent/i);

  await page.goto(`${base}/owner/`, { waitUntil: 'networkidle' });
  assert.equal(await page.locator('body').evaluate((body) => body.scrollWidth === body.clientWidth), true, 'owner mobile view must not overflow');
  assert.match(await page.locator('#today-holds').textContent(), /Demo Guest/);
  await page.locator('[data-owner-tab="events"]').last().click();
  await page.locator('#event-form [name="title"]').fill('Saturday Market Test');
  await page.locator('#event-form [name="location"]').fill('Demo location — not public');
  await page.locator('#event-form [name="date"]').fill('Saturday · 11 AM–4 PM');
  await page.locator('#event-form [name="status"]').selectOption('confirmed');
  await page.locator('#event-form button[type="submit"]').click();

  await page.goto(`${base}/`, { waitUntil: 'networkidle' });
  assert.match(await page.locator('#event-title').textContent(), /Saturday Market Test/);
  assert.match(await page.locator('#event-status-label').textContent(), /confirmed by Omar/i);
  assert.equal(consoleErrors.length, 0, `unexpected console errors: ${consoleErrors.join('; ')}`);
  console.log('PASS Omar Top Deals E2E: mobile public hold -> owner desk, owner event -> public page, zero overflow, zero console errors, zero external effects.');
} finally {
  await browser.close();
}
