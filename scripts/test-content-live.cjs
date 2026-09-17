/** Read-only production smoke. No synthetic responses, consent changes or submissions. */
const { chromium } = require('../frontend/node_modules/@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const folder = '.local-email-preview/content-live';
const output = 'docs/evidence/content-experience-production/browser-results.json';

(async () => {
  const { CONTENT_ROUTES } = await import(pathToFileURL(path.resolve('frontend/src/components/content-experience/recipes.js')));
  const representatives = ['/packages', '/packages/199-quick-start', '/services', '/services/custom-website-development', '/about', '/contact'];
  const results = { scope: 'anonymous live-readonly production presentation; no mocked data or successful form submission', routes: [], blockedWrites: [], assets: [] };
  fs.mkdirSync(folder, { recursive: true });
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    await context.route('**/*', route => {
      if (!['GET', 'HEAD'].includes(route.request().method())) {
        results.blockedWrites.push({ method: route.request().method(), url: route.request().url() }); return route.abort();
      }
      return route.continue();
    });
    for (const host of ['famtasticdesigns.com', 'www.famtasticdesigns.com']) {
      const routes = host.startsWith('www.') ? ['/', ...representatives] : ['/', ...Object.keys(CONTENT_ROUTES)];
      for (const route of routes) for (const width of [390, 1440]) {
        const page = await context.newPage(); await page.setViewportSize({ width, height: 1000 });
        const errors = [], consoleErrors = [], failedAssets = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
        page.on('requestfailed', r => { if (/\.(js|css|woff2|png)(?:\?|$)/.test(r.url())) failedAssets.push(r.url()); });
        page.on('response', r => {
          if (/\/assets\/.*\.(js|css)(?:\?|$)/.test(r.url())) results.assets.push({ url: r.url(), status: r.status(), type: r.headers()['content-type'] || '' });
        });
        const response = await page.goto(`https://${host}${route}`, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200, `${host}${route}`);
        await page.locator('main h1').waitFor();
        if (route !== '/') await page.locator(`.fam-page[data-fam-recipe="${CONTENT_ROUTES[route]}"]`).waitFor();
        await page.waitForLoadState('networkidle', { timeout: 30000 });
        await page.evaluate(() => document.fonts.ready);
        await page.locator('.fam-brand-logo--header').evaluate(i => i.decode());
        assert.equal(await page.locator('h1').count(), 1);
        assert.ok((await page.locator('#root').innerText()).length > 100);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${host}${route}: overflow`);
        const recipe = CONTENT_ROUTES[route];
        if (recipe) assert.equal(await page.locator('.fam-page').getAttribute('data-fam-system'), '1.1.0');
        else assert.equal(await page.locator('.fam-page').count(), 0, 'homepage excluded');
        if (recipe === 'package-detail') {
          const expected = route === '/packages/199-quick-start' ? '/start?option=web-basics' : '/start';
          assert.equal(await page.locator('.fam-page .fam-ce-cta--primary').first().getAttribute('href'), expected);
          assert.ok((await page.locator('.fam-ce-offer__price').innerText()).startsWith('$'));
          if (route === '/packages/199-quick-start') {
            assert.equal(await page.locator('.fam-ce-insight').count(), 4);
            assert.match(await page.locator('#pricing').innerText(), /Not daily billing/);
          } else assert.equal(await page.locator('.fam-ce-pricing').count(), 0);
        }
        if (recipe === 'packages-hub') assert.equal(await page.locator('.fam-ce-package-card').count(), 7);
        if (recipe === 'services-hub') assert.equal(await page.locator('.fam-ce-capability').count(), 6);
        if (recipe === 'about') assert.deepEqual(await page.locator('.fam-ce-meaning h3').allTextContents(), ['Fearless Deviation', 'Applying Mastery', 'Manifesting Extraordinary']);
        if (recipe === 'contact') {
          assert.equal(await page.locator('input[name="name"],input[name="email"],input[name="phone"],input[name="business"],textarea[name="message"]').count(), 5);
          assert.equal(await page.locator('[data-intent="success"]').count(), 0);
          await page.getByRole('button', { name: 'Send Message', exact: true }).click();
          assert.equal(await page.locator('[aria-invalid="true"]').count(), 3, 'empty validation must not submit');
          await page.evaluate(() => window.scrollTo(0, 0));
        }
        if (host === 'famtasticdesigns.com' && (representatives.includes(route) || route === '/')) {
          const slug = route === '/' ? 'home' : route.slice(1).replaceAll('/', '-');
          await page.screenshot({ path: `${folder}/${slug}-${width}.png`, fullPage: true });
          await page.screenshot({ path: `${folder}/${slug}-${width}-hero.png` });
        }
        assert.deepEqual(errors, [], `${host}${route}: page errors`);
        assert.deepEqual(consoleErrors, [], `${host}${route}: console errors`);
        assert.deepEqual(failedAssets, [], `${host}${route}: failed assets`);
        results.routes.push({ host, route, finalUrl: page.url(), width, recipe: recipe || 'existing-home', heading: await page.locator('h1').innerText(), overflow: false, errors, consoleErrors, failedAssets });
        console.log(`PASS ${host}${route} ${width}px`); await page.close();
      }
    }
    results.assets = [...new Map(results.assets.map(a => [a.url, a])).values()];
    assert.ok(results.assets.length >= 2);
    for (const asset of results.assets) {
      assert.equal(asset.status, 200);
      assert.match(asset.type, asset.url.endsWith('.css') ? /text\/css/ : /javascript/);
    }
    assert.deepEqual(results.blockedWrites, []);
    results.status = 'passed'; results.checkedAt = new Date().toISOString();
    fs.mkdirSync(path.dirname(output), { recursive: true }); fs.writeFileSync(output, JSON.stringify(results, null, 2) + '\n');
    console.log(`PASS: ${results.routes.length} live desktop/mobile checks; ${results.assets.length} compiled assets; zero writes. ${output}`);
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
