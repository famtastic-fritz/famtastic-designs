import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const siteDir = path.join(here, 'site');
const evidenceDir = path.join(here, 'evidence');
const screenshotDir = path.join(evidenceDir, 'screenshots');
const port = 41867;
const baseUrl = `http://127.0.0.1:${port}`;

await mkdir(screenshotDir, { recursive: true });

const server = spawn('python3', ['-m', 'http.server', String(port), '--bind', '127.0.0.1', '--directory', siteDir], {
  stdio: ['ignore', 'pipe', 'pipe'],
});

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function waitForServer() {
  for (let attempt = 0; attempt < 40; attempt += 1) {
    try {
      const response = await fetch(`${baseUrl}/index.html`);
      if (response.ok) return;
    } catch {
      // The server needs another moment.
    }
    await delay(100);
  }
  throw new Error('Static proof server did not start.');
}

const results = {
  schema: 'famtastic.browser-proof.v1',
  campaign: 'and_if_it_is_rattler_lifers',
  tested_at: new Date().toISOString(),
  browser: 'playwright-chromium',
  routes: [],
  assertions: {},
  console_errors: [],
  page_errors: [],
  failed_requests: [],
  screenshots: [],
  hashes: {},
  pass: false,
};

let browser;

try {
  await waitForServer();
  browser = await chromium.launch({ headless: true });

  const profiles = [
    { name: 'desktop', viewport: { width: 1440, height: 1000 } },
    { name: 'mobile', viewport: { width: 390, height: 844 }, isMobile: true },
  ];

  for (const profile of profiles) {
    const context = await browser.newContext({ viewport: profile.viewport, isMobile: profile.isMobile, reducedMotion: 'reduce' });
    await context.route('https://www.googletagmanager.com/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* analytics stub for deterministic experience QA */' });
    });
    const page = await context.newPage();
    page.on('console', (message) => {
      if (message.type() === 'error') results.console_errors.push(`${profile.name}: ${message.text()}`);
    });
    page.on('pageerror', (error) => results.page_errors.push(`${profile.name}: ${error.message}`));
    page.on('requestfailed', (request) => results.failed_requests.push(`${profile.name}: ${request.url()} — ${request.failure()?.errorText || 'failed'}`));

    const response = await page.goto(`${baseUrl}/index.html`, { waitUntil: 'networkidle' });
    await page.evaluate(async () => { await document.fonts.ready; });
    await page.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));

    await page.locator('#class-year').fill('1996');
    await page.locator('#bring-back').fill('The games, the band, and coming home to the Hill.');
    await page.locator('[data-roll-form] button[type="submit"]').click();
    const generatedRollCall = await page.locator('[data-roll-output]').textContent();
    const storedRollCall = await page.evaluate(() => localStorage.getItem('and-if-it-is-roll-call-v1'));
    await page.reload({ waitUntil: 'networkidle' });
    const restoredRollCall = await page.locator('[data-roll-output]').textContent();

    const facts = await page.evaluate(() => ({
      title: document.title,
      h1Count: document.querySelectorAll('h1').length,
      imageCount: document.images.length,
      missingAltCount: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
      horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      disclosure: document.body.innerText.includes('Not affiliated with or endorsed by Florida A&M University'),
      campaignHook: document.body.innerText.includes('IS FAMU') && document.body.innerText.includes('A CULT?') && document.body.innerText.includes('And if it is?'),
      privacyBoundary: document.body.innerText.toLowerCase().includes('saved only on this device') && document.body.innerText.toLowerCase().includes('nothing is transmitted'),
      canonical: document.querySelector('link[rel="canonical"]')?.href,
      socialMetadata: Boolean(document.querySelector('meta[property="og:title"]')?.content && document.querySelector('meta[name="twitter:card"]')?.content),
      jsonLdValid: [...document.querySelectorAll('script[type="application/ld+json"]')].length === 1,
      shareControls: document.querySelectorAll('[data-roll-copy], [data-roll-share]').length,
      localCardLinks: [...document.querySelectorAll('a[href^="cards/"]')].map((link) => link.getAttribute('href')),
      blankExternalLinks: [...document.querySelectorAll('a[href^="http"]')].filter((link) => link.target === '_blank' && !link.rel.includes('noopener')).length,
    }));

    const screenshotName = `social-hub-${profile.name}.png`;
    const screenshotPath = path.join(screenshotDir, screenshotName);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    results.screenshots.push(`evidence/screenshots/${screenshotName}`);
    results.routes.push({
      route: '/index.html',
      profile: profile.name,
      status: response?.status(),
      generatedRollCall,
      storedRollCall: Boolean(storedRollCall),
      restoredRollCall,
      ...facts,
    });
    await context.close();
  }

  const cardRoutes = ['01-question.html', '02-lifer.html', '03-proof.html'];
  const context = await browser.newContext({ viewport: { width: 1080, height: 1350 }, reducedMotion: 'reduce', deviceScaleFactor: 1 });
  for (const route of cardRoutes) {
    const page = await context.newPage();
    page.on('console', (message) => {
      if (message.type() === 'error') results.console_errors.push(`${route}: ${message.text()}`);
    });
    page.on('pageerror', (error) => results.page_errors.push(`${route}: ${error.message}`));
    page.on('requestfailed', (request) => results.failed_requests.push(`${route}: ${request.url()} — ${request.failure()?.errorText || 'failed'}`));
    const response = await page.goto(`${baseUrl}/cards/${route}`, { waitUntil: 'networkidle' });
    await page.evaluate(async () => { await document.fonts.ready; });
    await page.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));
    const facts = await page.evaluate(() => ({
      horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      cardWidth: Math.round(document.querySelector('.card')?.getBoundingClientRect().width || 0),
      cardHeight: Math.round(document.querySelector('.card')?.getBoundingClientRect().height || 0),
    }));
    const screenshotName = route.replace('.html', '.png');
    const screenshotPath = path.join(screenshotDir, screenshotName);
    await page.screenshot({ path: screenshotPath });
    results.screenshots.push(`evidence/screenshots/${screenshotName}`);
    results.routes.push({ route: `/cards/${route}`, profile: 'social-1080x1350', status: response?.status(), ...facts });
    await page.close();
  }
  await context.close();

  const localLinks = [...new Set(results.routes.flatMap((route) => route.localCardLinks || []))];
  const linkStatuses = [];
  for (const href of localLinks) {
    const response = await fetch(`${baseUrl}/${href}`);
    linkStatuses.push({ href, status: response.status, ok: response.ok });
  }

  const htmlFiles = ['site/index.html', ...cardRoutes.map((route) => `site/cards/${route}`)];
  for (const relativePath of htmlFiles) {
    const bytes = await readFile(path.join(here, relativePath));
    results.hashes[relativePath] = createHash('sha256').update(bytes).digest('hex');
  }
  for (const relativePath of ['site/assets/rattler-lifers-hero.png', 'site/assets/the-lifer-character.png']) {
    const bytes = await readFile(path.join(here, relativePath));
    results.hashes[relativePath] = createHash('sha256').update(bytes).digest('hex');
  }

  results.assertions = {
    every_route_200: results.routes.every((route) => route.status === 200),
    one_h1_on_hub: results.routes.filter((route) => route.route === '/index.html').every((route) => route.h1Count === 1),
    all_hub_images_have_alt_attributes: results.routes.filter((route) => route.route === '/index.html').every((route) => route.missingAltCount === 0),
    no_horizontal_overflow: results.routes.every((route) => route.horizontalOverflow === false),
    disclosure_visible: results.routes.filter((route) => route.route === '/index.html').every((route) => route.disclosure === true),
    campaign_hook_preserved: results.routes.filter((route) => route.route === '/index.html').every((route) => route.campaignHook === true),
    public_launch_metadata_complete: results.routes.filter((route) => route.route === '/index.html').every((route) => route.canonical === 'https://famtasticdesigns.com/and-if-it-is/' && route.socialMetadata && route.jsonLdValid),
    roll_call_generates_persists_and_restores: results.routes.filter((route) => route.route === '/index.html').every((route) => route.generatedRollCall?.includes('CLASS OF 1996') && route.storedRollCall && route.restoredRollCall === route.generatedRollCall && route.shareControls === 2 && route.privacyBoundary),
    local_card_links_work: linkStatuses.length === 3 && linkStatuses.every((item) => item.ok),
    exact_three_social_cards: cardRoutes.length === 3 && results.routes.filter((route) => route.profile === 'social-1080x1350').length === 3,
    exact_two_generated_assets: Object.keys(results.hashes).filter((key) => key.endsWith('.png') && key.startsWith('site/assets/')).length === 2,
    no_console_errors: results.console_errors.length === 0,
    no_page_errors: results.page_errors.length === 0,
    no_failed_requests: results.failed_requests.length === 0,
  };
  results.link_statuses = linkStatuses;
  results.pass = Object.values(results.assertions).every(Boolean);
} finally {
  await browser?.close();
  server.kill('SIGTERM');
  await writeFile(path.join(evidenceDir, 'browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
}

console.log(JSON.stringify({ pass: results.pass, assertions: results.assertions, evidence: path.join(evidenceDir, 'browser-results.json') }, null, 2));
process.exit(results.pass ? 0 : 1);
