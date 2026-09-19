#!/usr/bin/env node
'use strict';

/**
 * Browser-only proof navigation regression checks against real frontend code.
 * Every account API, login and proof document is fulfilled locally. No backend
 * session, real credentials, customer data, selection or outbound message exists.
 *
 * node scripts/email-preview/check-proof-portal.cjs --base-url http://127.0.0.1:4199 \
 *   --playwright /absolute/path/to/node_modules/playwright
 * Optional: --output-dir /tmp/proof-portal-results --timeout-ms 20000
 * Env: PROOF_PORTAL_BASE_URL, PLAYWRIGHT_MODULE, PROOF_PORTAL_OUTPUT_DIR.
 * Defaults: localhost:4199, installed playwright, a unique ignored .artifacts run.
 * Exit 1 means a regression; screenshots and results.json survive failures.
 * This verifies frontend behavior, not backend authorization or real proof bytes.
 */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '../..');
const TARGET = '11111111-1111-4111-8111-111111111111';
const OTHER = '22222222-2222-4222-8222-222222222222';
const TARGET_NAME = 'Synthetic proof navigation project';
const DESTINATION = `/portal/?section=projects&request=${TARGET}`;

function options() {
  const parsed = {
    'base-url': process.env.PROOF_PORTAL_BASE_URL || 'http://127.0.0.1:4199',
    playwright: process.env.PLAYWRIGHT_MODULE || '',
    'output-dir': process.env.PROOF_PORTAL_OUTPUT_DIR || '',
    'timeout-ms': '20000',
  };
  const args = process.argv.slice(2);
  if (args.includes('--help')) {
    console.log('Usage: node scripts/email-preview/check-proof-portal.cjs [--base-url URL] [--playwright MODULE] [--output-dir DIRECTORY] [--timeout-ms NUMBER]');
    process.exit(0);
  }
  for (let i = 0; i < args.length; i++) {
    const key = args[i].replace(/^--/, '');
    if (!args[i].startsWith('--') || !Object.hasOwn(parsed, key) || !args[i + 1] || args[i + 1].startsWith('--')) throw new Error(`Unknown/incomplete argument: ${args[i]}`);
    parsed[key] = args[++i];
  }
  const url = new URL(parsed['base-url']);
  assert.ok(['http:', 'https:'].includes(url.protocol) && !url.username && !url.password, 'Base URL must be HTTP(S) without credentials');
  assert.ok(url.pathname === '/' && !url.search && !url.hash, 'Base URL must be an origin, without path/query/fragment');
  const timeout = Number(parsed['timeout-ms']);
  assert.ok(Number.isInteger(timeout) && timeout > 0 && timeout <= 60000, 'Timeout must be 1–60000 milliseconds');
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  return { origin: url.origin, timeout, module: parsed.playwright, output: path.resolve(parsed['output-dir'] || path.join(ROOT, '.artifacts/proof-portal', `${stamp}-${process.pid}`)) };
}

function playwright(modulePath) {
  const candidates = modulePath ? [modulePath] : [
    'playwright', path.join(ROOT, 'frontend/node_modules/playwright'),
    path.join(os.homedir(), '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'),
  ];
  for (const candidate of candidates) {
    let resolved;
    try { resolved = require.resolve(candidate); } catch (error) { if (error.code === 'MODULE_NOT_FOUND') continue; throw error; }
    return { ...require(resolved), resolved };
  }
  throw new Error('Playwright not found. Pass --playwright /absolute/path/to/node_modules/playwright. No install is attempted.');
}

function project(id, name) {
  return {
    public_id: id, project_name: name, status: 'submitted', project_type: 'new_website',
    proof_review_status: 'notified', customer_archived: false, selected_proof_direction: '',
    changed: 1789700000, intake: {}, assets: [],
    proof_handoff: { state: 'customer_ready', label: 'Concepts ready', detail: 'Synthetic browser-only fixture.' },
    proofs: {
      variants: ['a', 'b', 'c'].map((direction_id) => ({ direction_id, direction_name: `Synthetic direction ${direction_id.toUpperCase()}`, preview_url: `/web/api/customer/website-requests/${id}/proofs/${direction_id}` })),
      research_snapshot: { overview: `SYNTHETIC FIXTURE: ${name}. No production account or proof data.`, market_signals: [], opportunities: [], sources: [] },
      review_terms: { design_reset_remaining: 1, edit_rounds_remaining: 3 },
    },
  };
}

