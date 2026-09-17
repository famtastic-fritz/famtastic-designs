/** Local presentation acceptance. All write requests are intercepted; no mail is sent. */
const { chromium } = require('../frontend/node_modules/@playwright/test');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { createHash } = require('node:crypto');
const { pathToFileURL } = require('node:url');
const path = require('node:path');
const base = process.env.CONTENT_TEST_URL || 'http://127.0.0.1:4187';
const output = 'docs/evidence/content-experience-rollout/browser-results.json';
const norm = s => String(s || '').replace(/\s+/g, ' ').trim();

(async () => {
  const { CONTENT_ROUTES, contentRecipeForPath, safeContentHref } = await import(pathToFileURL(path.resolve('frontend/src/components/content-experience/recipes.js')));
  for (const route of ['/blog', '/work', '/portal', '/admin', '/login', '/terms', '/services/custom-199', '/packages/custom-199']) assert.equal(contentRecipeForPath(route), null);
  assert.equal(contentRecipeForPath('/about/'), 'about');
  const source = {};
  for (const [type, query] of [['package_page', '?include=field_addons'], ['service_page', '?include=field_faq_qa,field_process_steps'], ['page', '']]) {
    const res = await fetch(`https://famtasticdesigns.com/web/jsonapi/node/${type}${query}`);
    assert.equal(res.status, 200); source[type] = await res.json();
  }
  const results = { scope: 'local browser presentation against freshly fetched public CMS snapshots and cached anonymous JSON:API GETs; synthetic intercepted form responses only', sourceNodes: Object.fromEntries(Object.entries(source).map(([type, value]) => [type, value.data.map(n => n.id)])), routes: [], excludedRoutes: [], interceptedContactPosts: [], unexpectedWrites: [] };
  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ reducedMotion: 'reduce' });
    const readCache = new Map();
    await context.route('**/*', async r => {
      if (!['GET', 'HEAD'].includes(r.request().method())) {
        results.unexpectedWrites.push({ method: r.request().method(), url: r.request().url() }); return r.abort();
      }
      const url = new URL(r.request().url());
      const type = url.pathname.match(/\/jsonapi\/node\/(package_page|service_page|page)$/)?.[1];
      if (type) return r.fulfill({ json: source[type] });
      if (url.pathname.startsWith('/jsonapi/')) {
        if (!readCache.has(url.href)) readCache.set(url.href, (async () => {
          const res = await r.fetch({ timeout: 15000 });
          return { status: res.status(), headers: res.headers(), body: await res.body() };
        })());
        return r.fulfill(await readCache.get(url.href));
      }
      return r.continue();
    });
    const dataPage = await context.newPage(); await dataPage.goto(base + '/packages'); await dataPage.waitForLoadState('networkidle');
    const data = await dataPage.evaluate(async source => {
      const a = await import('/src/lib/drupalAdapter.js');
      return {
        packages: source.package_page.data.map(n => a.transformPackageNode(n, source.package_page.included)),
        services: source.service_page.data.map(n => a.transformServiceNode(n, source.service_page.included)),
        about: source.page.data.find(n => n.attributes.path?.alias === '/about'),
      };
    }, source);
    await dataPage.close();
    assert.equal(data.packages.length, 7); assert.equal(data.services.length, 6); assert.ok(data.about);

    for (const [route, recipe] of Object.entries(CONTENT_ROUTES)) {
      await Promise.all([320, 390, 768, 1440].map(async width => {
        const page = await context.newPage(); await page.setViewportSize({ width, height: 1000 });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto(base + route); await page.waitForLoadState('networkidle'); await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('.fam-page').getAttribute('data-fam-recipe'), recipe, route);
        assert.equal(await page.locator('h1').count(), 1, `${route}: one H1`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), width, `${route}: overflow at${width}`);
        const text = norm(await page.locator('.fam-page').innerText());
        for (const accent of await page.locator('.fam-page .fam-heading-script').all()) {
          assert.ok(norm(await accent.innerText()).split(' ').length <= 6);
          assert.ok(await accent.evaluate(e => ['H1', 'H2'].includes(e.parentElement.tagName)));
        }
        for (const link of await page.locator('.fam-page .fam-ce-cta').all()) {
          assert.ok((await link.boundingBox()).height >= 44);
          if (await link.evaluate(e => e.tagName === 'A')) assert.ok(safeContentHref(await link.getAttribute('href')));
        }
        assert.equal(await page.locator('.fam-page a[href*="/buy"]').count(), 0);
        if (recipe === 'package-detail') {
          const plan = data.packages.find(p => route === `/packages/${p.slug}`); assert.ok(plan);
          assert.ok(text.includes(norm(plan.price))); assert.ok(text.includes(norm(plan.bestFor))); assert.ok(text.includes(norm(plan.timeline)));
          assert.deepEqual(await page.locator('.fam-ce-features h3').allTextContents(), plan.whatsIncluded.length ? plan.whatsIncluded : plan.features);
          const expected = plan.slug === '199-quick-start' ? '/start?option=web-basics' : '/start';
          for (const link of await page.locator('.fam-ce-cta--primary').all()) assert.equal(await link.getAttribute('href'), expected, `${route}: correct intake`);
          if (plan.slug !== '199-quick-start') {
            assert.equal(await page.locator('.fam-ce-pricing').count(), 0, 'no inherited Web Basics billing');
            assert.equal(await page.locator('.fam-ce-offer__price').innerText(), plan.price);
          }
          for (const addon of plan.addons) for (const field of ['name', 'description', 'price']) if (addon[field]) assert.ok(text.includes(norm(addon[field])));
        }
        if (recipe === 'packages-hub') {
          assert.equal(await page.locator('.fam-ce-package-card').count(), data.packages.length);
          for (const plan of data.packages) {
            const card = page.locator(`[data-fam-instance="${plan.id}"]`); const copy = norm(await card.innerText());
            for (const item of [plan.title, plan.price, plan.bestFor, plan.timeline, ...plan.features.slice(0, 7)]) if (item) assert.ok(copy.includes(norm(item)));
            assert.equal(await card.locator('a').getAttribute('href'), `/packages/${plan.slug}`);
          }
        }
        if (recipe === 'services-hub') {
          assert.equal(await page.locator('.fam-ce-capability').count(), data.services.length);
          for (const service of data.services) {
            const card = page.locator(`[data-fam-instance="${service.id}"]`); const copy = norm(await card.innerText());
            const fields = [service.title, service.subheadline, ...(service.features.length ? service.features : service.solutionBullets).slice(0, 4)];
            for (const item of fields) if (item) assert.ok(copy.includes(norm(item)));
            assert.equal(await card.locator('a').getAttribute('href'), `/services/${service.slug}`);
          }
        }
        if (recipe === 'solution-detail') {
          const service = data.services.find(s => route === `/services/${s.slug}`); assert.ok(service);
          for (const item of [service.headline, service.subheadline, ...service.painPoints, ...service.solutionBullets, ...service.features]) if (item) assert.ok(text.includes(norm(item)), `${route}: ${item}`);
          assert.equal(await page.locator('.fam-ce-process li').count(), service.processSteps.length);
          for (const step of service.processSteps) for (const field of ['title', 'body']) if (step[field]) assert.ok(text.includes(norm(step[field])));
          assert.equal(await page.locator('.fam-ce-quote').count(), service.testimonial.quote ? 1 : 0);
          assert.equal(await page.locator('.v1-faq__question').count(), service.faqs.length);
          for (let i = 0; i < service.faqs.length; i++) {
            const q = page.locator('.v1-faq__question').nth(i); await q.click();
            assert.equal(await q.getAttribute('aria-expanded'), 'true');
            assert.ok(norm(await page.locator('.v1-faq__answer').innerText()).includes(norm(service.faqs[i].answer)));
            await q.click(); assert.equal(await q.getAttribute('aria-expanded'), 'false');
          }
        }
        if (recipe === 'about') {
          for (const meaning of ['Fearless Deviation', 'Applying Mastery', 'Manifesting Extraordinary']) assert.ok(text.includes(meaning));
          const expected = await page.evaluate(html => { const d = new DOMParser().parseFromString(html, 'text/html'); return d.body.textContent; }, data.about.attributes.body.processed);
          assert.equal(norm(await page.locator('.fam-ce-story-body').textContent()), norm(expected));
        }
        if (recipe === 'contact') {
          assert.equal(await page.locator('input[name="name"],input[name="email"],input[name="phone"],input[name="business"],textarea[name="message"]').count(), 5);
          assert.ok(await page.locator('#project-fit').evaluate(e => e.compareDocumentPosition(document.querySelector('#contact-form')) & Node.DOCUMENT_POSITION_FOLLOWING));
          assert.equal(await page.locator('[data-intent="success"]').count(), 0);
        }
        const focusTarget = page.locator('.fam-ce-cta').first();
        if (await focusTarget.count()) {
          await focusTarget.focus(); assert.ok(await focusTarget.evaluate(e => parseFloat(getComputedStyle(e).outlineWidth) >= 2));
          assert.equal(await focusTarget.evaluate(e => getComputedStyle(e).transitionDuration), '0s');
        }
        assert.deepEqual(errors, [], route);
        results.routes.push({ route, recipe, width, overflow: false, sourceContentPreserved: true, errors });
        await page.close();
      }));
      console.log(`PASS ${route} at320/390/768/1440`);
    }

    // Contact validation and real handler, but wholly synthetic transport receipts.
    const contact = await context.newPage(); await contact.goto(base + '/contact'); await contact.waitForLoadState('networkidle');
    await contact.getByRole('button', { name: 'Send Message', exact: true }).click();
    assert.equal(await contact.locator('[aria-invalid="true"]').count(), 3);
    assert.equal(results.unexpectedWrites.length, 0);
    let receipt = { ok: true, status: 'received', request_id: 900001, notification_sent: true, message: 'Synthetic saved request. No real mail was sent.' };
    await contact.route('**/api/public/contact', r => {
      const body = r.request().postDataJSON();
      assert.equal(body.source, 'contact-form'); assert.equal(body.path, '/contact');
      assert.equal(body.email, 'local-preview@example.invalid');
      results.interceptedContactPosts.push({ method: r.request().method(), scenario: receipt?.status || 'unconfirmed', synthetic: true });
      return receipt === null ? r.abort('failed') : r.fulfill({ status: receipt.status === 'partial_success' ? 202 : 200, json: receipt });
    });
    const submit = async () => {
      await contact.locator('input[name="name"]').fill('Local QA <script>');
      await contact.locator('input[name="email"]').fill('local-preview@example.invalid');
      await contact.locator('textarea[name="message"]').fill('Synthetic browser validation only. Do not send.');
      await contact.getByRole('button', { name: 'Send Message', exact: true }).click();
      await contact.locator('.v1-form-card[role="status"]').waitFor();
    };
    await submit(); assert.equal(await contact.locator('[data-intent="success"]').count(), 1);
    await contact.getByRole('button', { name: 'Send another message' }).click();
    receipt = { ...receipt, status: 'partial_success', notification_sent: false, message: 'Synthetic request saved; notification failed.' };
    await submit(); assert.equal(await contact.locator('[data-intent="success"]').count(), 1);
    assert.match(await contact.locator('.v1-form-card[role="status"]').innerText(), /notification failed/);
    await contact.getByRole('button', { name: 'Send another message' }).click();
    receipt = { ok: true, status: 'received' }; await submit();
    assert.equal(await contact.locator('[data-intent="success"]').count(), 0);
    assert.match(await contact.locator('.v1-form-card[role="status"]').innerText(), /could not confirm/);
    await contact.getByRole('button', { name: 'Send another message' }).click();
    receipt = null; await submit(); assert.equal(await contact.locator('[data-intent="success"]').count(), 0);
    assert.match(await contact.locator('.v1-form-card[role="status"]').innerText(), /could not reach the server/);
    assert.match(await contact.locator('.v1-form-card[role="status"] a').getAttribute('href'), /^mailto:hello@famtasticdesigns.com\?/);
    await contact.close(); results.contact = 'validation, confirmed saved, partial notification failure, missing receipt, failed transport, reset: passed with interception; no inbox/provider evidence';

    const finder = await context.newPage(); await finder.goto(base + '/services/client-portal-systems'); await finder.waitForLoadState('networkidle');
    await finder.getByRole('button', { name: 'Start with this service', exact: true }).click();
    await finder.locator('#solution-finder').waitFor(); await finder.getByRole('button', { name: 'Close chat' }).click();
    assert.equal(await finder.locator('#solution-finder').count(), 1); await finder.close(); results.finder = 'preselected existing service finder opens and closes without submission';

    // Empty/missing content and unsafe-looking strings remain safe local fixtures.
    const missing = await context.newPage();
    await missing.route('**/jsonapi/node/service_page?*', r => r.fulfill({ json: { data: [] } }));
    await missing.goto(base + '/services/ai-chatbot'); await missing.waitForLoadState('networkidle');
    assert.match(await missing.locator('main').innerText(), /could not find that service/); assert.equal(await missing.locator('.fam-page').count(), 0);
    await missing.close();
    const hostile = await context.newPage(); const fixture = structuredClone(source.service_page);
    fixture.data.find(n => n.attributes.path.alias === '/services/ai-chatbot').attributes.field_hero_headline = '<img src=x onerror="alert(1)">';
    await hostile.route('**/jsonapi/node/service_page?*', r => r.fulfill({ json: fixture }));
    await hostile.goto(base + '/services/ai-chatbot'); await hostile.waitForLoadState('networkidle');
    assert.match(await hostile.locator('h1').innerText(), /<img/); assert.equal(await hostile.locator('.fam-page img[src="x"]').count(), 0); await hostile.close();
    results.missingAndEscaping = 'passed';

    results.fontFallback = [];
    for (const route of ['/packages', '/packages/499-site-upgrade', '/services', '/services/ai-chatbot', '/about', '/contact']) {
      const page = await context.newPage(); await page.setViewportSize({ width: 320, height: 900 });
      await page.route('**/brand/fonts/*.woff2', r => r.abort());
      await page.goto(base + route); await page.waitForLoadState('networkidle');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth), 320);
      assert.equal(await page.evaluate(() => document.fonts.check('24px "FAMtastic Heading Script"')), false);
      // Real keyboard entry, not just a programmatic focus style check.
      let focused = false;
      for (let i = 0; i < 45; i++) {
        await page.keyboard.press('Tab'); focused = await page.evaluate(() => document.activeElement?.classList.contains('fam-ce-cta'));
        if (focused) break;
      }
      assert.ok(focused, route); assert.ok(await page.evaluate(() => parseFloat(getComputedStyle(document.activeElement).outlineWidth) >= 2));
      results.fontFallback.push({ route, width: 320, overflow: false, keyboardFocus: true }); await page.close();
    }

    for (const route of ['/', '/blog', '/work', '/portal', '/login', '/terms']) {
      const page = await context.newPage(); await page.goto(base + route); await page.waitForLoadState('networkidle');
      assert.equal(await page.locator('.fam-page').count(), 0, `excluded ${route}`); results.excludedRoutes.push(route); await page.close();
    }
    assert.equal(createHash('sha256').update(fs.readFileSync('frontend/public/brand/famtastic-designs-logo-v1.png')).digest('hex'), 'ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950');
    assert.deepEqual(results.unexpectedWrites, []);
    results.status = 'passed'; results.checkedAt = new Date().toISOString();
    fs.writeFileSync(output, JSON.stringify(results, null, 2) + '\n'); console.log(`PASS ${results.routes.length} responsive route cases; synthetic-only form QA. ${output}`);
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
