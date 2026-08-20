import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const evidenceDir = path.join(here, 'evidence');
const screenshotDir = path.join(evidenceDir, 'screenshots');
const liveUrl = 'https://famtasticdesigns.com/and-if-it-is/';
const labUrl = 'https://famtasticdesigns.com/lab/and-if-it-is/';

await mkdir(screenshotDir, { recursive: true });

const results = {
  schema: 'famtastic.public-experience-production-proof.v1',
  tested_at: new Date().toISOString(),
  url: liveUrl,
  profiles: [],
  card_routes: [],
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
    await context.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: 'https://famtasticdesigns.com' });
    await context.route('https://www.googletagmanager.com/**', async (route) => {
      await route.fulfill({ status: 200, contentType: 'application/javascript', body: '/* analytics stub for production experience QA */' });
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

    await page.locator('#class-year').fill('1996');
    await page.locator('#bring-back').fill('The games, the band, and coming home to the Hill.');
    await page.locator('[data-roll-form] button[type="submit"]').click();
    const generated = await page.locator('[data-roll-output]').textContent();
    const stored = await page.evaluate(() => localStorage.getItem('and-if-it-is-roll-call-v1'));
    await page.locator('[data-roll-copy]').click();
    const copied = await page.evaluate(() => navigator.clipboard.readText());
    await page.reload({ waitUntil: 'networkidle' });
    const restored = await page.locator('[data-roll-output]').textContent();

    const facts = await page.evaluate(({ expectedUrl, expectedLab }) => {
      const heroText = (document.querySelector('.hero')?.innerText || '').toUpperCase();
      const jsonLd = [...document.querySelectorAll('script[type="application/ld+json"]')];
      return {
        final_url: location.href,
        canonical: document.querySelector('link[rel="canonical"]')?.href,
        h1_count: document.querySelectorAll('h1').length,
        main_count: document.querySelectorAll('main').length,
        image_count: document.images.length,
        all_images_loaded: [...document.images].every((image) => image.naturalWidth > 0),
        horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        campaign_hook_visible: heroText.includes('IS FAMU') && heroText.includes('A CULT?') && heroText.includes('AND IF IT IS?'),
        disclosure_visible: document.body.innerText.includes('Not affiliated with or endorsed by Florida A&M University'),
        privacy_boundary_visible: document.body.innerText.toLowerCase().includes('saved only on this device') && document.body.innerText.toLowerCase().includes('nothing is transmitted'),
        metadata_complete: document.querySelector('link[rel="canonical"]')?.href === expectedUrl && Boolean(document.querySelector('meta[property="og:title"]')?.content) && document.querySelector('meta[name="twitter:card"]')?.content === 'summary_large_image',
        json_ld_valid: jsonLd.length === 1 && jsonLd.every((node) => { try { JSON.parse(node.textContent); return true; } catch { return false; } }),
        lab_link_exact: [...document.querySelectorAll('a[data-track="lab-dna"]')].some((link) => link.href === expectedLab),
      };
    }, { expectedUrl: liveUrl, expectedLab: labUrl });

    const screenshotName = `and-if-it-is-live-${profile.name}.png`;
    const screenshotPath = path.join(screenshotDir, screenshotName);
    await page.screenshot({ path: screenshotPath, fullPage: true });
    const screenshotBytes = await readFile(screenshotPath);
    results.profiles.push({
      profile: profile.name,
      status: response?.status(),
      screenshot: `evidence/screenshots/${screenshotName}`,
      screenshot_sha256: createHash('sha256').update(screenshotBytes).digest('hex'),
      roll_call_generated: generated,
      roll_call_stored: Boolean(stored),
      roll_call_copied: copied,
      roll_call_restored: restored,
      ...facts,
    });
    await context.close();
  }

  for (const route of ['cards/01-question.html', 'cards/02-lifer.html', 'cards/03-proof.html']) {
    const response = await fetch(new URL(route, liveUrl));
    results.card_routes.push({ route, status: response.status, ok: response.ok });
  }

  results.assertions = {
    anonymous_desktop_and_phone_return_200: results.profiles.every((profile) => profile.status === 200 && profile.final_url === liveUrl),
    semantic_and_visual_basics_pass: results.profiles.every((profile) => profile.h1_count === 1 && profile.main_count === 1 && profile.all_images_loaded && profile.image_count >= 3 && !profile.horizontal_overflow),
    campaign_experience_preserved: results.profiles.every((profile) => profile.campaign_hook_visible && profile.disclosure_visible),
    launch_metadata_and_lab_dna_link_pass: results.profiles.every((profile) => profile.metadata_complete && profile.json_ld_valid && profile.lab_link_exact),
    roll_call_generates_persists_copies_and_restores: results.profiles.every((profile) => profile.roll_call_generated?.includes('CLASS OF 1996') && profile.roll_call_stored && profile.roll_call_copied === profile.roll_call_generated && profile.roll_call_restored === profile.roll_call_generated && profile.privacy_boundary_visible),
    all_three_social_cards_reachable: results.card_routes.length === 3 && results.card_routes.every((route) => route.ok),
    production_screenshots_recorded: results.profiles.length === 2 && results.profiles.every((profile) => profile.screenshot_sha256),
    no_console_errors: results.console_errors.length === 0,
    no_page_errors: results.page_errors.length === 0,
    no_failed_requests: results.failed_requests.length === 0,
  };
  results.pass = Object.values(results.assertions).every(Boolean);
} finally {
  await browser.close();
  await writeFile(path.join(evidenceDir, 'experience-live-results.json'), `${JSON.stringify(results, null, 2)}\n`);
}

console.log(JSON.stringify({ pass: results.pass, assertions: results.assertions, evidence: path.join(evidenceDir, 'experience-live-results.json') }, null, 2));
process.exit(results.pass ? 0 : 1);
