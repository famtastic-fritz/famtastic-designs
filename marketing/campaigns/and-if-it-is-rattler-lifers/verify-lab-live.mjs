import { createHash } from 'node:crypto';
import { writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.join(here, 'evidence');
const screenshotDir = path.join(evidenceDir, 'lab-screenshots');
const liveUrl = 'https://famtasticdesigns.com/lab/and-if-it-is/';
const experienceUrl = 'https://famtasticdesigns.com/and-if-it-is/';

await mkdir(screenshotDir, { recursive: true });

const results = {
  schema: 'famtastic.lab-production-smoke.v1',
  tested_at: new Date().toISOString(),
  url: liveUrl,
  session: 'anonymous_fresh_browser_context',
  profiles: [],
  console_errors: [],
  page_errors: [],
  failed_requests: [],
  assertions: {},
  pass: false,
};

const browser = await chromium.launch({ headless: true });
try {
  for (const profile of [
    { name: 'desktop-1440', viewport: { width: 1440, height: 1000 } },
    { name: 'phone-390', viewport: { width: 390, height: 844 }, isMobile: true },
  ]) {
    const context = await browser.newContext({ viewport: profile.viewport, isMobile: profile.isMobile, reducedMotion: 'reduce' });
    await context.route('https://www.googletagmanager.com/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* analytics stub for production smoke */' });
    });
    const page = await context.newPage();
    page.on('console', (message) => {
      if (message.type() === 'error') results.console_errors.push(`${profile.name}: ${message.text()}`);
    });
    page.on('pageerror', (error) => results.page_errors.push(`${profile.name}: ${error.message}`));
    page.on('requestfailed', (request) => results.failed_requests.push(`${profile.name}: ${request.url()} — ${request.failure()?.errorText || 'failed'}`));

    const response = await page.goto(liveUrl, { waitUntil: 'networkidle' });
    await page.evaluate(async () => { await document.fonts.ready; });
    await page.waitForFunction(() => [...document.images].every((image) => image.complete && image.naturalWidth > 0));
    const facts = await page.evaluate(({ expectedUrl, expectedExperience }) => ({
      title: document.title,
      canonical: document.querySelector('link[rel="canonical"]')?.href,
      h1Count: document.querySelectorAll('h1').length,
      imageCount: document.images.length,
      allImagesLoaded: [...document.images].every((image) => image.naturalWidth > 0),
      horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      disclosureVisible: document.body.innerText.includes('Not affiliated with or endorsed by Florida A&M University'),
      campaignHookVisible: ['IS FAMU A CULT?', 'AND IF IT IS?', 'FAM-GOT-DAM-U.'].every((line) => (document.querySelector('.hero')?.innerText || '').toUpperCase().includes(line)),
      experienceExact: [...document.querySelectorAll('a[data-track="live-experience"]')].every((link) => link.href === expectedExperience),
      intakeAttributed: [...document.querySelectorAll('a[data-track="intake"]')].every((link) => {
        const url = new URL(link.href);
        return url.origin === 'https://famtasticdesigns.com' && url.pathname === '/start' && url.searchParams.get('source') === 'famtastic-lab' && url.searchParams.get('campaign') === 'and-if-it-is';
      }),
      canonicalExact: document.querySelector('link[rel="canonical"]')?.href === expectedUrl,
    }), { expectedUrl: liveUrl, expectedExperience: experienceUrl });

    const screenshotName = `famtastic-lab-live-${profile.name}.png`;
    const screenshotPath = path.join(screenshotDir, screenshotName);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    const screenshotBytes = await import('node:fs/promises').then(({ readFile }) => readFile(screenshotPath));
    results.profiles.push({
      profile: profile.name,
      status: response?.status(),
      finalUrl: page.url(),
      screenshot: `evidence/lab-screenshots/${screenshotName}`,
      screenshot_sha256: createHash('sha256').update(screenshotBytes).digest('hex'),
      ...facts,
    });
    await context.close();
  }

  const [experienceResponse, intakeResponse] = await Promise.all([
    fetch(experienceUrl, { redirect: 'follow' }),
    fetch('https://famtasticdesigns.com/start?source=famtastic-lab&campaign=and-if-it-is', { redirect: 'follow' }),
  ]);

  results.destinations = {
    live_experience: { status: experienceResponse.status, final_url: experienceResponse.url },
    intake: { status: intakeResponse.status, final_url: intakeResponse.url },
  };
  results.assertions = {
    anonymous_profiles_return_200: results.profiles.every((profile) => profile.status === 200),
    canonical_and_url_exact: results.profiles.every((profile) => profile.canonicalExact && profile.finalUrl === liveUrl),
    one_h1_and_all_images_loaded: results.profiles.every((profile) => profile.h1Count === 1 && profile.allImagesLoaded && profile.imageCount >= 3),
    no_horizontal_overflow: results.profiles.every((profile) => !profile.horizontalOverflow),
    disclosure_visible: results.profiles.every((profile) => profile.disclosureVisible),
    campaign_hook_preserved_in_first_viewport: results.profiles.every((profile) => profile.campaignHookVisible),
    experience_and_intake_links_exact: results.profiles.every((profile) => profile.experienceExact && profile.intakeAttributed),
    destinations_reachable: experienceResponse.ok && intakeResponse.ok,
    production_screenshots_recorded: results.profiles.length === 2 && results.profiles.every((profile) => profile.screenshot_sha256),
    no_console_errors: results.console_errors.length === 0,
    no_page_errors: results.page_errors.length === 0,
    no_failed_requests: results.failed_requests.length === 0,
  };
  results.pass = Object.values(results.assertions).every(Boolean);
} finally {
  await browser.close();
  await writeFile(path.join(evidenceDir, 'lab-live-results.json'), `${JSON.stringify(results, null, 2)}\n`);
}

console.log(JSON.stringify({ pass: results.pass, assertions: results.assertions, evidence: path.join(evidenceDir, 'lab-live-results.json') }, null, 2));
process.exit(results.pass ? 0 : 1);