function fixtures(wrongAccount) {
  const customer = { public_id: '33333333-3333-4333-8333-333333333333', display_name: 'Synthetic browser customer', email: `${wrongAccount ? 'other' : 'owner'}@example.invalid`, verified: true };
  const organization = { public_id: '44444444-4444-4444-8444-444444444444', name: 'Synthetic browser organization', members: [] };
  const other = project(OTHER, 'Other account-owned synthetic project');
  const requests = wrongAccount ? [other] : [other, project(TARGET, TARGET_NAME)];
  return {
    session: { ok: true, customer, organizations: [organization], can_manage_messages: false },
    workspace: { organization, customer, website_requests: requests, projects: [], orders: [], entitlements: [], services: [], intakes: [], activity: [], threads: [], faqs: [], referrals: [], booking_sites: [], preferences: {} },
  };
}

async function ownerLanding(page, result, opts, label) {
  const id = `concepts-${TARGET}`;
  await page.locator(`#${id}`).waitFor({ state: 'visible' });
  // Do not scroll or click first: this specifically tests the automatic effect.
  await page.waitForFunction((target) => {
    const element = document.getElementById(target);
    if (!element || document.activeElement !== element) return false;
    const box = element.getBoundingClientRect();
    return box.top >= -1 && box.top < Math.min(200, innerHeight / 3) && box.bottom > 0;
  }, id);
  const geometry = await page.locator(`#${id}`).evaluate((element) => ({ top: element.getBoundingClientRect().top, viewportHeight: innerHeight, scrollY, tabIndex: element.tabIndex, activeElement: document.activeElement.id }));
  assert.equal(geometry.tabIndex, -1);
  assert.equal(await page.locator(`#website-request-${TARGET}`).count(), 1);
  assert.equal(await page.locator(`#website-request-${OTHER}`).count(), 0, 'Explicit request must win over first ready project');
  const suite = page.locator(`#${id}`);
  assert.equal(await suite.locator('[data-proof-direction]').count(), 3);
  assert.equal(await suite.locator('iframe').count(), 3);
  assert.equal(await page.locator('.portal-notice--error').count(), 0);
  const frameUrls = await suite.locator('iframe').evaluateAll((frames) => frames.map((frame) => frame.getAttribute('src')));
  assert.deepEqual(frameUrls, ['a', 'b', 'c'].map((direction) => `/web/api/customer/website-requests/${TARGET}/proofs/${direction}`));
  result.checks[label] = { passed: true, geometry, frameUrls };
  await page.screenshot({ path: path.join(opts.output, `${result.name}-${label}.png`) });
}

