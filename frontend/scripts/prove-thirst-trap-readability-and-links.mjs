import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(scriptDir, '../..');
const evidenceDir = path.join(repoRoot, 'docs/evidence/thirst-trap-772-readability-footer-v2-2/screenshots');
const base = process.env.FAMTASTIC_PREVIEW_BASE || 'http://127.0.0.1:4192';

await mkdir(evidenceDir, { recursive: true });
const browser = await chromium.launch({ headless: true });

for (const profile of [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'wide-mobile', width: 666, height: 844 },
]) {
  const context = await browser.newContext({ viewport: { width: profile.width, height: profile.height }, isMobile: true, deviceScaleFactor: 1 });
  const page = await context.newPage();
  await page.goto(`${base}/showcase/thirst-trap-772/v2/`, { waitUntil: 'networkidle' });
  await page.locator('.hero-copy').waitFor({ state: 'visible' });

  const typography = await page.evaluate(() => {
    const metrics = (selector) => {
      const style = getComputedStyle(document.querySelector(selector));
      return { fontSize: parseFloat(style.fontSize), letterSpacing: parseFloat(style.letterSpacing) || 0, fontFamily: style.fontFamily };
    };
    return { button: metrics('.hero-actions .button'), kicker: metrics('.hero .kicker'), lede: metrics('.hero .lede'), brandDetail: metrics('.brand-lockup small') };
  });

  assert.ok(typography.button.fontSize >= 13.4, `${profile.name}: CTA is at least 13.4px`);
  assert.ok(typography.button.letterSpacing <= .6, `${profile.name}: CTA tracking remains readable`);
  assert.match(typography.button.fontFamily, /system-ui|Segoe UI|Arial/i);
  assert.ok(typography.kicker.fontSize >= 11.4, `${profile.name}: utility kicker is at least 11.4px`);
  assert.ok(typography.kicker.letterSpacing <= 1.1, `${profile.name}: utility kicker tracking remains readable`);
  assert.ok(typography.lede.fontSize >= 16, `${profile.name}: hero body copy is at least 16px`);
  assert.ok(typography.brandDetail.fontSize >= 9, `${profile.name}: brand tagline is at least 9px`);

  const widths = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
  assert.ok(widths.scroll <= widths.client + 1, `${profile.name}: no horizontal overflow`);
  await page.locator('.skip-link').evaluate((element) => { element.style.display = 'none'; });
  await page.locator('.hero').screenshot({ path: path.join(evidenceDir, `hero-${profile.name}.jpg`), type: 'jpeg', quality: 88 });

  await page.locator('.site-footer').scrollIntoViewIfNeeded();
  await page.locator('.site-footer').screenshot({ path: path.join(evidenceDir, `footer-${profile.name}.jpg`), type: 'jpeg', quality: 88 });
  assert.equal(await page.locator('.system-links a', { hasText: 'Admin demo' }).getAttribute('href'), './owner/?demo=1');
  assert.equal(await page.locator('.system-links a', { hasText: 'Client portal' }).getAttribute('href'), '/portal');
  assert.match(await page.locator('.famtastic-credit').textContent(), /FAMtastic.*DESIGNS.*Website.*Preorders.*Owner Studio/s);
  await context.close();
}

const demoContext = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, deviceScaleFactor: 1 });
const demoPage = await demoContext.newPage();
let ownerApiRequests = 0;
demoPage.on('request', (request) => { if (request.url().includes('/web/api/microsite/thirst-trap-772/owner')) ownerApiRequests += 1; });
await demoPage.goto(`${base}/showcase/thirst-trap-772/v2/owner/?demo=1`, { waitUntil: 'networkidle' });
await demoPage.locator('#studio').waitFor({ state: 'visible' });
assert.equal(ownerApiRequests, 0, 'public admin demo does not request protected owner data');
assert.match(await demoPage.locator('#demo-notice').textContent(), /PUBLIC ADMIN DEMO.*fictional.*nothing here saves/s);
assert.equal(await demoPage.locator('[name="product-name-0"]').inputValue(), 'Pink Lemonade Pouch');
assert.match(await demoPage.locator('#order-list').textContent(), /TT772-DEMO.*Payment unverified/s);
assert.equal(await demoPage.locator('#save-top').isDisabled(), true);
const demoWidths = await demoPage.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
assert.ok(demoWidths.scroll <= demoWidths.client + 1, 'mobile public admin demo has no horizontal overflow');
await demoPage.locator('.demo-notice').screenshot({ path: path.join(evidenceDir, 'admin-demo-mobile.jpg'), type: 'jpeg', quality: 88 });
await demoContext.close();

await browser.close();
console.log('PASS: readable hero utility type, labeled system footer links, FAMtastic branding, and public no-sign-in admin demo are browser-proven.');
