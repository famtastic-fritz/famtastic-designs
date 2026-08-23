import { spawn } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const siteDir = path.join(here, 'lab-site');
const evidenceDir = path.join(here, 'evidence');
const screenshotDir = path.join(evidenceDir, 'lab-screenshots');
const port = 41871;
const baseUrl = `http://127.0.0.1:${port}`;
const canonicalUrl = 'https://famtasticdesigns.com/lab/and-if-it-is/';
const liveExperienceUrl = 'https://famtasticdesigns.com/and-if-it-is/';

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
      // Static server is still starting.
    }
    await delay(100);
  }
  throw new Error('Lab static server did not start.');
}

const results = {
  schema: 'famtastic.lab-browser-proof.v1',
  content_id: 'famtastic-lab-and-if-it-is-v1',
  tested_at: new Date().toISOString(),
  browser: 'playwright-chromium',
  profiles: [],
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
    { name: 'desktop-1440', viewport: { width: 1440, height: 1000 } },
    { name: 'phone-390', viewport: { width: 390, height: 844 }, isMobile: true },
  ];

  for (const profile of profiles) {
    const context = await browser.newContext({
      viewport: profile.viewport,
      isMobile: profile.isMobile,
      reducedMotion: 'reduce',
    });
    await context.route('https://www.googletagmanager.com/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* analytics stub for deterministic QA */' });
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

    const facts = await page.evaluate(({ expectedCanonical, expectedExperience }) => {
      const jsonLdNodes = [...document.querySelectorAll('script[type="application/ld+json"]')];
      const jsonLdValid = jsonLdNodes.length === 1 && jsonLdNodes.every((node) => {
        try { JSON.parse(node.textContent); return true; } catch { return false; }
      });
      const intakeLinks = [...document.querySelectorAll('a[data-track="intake"]')];
      const experienceLinks = [...document.querySelectorAll('a[data-track="live-experience"]')];
      const sensitiveQueryKeys = ['email', 'name', 'phone', 'token', 'key', 'code', 'session', 'secret'];
      const intakeQueryFacts = intakeLinks.map((link) => {
        const url = new URL(link.href);
        return {
          path: url.pathname,
          source: url.searchParams.get('source'),
          campaign: url.searchParams.get('campaign'),
          utmSource: url.searchParams.get('utm_source'),
          hasPii: sensitiveQueryKeys.some((key) => url.searchParams.has(key)),
        };
      });

      const trackedLink = intakeLinks.at(-1);
      const stopNavigation = (event) => event.preventDefault();
      trackedLink.addEventListener('click', stopNavigation, { capture: true, once: true });
      trackedLink.click();
      const normalizedDataLayer = (window.dataLayer || []).map((entry) => Array.from(entry));
      const events = normalizedDataLayer.filter((entry) => entry[0] === 'event').map((entry) => ({ name: entry[1], parameters: entry[2] }));
      const firstViewportText = (document.querySelector('.hero')?.innerText || '').toUpperCase();

      return {
        title: document.title,
        h1Count: document.querySelectorAll('h1').length,
        landmarkMainCount: document.querySelectorAll('main').length,
        imageCount: document.images.length,
        missingAltCount: [...document.images].filter((image) => !image.hasAttribute('alt')).length,
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        canonical: document.querySelector('link[rel="canonical"]')?.href,
        ogTitle: document.querySelector('meta[property="og:title"]')?.content,
        ogDescription: document.querySelector('meta[property="og:description"]')?.content,
        ogImage: document.querySelector('meta[property="og:image"]')?.content,
        twitterCard: document.querySelector('meta[name="twitter:card"]')?.content,
        jsonLdValid,
        disclosure: document.body.innerText.includes('Not affiliated with or endorsed by Florida A&M University'),
        timingBoundary: document.body.innerText.includes('Research happened before this clock') && document.body.innerText.includes('not a delivery guarantee'),
        campaignHookVisible: firstViewportText.includes('IS FAMU A CULT?') && firstViewportText.includes('AND IF IT IS?') && firstViewportText.includes('FAM-GOT-DAM-U.'),
        marketingBoundary: document.body.innerText.includes('cannot own customer projects') && document.body.innerText.includes('extraction queue'),
        liveExperienceLinks: experienceLinks.length,
        liveExperienceExact: experienceLinks.every((link) => link.href === expectedExperience),
        intakeLinks: intakeLinks.length,
        intakeQueryFacts,
        pageViewEvent: events.some((event) => event.name === 'page_view' && event.parameters.page_path === '/lab/and-if-it-is/' && event.parameters.content_id === 'famtastic-lab-and-if-it-is-v1'),
        ctaEvent: events.some((event) => event.name === 'cta_clicked' && event.parameters.cta_id === 'intake' && event.parameters.cta_location === 'final'),
        canonicalExact: document.querySelector('link[rel="canonical"]')?.href === expectedCanonical,
        externalBlankRelDefects: [...document.querySelectorAll('a[target="_blank"]')].filter((link) => !link.rel.includes('noopener')).length,
        activeHeaderHeight: Math.round(document.querySelector('[data-header]')?.getBoundingClientRect().height || 0),
      };
    }, { expectedCanonical: canonicalUrl, expectedExperience: liveExperienceUrl });

    const screenshotName = `famtastic-lab-${profile.name}.png`;
    await page.screenshot({ path: path.join(screenshotDir, screenshotName), fullPage: true });
    results.screenshots.push(`evidence/lab-screenshots/${screenshotName}`);
    results.profiles.push({ profile: profile.name, status: response?.status(), ...facts });
    await context.close();
  }

  const sourceFiles = [
    'lab-site/index.html',
    'lab-site/styles.css',
    'lab-site/app.js',
    'lab-site/assets/rattler-lifers-hero.jpg',
    'lab-site/assets/the-lifer-character.jpg',
    'lab-site/assets/famtastic-mark.svg',
  ];
  for (const relativePath of sourceFiles) {
    const bytes = await readFile(path.join(here, relativePath));
    results.hashes[relativePath] = createHash('sha256').update(bytes).digest('hex');
  }

  results.assertions = {
    every_profile_200: results.profiles.every((profile) => profile.status === 200),
    exactly_one_h1_and_main: results.profiles.every((profile) => profile.h1Count === 1 && profile.landmarkMainCount === 1),
    all_images_have_alt_attributes: results.profiles.every((profile) => profile.missingAltCount === 0),
    no_horizontal_overflow: results.profiles.every((profile) => profile.horizontalOverflow === false),
    canonical_metadata_complete: results.profiles.every((profile) => profile.canonicalExact && profile.ogTitle && profile.ogDescription && profile.ogImage && profile.twitterCard === 'summary_large_image'),
    structured_data_valid: results.profiles.every((profile) => profile.jsonLdValid),
    legal_and_timing_boundaries_visible: results.profiles.every((profile) => profile.disclosure && profile.timingBoundary),
    campaign_hook_preserved_in_first_viewport: results.profiles.every((profile) => profile.campaignHookVisible),
    marketing_split_visible: results.profiles.every((profile) => profile.marketingBoundary),
    live_experience_ctas_exact: results.profiles.every((profile) => profile.liveExperienceLinks >= 3 && profile.liveExperienceExact),
    attributed_intake_ctas_without_pii: results.profiles.every((profile) => profile.intakeLinks === 2 && profile.intakeQueryFacts.every((fact) => fact.path === '/start' && fact.source === 'famtastic-lab' && fact.campaign === 'and-if-it-is' && fact.utmSource === 'famtastic_lab' && !fact.hasPii)),
    analytics_page_and_cta_events_wired: results.profiles.every((profile) => profile.pageViewEvent && profile.ctaEvent),
    no_external_rel_defects: results.profiles.every((profile) => profile.externalBlankRelDefects === 0),
    screenshots_exactly_desktop_and_phone: results.screenshots.length === 2,
    source_and_asset_hashes_recorded: Object.keys(results.hashes).length === sourceFiles.length,
    no_console_errors: results.console_errors.length === 0,
    no_page_errors: results.page_errors.length === 0,
    no_failed_requests: results.failed_requests.length === 0,
  };
  results.pass = Object.values(results.assertions).every(Boolean);
} finally {
  await browser?.close();
  server.kill('SIGTERM');
  await writeFile(path.join(evidenceDir, 'lab-browser-results.json'), `${JSON.stringify(results, null, 2)}\n`);
}

console.log(JSON.stringify({ pass: results.pass, assertions: results.assertions, evidence: path.join(evidenceDir, 'lab-browser-results.json') }, null, 2));
process.exit(results.pass ? 0 : 1);
