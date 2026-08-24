// FAMtastic customer-portal crawl driver (called by scripts/e2e-portal-links.sh).
// Signs in as the seeded local test customer, visits every portal surface the
// dashboard can render, opens a message thread, walks the projects
// progressive-disclosure flow, and asserts per page:
//   - render OK (expected heading visible)
//   - no fake-affordance pattern (bold/arrow labels outside real anchors/buttons)
//   - no synthetic strings in customer-visible content
//   - no horizontal overflow past the viewport marker
// Notices must be context-scoped: none may survive a navigation.
// Emits one JSON result object on stdout. Local-only; cleans nothing here.
import { chromium } from '@playwright/test';
import fs from 'node:fs';

const BASE = process.argv[2] || 'http://127.0.0.1:8937';
const EMAIL = process.argv[3];
const PASSWORD = process.argv[4];
const OUT = process.argv[5];

const SYNTHETIC = /mailbox proof|controlled customer reply|synthetic|example\.test|e2e|FAM-2608/i;

// Reachable sections come from GROUPS in CustomerPortalDashboard.jsx. The rest
// are rendered code paths with no affordance pointing at them today; we still
// probe them via ?section= and report UNREACHABLE instead of failing silently.
const NAV_SECTIONS = ['home', 'services', 'projects', 'messages', 'billing', 'account'];
const DEAD_SECTIONS = ['activity', 'performance', 'support', 'learn', 'faq', 'grow', 'referrals', 'settings'];
const LABELS = { home: 'Home', services: 'Services', projects: 'Projects', messages: 'Messages', billing: 'Billing', account: 'Account', activity: 'Activity', performance: 'Performance', support: 'Support', learn: 'Learn', faq: 'FAQ', grow: 'Grow', referrals: 'Referrals', settings: 'Settings' };

const results = [];

function verdict(entry) {
  if (entry.failures.length) return 'FAIL';
  if (entry.warnings.length) return 'WARN';
  return 'ok';
}

async function scanPage(page, name, { expectedHeading } = {}) {
  const entry = { name, failures: [], warnings: [], artifacts: {} };
  const h1 = await page.locator('.portal-main > header h1').first().textContent().catch(() => null);
  if (expectedHeading && h1?.trim() !== expectedHeading) {
    entry.failures.push(`heading "${h1?.trim()}" != "${expectedHeading}"`);
  }
  // Synthetic strings in text nodes and form values/placeholders.
  const hits = await page.evaluate((pattern) => {
    const re = new RegExp(pattern, 'i');
    const found = [];
    const texts = [document.body.innerText];
    document.querySelectorAll('input,textarea').forEach((el) => {
      if (el.value) texts.push(el.value);
      if (el.placeholder) texts.push(el.placeholder);
    });
    for (const t of texts) {
      const m = t.match(re);
      if (m) found.push(m[0]);
    }
    return [...new Set(found)];
  }, SYNTHETIC.source);
  if (hits.length) entry.failures.push(`synthetic strings: ${hits.join(', ')}`);

  // Fake affordances: bold/arrow labels that look clickable but sit outside a real anchor/button.
  const fakes = await page.evaluate(() => [...document.querySelectorAll('.portal-main b, .portal-main strong')]
    .filter((el) => /[→↗]/.test(el.textContent))
    .filter((el) => !el.closest('a,button,[role="button"],[role="link"],summary,label'))
    .map((el) => `${el.tagName.toLowerCase()}:"${el.textContent.trim().slice(0, 50)}"`));
  if (fakes.length) entry.failures.push(`fake affordances: ${fakes.join('; ')}`);

  // Horizontal overflow past the viewport width marker.
  const sizes = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
  if (sizes.content > sizes.viewport + 1) {
    entry.failures.push(`horizontal overflow: content ${sizes.content}px > viewport ${sizes.viewport}px`);
  }

  // Stale notices: a notice surviving navigation is not context-scoped.
  const notices = await page.locator('.portal-notice').allTextContents();
  if (notices.length) entry.warnings.push(`notice visible: "${notices[0].trim().slice(0, 60)}"`);

  entry.artifacts.heading = h1?.trim();
  entry.verdict = verdict(entry);
  results.push(entry);
  console.error(`[${entry.verdict.toUpperCase()}] ${name}${entry.failures.length ? ' :: ' + entry.failures.join(' | ') : ''}${entry.warnings.length ? ' :: ' + entry.warnings.join(' | ') : ''}`);
}