async function runCase(browser, opts, viewport, wrongAccount, report, save) {
  const result = { name: `${viewport.width < 600 ? 'mobile' : 'desktop'}-${wrongAccount ? 'wrong-account' : 'owner'}`, viewport, wrongAccount, checks: {}, mocks: [], forwarded: [], blockedMutations: [], unexpectedApis: [], deniedExternalRequests: [], pageErrors: [], assetHashes: [], warnings: [] };
  report.cases.push(result);
  const fixture = fixtures(wrongAccount);
  const context = await browser.newContext({ viewport, serviceWorkers: 'block', reducedMotion: 'reduce' });
  await context.routeWebSocket('**/*', (socket) => socket.close());
  const page = await context.newPage();
  page.setDefaultTimeout(opts.timeout);
  page.on('pageerror', (error) => result.pageErrors.push(error.message));
  const assetJobs = [];
  page.on('response', (response) => {
    const url = new URL(response.url());
    if (url.origin === opts.origin && response.request().resourceType() === 'script' && /\/assets\/|CustomerPortalDashboard|PortalProjectsView|LoginPage|portalReturn/.test(url.pathname)) {
      assetJobs.push(response.body().then((body) => result.assetHashes.push({ url: response.url(), sha256: crypto.createHash('sha256').update(body).digest('hex') })).catch(() => {}));
    }
  });
  let loggedIn = false;
  await context.route('**/*', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const method = request.method();
    const json = (body, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
    if (url.origin !== opts.origin) {
      result.deniedExternalRequests.push({ method, url: url.href });
      return route.abort();
    }
    const customerApi = url.pathname.match(/^\/(?:web\/)?api\/customer(\/.*)$/);
    if (customerApi) {
      const endpoint = customerApi[1];
      result.mocks.push({ method, endpoint });
      if (endpoint === '/login' && method === 'POST') {
        assert.deepEqual(request.postDataJSON(), { email: fixture.session.customer.email, password: 'synthetic-browser-password' });
        loggedIn = true;
        return json(fixture.session);
      }
      if (!['GET', 'HEAD'].includes(method)) {
        result.blockedMutations.push({ method, endpoint });
        return route.abort();
      }
      if (endpoint === '/session') return loggedIn ? json(fixture.session) : json({ error: 'authentication_required', message: 'Sign in to continue.' }, 401);
      if (!loggedIn) { result.unexpectedApis.push(endpoint); return json({ error: 'authentication_required' }, 401); }
      if (endpoint === '/workspace') return json(fixture.workspace);
      if (endpoint === '/catalog') return json({ products: [], categories: [], recommendations: [] });
      if (endpoint === '/messages') return json({ threads: [], unread_count: 0, needs_reply_count: 0, is_staff: false });
      const proof = endpoint.match(/^\/website-requests\/([0-9a-f-]{36})\/proofs\/([a-f])$/);
      if (proof && fixture.workspace.website_requests.some((item) => item.public_id === proof[1])) return route.fulfill({ contentType: 'text/html', body: `<!doctype html><title>Synthetic proof ${proof[2]}</title><body style="background:#111;color:#fff;font:20px sans-serif"><h1>SYNTHETIC CONCEPT ${proof[2].toUpperCase()}</h1><p>Browser fixture only. No live proof bytes.</p></body>` });
      result.unexpectedApis.push(endpoint);
      return json({ error: 'unmocked_or_foreign_api' }, 404);
    }
    if (!['GET', 'HEAD'].includes(method)) { result.blockedMutations.push({ method, path: url.pathname }); return route.abort(); }
    // The public login layout loads navigation/catalog chrome in development.
    // Stub those reads explicitly, without reaching its Drupal proxy.
    if (/^\/(?:web\/)?jsonapi\/(?:menu_items\/main|node\/(?:service_page|package_page))$/.test(url.pathname)) {
      result.mocks.push({ method, endpoint: url.pathname });
      return json({ data: [], links: {} });
    }
    if (/^\/(?:web\/)?(?:api|jsonapi|session|oauth)(?:\/|$)/.test(url.pathname) || ['fetch', 'xhr'].includes(request.resourceType())) {
      result.unexpectedApis.push(url.pathname);
      return json({ error: 'unexpected_api_blocked' }, 404);
    }
    result.forwarded.push({ method, url: url.href, type: request.resourceType() });
    return route.continue();
  });
  try {
    const response = await page.goto(opts.origin + DESTINATION, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, 'Frontend must serve the portal SPA route');
    await page.waitForURL((url) => url.pathname === '/login');
    await page.locator('#portal-email').waitFor({ state: 'visible' });
    const login = new URL(page.url());
    const redirectCorrect = login.searchParams.get('redirect') === DESTINATION;
    result.checks.anonymousRedirect = { passed: redirectCorrect, url: login.href, decodedDestination: login.searchParams.get('redirect'), authenticationResponse: 'mocked 401' };
    await page.screenshot({ path: path.join(opts.output, `${result.name}-login.png`) });
    if (!redirectCorrect) {
      // Preserve the failed assertion, but independently exercise login and the
      // proof suite so one redirect defect does not hide the other findings.
      result.redirectMismatch = login.searchParams.get('redirect');
      result.warnings.push('Anonymous redirect failed. Subsequent login checks use an explicitly encoded destination and do not establish end-to-end success.');
      await page.goto(`${opts.origin}/login?redirect=${encodeURIComponent(DESTINATION)}`, { waitUntil: 'domcontentloaded' });
    }
    await page.locator('#portal-email').fill(fixture.session.customer.email);
    await page.locator('#portal-password').fill('synthetic-browser-password');
    await page.getByRole('button', { name: 'Open my portal', exact: true }).click();
    await page.waitForURL((url) => url.pathname === '/portal/' && url.search === new URL(DESTINATION, opts.origin).search);
    result.checks.loginReturn = { passed: true, url: page.url(), loginResponse: 'locally fulfilled; never forwarded' };
    if (wrongAccount) {
      await page.getByRole('alert').filter({ hasText: 'This proof link is not connected to the account signed in as' }).waitFor();
      assert.equal(await page.locator(`#website-request-${TARGET}, #concepts-${TARGET}, #proof-review-${TARGET}`).count(), 0);
      assert.ok(!(await page.locator('body').innerText()).includes(TARGET_NAME));
      assert.equal(await page.locator(`iframe[src*="${TARGET}"], a[href*="website-requests/${TARGET}/proofs/"]`).count(), 0);
      assert.equal(result.mocks.filter((call) => call.endpoint.includes(`/website-requests/${TARGET}/`)).length, 0, 'Foreign request must never be fetched from query alone');
      const unrelatedProjectShown = await page.locator(`#website-request-${OTHER}`).count() > 0;
      if (unrelatedProjectShown) result.warnings.push('Mismatch error is shown, but the current UI also falls back to a different account-owned project. No foreign proof was exposed.');
      result.checks.wrongAccountNoForeignProof = { passed: true, unrelatedProjectShown };
      await page.screenshot({ path: path.join(opts.output, `${result.name}-blocked.png`), fullPage: true });
    } else {
      await ownerLanding(page, result, opts, 'automatic-concepts');
      const review = page.getByRole('link', { name: 'Review 3 directions', exact: false });
      const href = await review.getAttribute('href');
      assert.equal(href, `#proof-review-${TARGET}`);
      assert.equal(await page.locator(`[id="${href.slice(1)}"]`).count(), 1);
      await review.click();
      await page.waitForURL((url) => url.hash === href);
      await page.waitForFunction((id) => { const box = document.getElementById(id)?.getBoundingClientRect(); return box && box.top >= -1 && box.top < 200; }, href.slice(1));
      result.checks.reviewAnchor = { passed: true, href, url: page.url() };
      // Force lazy previews to load, still entirely within the mock boundary.
      for (const direction of ['a', 'b', 'c']) {
        const frame = page.locator(`[data-proof-direction="${direction}"] iframe`);
        await frame.scrollIntoViewIfNeeded();
        await page.frameLocator(`[data-proof-direction="${direction}"] iframe`).getByRole('heading', { name: `SYNTHETIC CONCEPT ${direction.toUpperCase()}` }).waitFor();
      }
      result.checks.threeMockFramesLoaded = { passed: true };
      await page.goto(opts.origin + DESTINATION, { waitUntil: 'domcontentloaded' });
      await ownerLanding(page, result, opts, 'signed-in-reload');
      assert.equal(new URL(page.url()).pathname, '/portal/');
    }
    assert.equal(result.pageErrors.length, 0, 'Unexpected JavaScript error');
    assert.equal(result.blockedMutations.length, 0, 'Unexpected state-changing request attempted');
    assert.equal(result.unexpectedApis.length, 0, 'Unexpected backend request attempted');
    assert.equal(result.mocks.filter((call) => call.method === 'POST').length, 1, 'Only the locally fulfilled mock login may use POST');
    assert.ok(result.forwarded.every((request) => ['GET', 'HEAD'].includes(request.method) && !/^\/(?:web\/)?(?:api|jsonapi|session|oauth)(?:\/|$)/.test(new URL(request.url).pathname)));
    result.cookies = (await context.cookies()).map(({ name, domain }) => ({ name, domain }));
    assert.ok(!result.cookies.some(({ name }) => /^(?:S?SESS|PHPSESSID)/i.test(name)), 'No backend session cookie may be created');
    result.checks.noRealSessionOrMutation = { passed: true };
    assert.equal(result.redirectMismatch, undefined, `Anonymous redirect lost destination: ${result.redirectMismatch}`);
    result.status = 'PASS';
    console.log(`PASS ${result.name}: ${wrongAccount ? 'exact login return; foreign proof absent' : 'exact login return; automatic Concepts scroll/focus; valid review anchor; signed-in reload'}`);
  } catch (error) {
    result.status = 'FAIL';
    result.error = error.stack;
    result.failureUrl = page.url();
    result.failureText = (await page.locator('body').innerText().catch(() => '')).slice(0, 16000);
    await page.screenshot({ path: path.join(opts.output, `${result.name}-failure.png`), fullPage: true }).catch(() => {});
    console.error(`FAIL ${result.name}: ${error.message}`);
  } finally {
    await Promise.allSettled(assetJobs);
    await context.close();
    save();
  }
}

