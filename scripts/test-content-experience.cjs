/** Read-only browser acceptance. Never submit a form or enable analytics consent. */
const { chromium } = require('../frontend/node_modules/@playwright/test');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const path = require('node:path');
const base = process.env.CONTENT_TEST_URL || 'http://127.0.0.1:4187';
const folder = '.local-email-preview/content-experience';
// Keep the original approved proof receipt frozen at its source revision.
const output = 'docs/evidence/content-experience-rollout/web-basics-regression.json';

(async () => {
  const { hasContentExperience, safeContentHref } = await import(pathToFileURL(path.resolve('frontend/src/components/content-experience/recipes.js')));
  assert.equal(hasContentExperience('199-quick-start'), true);
  assert.equal(hasContentExperience('499-site-upgrade'), true);
  for (const slug of ['custom-199', '199', '']) assert.equal(hasContentExperience(slug), false);
  for (const href of ['javascript:alert(1)', '//example.com', '/\\example.com', 'data:text/html,test', 'https://user:pass@example.com', '/\nexample']) assert.equal(safeContentHref(href), null);
  assert.equal(safeContentHref('/start?option=web-basics'), '/start?option=web-basics');
  const { resolveJsonApiNext } = await import(pathToFileURL(path.resolve('frontend/src/utils/jsonApiPagination.js')));
  const next = 'https://famtasticdesigns.com/web/jsonapi/node/blog_post?page%5Boffset%5D=50';
  assert.equal(resolveJsonApiNext(next, { origin: base, useDevelopmentProxy: true }), '/jsonapi/node/blog_post?page%5Boffset%5D=50');
  assert.equal(resolveJsonApiNext(next, { origin: base }), next, 'production absolute pagination unchanged');

  const products = JSON.parse(fs.readFileSync('backend/config/famtastic-products.json'));
  const terms = JSON.parse(fs.readFileSync('backend/config/famtastic-deal-terms.json'));
  assert.equal(products.products.find(p => p.sku === 'FAM-HOST-999').price, '9.99');
  assert.match(terms.deals['FAM-FOOT-199'].renewal.hosting, /separate recurring authorization/);
  const response = await fetch('https://famtasticdesigns.com/web/jsonapi/node/package_page?include=field_addons');
  assert.equal(response.status, 200);
  const source = await response.json();
  const packageNode = source.data.find(n => n.attributes.path.alias === '/packages/199-quick-start');
  assert.ok(packageNode);
  const features = packageNode.attributes.field_whats_included.length ? packageNode.attributes.field_whats_included : packageNode.attributes.field_features;
  const browser = await chromium.launch();
  const results = { scope: 'local presentation, live read-only CMS; no send/payment/customer persistence', widths: [], excludedRoutes: [], mutations: [] };
  fs.mkdirSync(folder, { recursive: true });
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    await context.route('**/*', route => {
      if (!['GET', 'HEAD'].includes(route.request().method())) {
        results.mutations.push({ method: route.request().method(), url: route.request().url() });
        return route.abort();
      }
      return route.continue();
    });
    for (const width of [320, 390, 768, 1024, 1440]) {
      const page = await context.newPage(); await page.setViewportSize({ width, height: 1000 });
      const errors = []; page.on('pageerror', e => errors.push(e.message));
      await page.goto(`${base}/packages/199-quick-start`); await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready);
      assert.equal(await page.locator('h1').count(), 1);
      assert.equal(await page.locator('.fam-page').getAttribute('data-fam-recipe'), 'package-detail');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width);
      assert.deepEqual(errors, []);
      const actual = await page.locator('.fam-ce-features h3').allTextContents(); assert.deepEqual(actual, features);
      const mainText = await page.locator('main').innerText();
      assert.ok(mainText.includes(packageNode.attributes.field_best_for.value));
      assert.ok(mainText.includes(packageNode.attributes.field_timeline));
      assert.match(mainText, /Not daily billing/); assert.match(mainText, /separate recurring authorization/);
      assert.match(mainText, /final approval of the completed staging site/);
      assert.equal(await page.locator('.fam-ce-price-figure').innerText(), '55¢');
      assert.equal(await page.locator('.fam-ce-insight').count(), 4, 'real published guides must render');
      for (const accent of await page.locator('.fam-page .fam-heading-script').all()) {
        assert.ok((await accent.innerText()).split(/\s+/).length <= 6);
        assert.ok(await accent.evaluate(e => ['H1', 'H2'].includes(e.parentElement.tagName)));
      }
      for (const button of await page.locator('.fam-page .fam-ce-cta').all()) {
        assert.ok((await button.boundingBox()).height >= 44);
        assert.ok(safeContentHref(await button.getAttribute('href')));
      }
      for (const link of await page.locator('.fam-page .fam-ce-cta--primary').all()) assert.equal(await link.getAttribute('href'), '/start?option=web-basics');
      assert.equal(await page.locator('.fam-page a[href*="/buy"]').count(), 0);
      assert.ok(await page.locator('link[rel="canonical"]').getAttribute('href').then(h => h.replace(/\/$/, '').endsWith('/packages/199-quick-start')));
      if (width === 390 || width === 1440) {
        await page.screenshot({ path: `${folder}/after-${width}.png`, fullPage: true });
        await page.screenshot({ path: `${folder}/hero-${width}.png` });
      }
      // Keyboard, not programmatic focus: walk into the first local CTA.
      let focused = false;
      for (let i = 0; i < 40; i++) {
        await page.keyboard.press('Tab');
        focused = await page.evaluate(() => document.activeElement?.classList.contains('fam-ce-cta'));
        if (focused) break;
      }
      assert.ok(focused);
      assert.equal(await page.evaluate(() => getComputedStyle(document.activeElement).outlineWidth), '2px');
      assert.equal(await page.evaluate(() => getComputedStyle(document.activeElement).transitionDuration), '0s');
      results.widths.push({ width, overflow: false, preservedFeatures: actual.length, guideCards: 4, keyboardFocus: true, minTarget: 44, errors });
      await page.close();
    }
    // Font failure, real text/escaping and actual node absence.
    const fallback = await context.newPage(); await fallback.setViewportSize({ width: 320, height: 900 });
    await fallback.route('**/brand/fonts/*.woff2', r => r.abort());
    await fallback.goto(`${base}/packages/199-quick-start`); await fallback.waitForLoadState('networkidle');
    assert.equal(await fallback.evaluate(() => document.documentElement.scrollWidth), 320);
    assert.equal(await fallback.evaluate(() => document.fonts.check('24px "FAMtastic Heading Script"')), false);
    results.fontFallback = 'passed at320'; await fallback.close();

    const content = await context.newPage();
    const hostile = structuredClone(source); const node = hostile.data.find(n => n.id === packageNode.id);
    node.attributes.title = '<img src=x onerror="alert(1)"> $199';
    node.attributes.field_features[0] = '<script>alert(1)</script>';
    await content.route('**/jsonapi/node/package_page?*', r => r.fulfill({ json: hostile }));
    await content.goto(`${base}/packages/199-quick-start`); await content.waitForLoadState('networkidle');
    assert.match(await content.locator('h1').innerText(), /<img/);
    assert.equal(await content.locator('.fam-page img[src="x"], .fam-page script').count(), 0);
    await content.route('**/jsonapi/node/package_page?*', r => r.fulfill({ json: { data: [] } }));
    await content.reload(); await content.waitForLoadState('networkidle');
    assert.equal(await content.locator('.fam-page').count(), 0);
    assert.match(await content.locator('main').innerText(), /could not find that package/);
    results.escapingAndMissingNode = 'passed'; await content.close();

    for (const route of ['/', '/blog', '/work']) {
      const page = await context.newPage(); await page.goto(base + route); await page.waitForLoadState('networkidle');
      assert.equal(await page.locator('.fam-page').count(), 0, `not enrolled: ${route}`);
      results.excludedRoutes.push(route); await page.close();
    }
    assert.deepEqual(results.mutations, []);
    results.status = 'passed'; results.checkedAt = new Date().toISOString();
    fs.mkdirSync(path.dirname(output), { recursive: true }); fs.writeFileSync(output, JSON.stringify(results, null, 2) + '\n');
    console.log(JSON.stringify(results, null, 2));
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