async function goSection(page, id) {
  // Nav labels may carry an unread-count badge ("Messages 3"), so match by prefix.
  await page.getByRole('button', { name: new RegExp(`^${LABELS[id]}\\b`) }).first().click();
  await page.waitForTimeout(150);
  await page.waitForFunction(() => !document.querySelector('.portal-state'), null, { timeout: 5000 }).catch(() => {});
}

async function main() {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const errors = [];

  try {
    // Sign in through the real login flow (session cookie set server-side).
    await page.goto(`${BASE}/login?redirect=${encodeURIComponent('/portal')}`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email').fill(EMAIL);
    await page.getByLabel('Password').fill(PASSWORD);
    await page.locator('form button[type="submit"], form button:not([type])').first().click();
    await page.waitForURL('**/portal**', { timeout: 15000 });
    await page.waitForSelector('.portal-app', { timeout: 15000 });
    await page.waitForFunction(() => !document.querySelector('.portal-state'), null, { timeout: 10000 });

    // Reachable sections via the nav.
    for (const id of NAV_SECTIONS) {
      await goSection(page, id);
      await scanPage(page, `portal:${id}`, { expectedHeading: LABELS[id] });
    }

    // Projects deep-link variant used by campaigns (?start=website).
    await page.goto(`${BASE}/portal?start=website`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.portal-request-form', { timeout: 15000 });
    const stepnote = await page.locator('.portal-form-stepnote').count();
    const entryStart = { name: 'portal:?start=website', failures: [], warnings: [], artifacts: {}, verdict: '' };
    if (!stepnote) entryStart.failures.push('progressive step-1 intro missing');
    else entryStart.artifacts.step1 = true;
    // Walk the conversion flow: three fields -> save draft -> full interview reveals.
    await page.getByLabel('Request name').fill('Portal Crawl Studio website');
    await page.getByLabel('What are we building?').selectOption('landing_page');
    await page.getByLabel('What should this website accomplish?').fill('Capture quote requests from local homeowners.');
    await page.getByRole('button', { name: /Save draft/i }).click();
    await page.waitForSelector('.portal-form-group', { timeout: 15000 });
    const groups = await page.locator('.portal-form-group').count();
    if (groups < 5) entryStart.failures.push(`expected >=5 grouped fieldsets after draft save, saw ${groups}`);
    else entryStart.artifacts.groupsAfterDraft = groups;
    const sticky = await page.locator('.portal-sticky-actions').count();
    if (!sticky) entryStart.failures.push('sticky save bar missing');
    const sizes = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
    if (sizes.content > sizes.viewport + 1) entryStart.failures.push(`horizontal overflow: ${sizes.content}>${sizes.viewport}`);
    const synth = await page.evaluate((pattern) => new RegExp(pattern, 'i').test(document.body.innerText), SYNTHETIC.source);
    if (synth) entryStart.failures.push('synthetic string visible');
    entryStart.verdict = verdict(entryStart);
    results.push(entryStart);
    console.error(`[${entryStart.verdict.toUpperCase()}] portal:?start=website :: groups=${groups}`);

    // Messages with an open thread.
    await goSection(page, 'messages');
    const threadButton = page.locator('.portal-thread-list button').first();
    if (await threadButton.count()) {
      await threadButton.click();
      await page.waitForSelector('.portal-conversation ol li', { timeout: 10000 });
      await scanPage(page, 'portal:messages(open-thread)');
    } else {
      await scanPage(page, 'portal:messages(open-thread)', {});
      results[results.length - 1].failures.push('no thread available to open (seed missing?)');
      results[results.length - 1].verdict = 'FAIL';
    }

    // Dead sections: rendered code paths with no reachable affordance.
    for (const id of DEAD_SECTIONS) {
      await page.goto(`${BASE}/portal?section=${id}`, { waitUntil: 'domcontentloaded' });
      await page.waitForFunction(() => !document.querySelector('.portal-state'), null, { timeout: 8000 }).catch(() => {});
      const h1 = (await page.locator('.portal-main > header h1').first().textContent().catch(() => '') || '').trim();
      const entry = { name: `portal:${id}(deep-link)`, failures: [], warnings: [], artifacts: {}, verdict: '' };
      if (h1 !== LABELS[id]) {
        entry.warnings.push(`UNREACHABLE: no nav/affordance renders this section (deep link ignored, shows "${h1}")`);
      } else {
        await scanInto(entry, page);
      }
      entry.verdict = verdict(entry);
      results.push(entry);
      console.error(`[${entry.verdict.toUpperCase()}] ${entry.name}${entry.warnings.length ? ' :: ' + entry.warnings.join(' | ') : ''}`);
    }

    // Client portal (token-scoped prospect command center).
    const clientToken = process.env.FAMTASTIC_CRAWL_CLIENT_TOKEN;
    if (clientToken) {
      await page.goto(`${BASE}/portal/${encodeURIComponent(clientToken)}`, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('.cp-page, .cp-state', { timeout: 15000 });
      await page.waitForTimeout(600);
      const business = process.env.FAMTASTIC_CRAWL_CLIENT_BUSINESS || '';
      const body = await page.evaluate(() => document.body.innerText);
      const cpEntry = { name: '/portal/:token', failures: [], warnings: [], artifacts: {}, verdict: '' };
      if (business && !body.includes(business)) cpEntry.failures.push(`business name "${business}" not rendered`);
      if (/This portal link is no longer active/.test(body)) cpEntry.failures.push('token rejected by client portal');
      if (SYNTHETIC.test(body)) cpEntry.failures.push('synthetic string visible');
      const cpSizes = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
      if (cpSizes.content > cpSizes.viewport + 1) cpEntry.failures.push(`horizontal overflow: ${cpSizes.content}>${cpSizes.viewport}`);
      cpEntry.verdict = verdict(cpEntry);
      results.push(cpEntry);
      console.error(`[${cpEntry.verdict.toUpperCase()}] /portal/:token`);
    }
  } catch (error) {
    errors.push(String(error));
  } finally {
    // Persist results BEFORE closing the browser: a hard crash in close()
    // must not lose the audit evidence.
    writeResults();
    try { await browser.close(); } catch { /* already gone */ }
    writeResults();
  }

  process.exitCode = results.filter((r) => r.verdict === 'FAIL').length + errors.length > 0 ? 1 : 0;

  function writeResults() {
    if (!OUT) return;
    const failCount = results.filter((r) => r.verdict === 'FAIL').length + errors.length;
    const warnCount = results.filter((r) => r.verdict === 'WARN').length;
    fs.writeFileSync(OUT, JSON.stringify({ ok: failCount === 0, failCount, warnCount, errors, results }, null, 2));
  }

  async function scanInto(entry, pg) {
    const h1 = (await pg.locator('.portal-main > header h1').first().textContent().catch(() => '') || '').trim();
    entry.artifacts.heading = h1;
    const hits = await pg.evaluate((pattern) => new RegExp(pattern, 'i').test(document.body.innerText), SYNTHETIC.source);
    if (hits) entry.failures.push('synthetic string visible');
    const sizes = await pg.evaluate(() => ({ viewport: document.documentElement.clientWidth, content: document.documentElement.scrollWidth }));
    if (sizes.content > sizes.viewport + 1) entry.failures.push(`horizontal overflow: ${sizes.content}>${sizes.viewport}`);
  }
}

main();