async function main() {
  const opts = options();
  fs.mkdirSync(opts.output, { recursive: true });
  const report = { startedAt: new Date().toISOString(), baseUrl: opts.origin, syntheticRequest: TARGET, destination: DESTINATION, scope: 'Actual frontend with browser-only mocked account/login/workspace/proof APIs. Not backend authorization, real credential, SMTP or production proof validation.', cases: [] };
  const save = () => fs.writeFileSync(path.join(opts.output, 'results.json'), JSON.stringify(report, null, 2));
  let browser;
  try {
    const library = playwright(opts.module);
    report.playwrightModule = library.resolved;
    browser = await library.chromium.launch({ headless: true });
    for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
      for (const wrongAccount of [false, true]) await runCase(browser, opts, viewport, wrongAccount, report, save);
    }
    report.status = report.cases.length === 4 && report.cases.every((item) => item.status === 'PASS') ? 'PASS' : 'FAIL';
  } catch (error) { report.status = 'FAIL'; report.fatal = error.stack; console.error(error); }
  finally {
    if (browser) await browser.close();
    report.completedAt = new Date().toISOString();
    save();
  }
  console.log(`${report.status}: evidence ${path.join(opts.output, 'results.json')}`);
  process.exitCode = report.status === 'PASS' ? 0 : 1;
}

main().catch((error) => { console.error(error); process.exitCode = 1; });
