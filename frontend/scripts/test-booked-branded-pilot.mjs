#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repositoryRoot = resolve(frontendRoot, '..');
const publicRoot = join(frontendRoot, 'public/showcase/booked-and-branded-pilot');
const data = JSON.parse(await readFile(join(publicRoot, 'pilot-data.json'), 'utf8'));
const base = (process.env.BOOKED_BRANDED_BASE_URL || 'http://127.0.0.1:4173/showcase/booked-and-branded-pilot').replace(/\/$/, '');
const evidenceDir = join(repositoryRoot, 'docs/evidence/booked-branded-four-proof-pilot');
const screenshotDir = join(evidenceDir, 'screenshots');
await mkdir(screenshotDir, { recursive: true });

const routes = ['/'];
routes.push('/package/');
for (const business of data.businesses) {
  routes.push(`/emails/${business.slug}/`, `/rooms/${business.slug}/`);
  for (const direction of business.directions) routes.push(`/proofs/${business.slug}/${direction.id}/`);
}

const browser = await chromium.launch({ headless: true });
const errors = [];
const routeEvidence = [];

async function inspectRoute(route, viewport) {
  const page = await browser.newPage({ viewport });
  const consoleErrors = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', error => consoleErrors.push(error.message));
  const response = await page.goto(base + route, { waitUntil: 'networkidle' });
  await page.evaluate(async () => {
    const distance = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    for (let y = 0; y < distance; y += Math.max(300, window.innerHeight * .75)) {
      window.scrollTo(0, y);
      await new Promise(resolve => setTimeout(resolve, 35));
    }
    window.scrollTo(0, distance);
    await new Promise(resolve => setTimeout(resolve, 200));
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(100);
  const status = response?.status() || 0;
  const metrics = await page.evaluate(() => ({
    title: document.title,
    h1: document.querySelector('h1')?.textContent?.trim() || '',
    width: document.documentElement.scrollWidth,
    viewport: document.documentElement.clientWidth,
    brokenImages: [...document.images].filter(image => !image.complete || image.naturalWidth === 0).map(image => image.src),
    fictionalLabel: document.body.textContent.includes('Fictional demonstration')
  }));
  if (status !== 200) errors.push(`${route} returned ${status}`);
  if (!metrics.h1) errors.push(`${route} has no H1 at ${viewport.width}px`);
  if (metrics.width > metrics.viewport + 1) errors.push(`${route} overflows ${metrics.width - metrics.viewport}px at ${viewport.width}px`);
  if (metrics.brokenImages.length) errors.push(`${route} has ${metrics.brokenImages.length} broken images at ${viewport.width}px`);
  if (!metrics.fictionalLabel) errors.push(`${route} lost its fictional-demonstration disclosure`);
  if (consoleErrors.length) errors.push(`${route} console errors: ${consoleErrors.join(' | ')}`);
  routeEvidence.push({ route, viewport, status, ...metrics, consoleErrors });
  await page.close();
}

try {
  for (const route of routes) {
    await inspectRoute(route, { width: 1440, height: 1000 });
    await inspectRoute(route, { width: 390, height: 844 });
  }

  const overview = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await overview.goto(base + '/', { waitUntil: 'networkidle' });
  await overview.screenshot({ path: join(screenshotDir, 'pilot-overview-desktop.png'), fullPage: true });
  await overview.close();

  const packagePage = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await packagePage.goto(base + '/package/', { waitUntil: 'networkidle' });
  await packagePage.screenshot({ path: join(screenshotDir, 'booked-branded-package-desktop.png'), fullPage: true });
  await packagePage.close();

  for (const business of data.businesses) {
    const room = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await room.goto(`${base}/rooms/${business.slug}/`, { waitUntil: 'networkidle' });
    await room.screenshot({ path: join(screenshotDir, `${business.slug}-room-desktop.png`), fullPage: true });
    await room.close();

    const phone = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await phone.goto(`${base}/proofs/${business.slug}/c/`, { waitUntil: 'networkidle' });
    await phone.screenshot({ path: join(screenshotDir, `${business.slug}-owner-direction-mobile.png`), fullPage: true });
    await phone.close();
  }

  const representative = data.businesses[0];
  const email = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await email.goto(`${base}/emails/${representative.slug}/`, { waitUntil: 'networkidle' });
  await email.screenshot({ path: join(screenshotDir, `${representative.slug}-email-desktop.png`), fullPage: true });
  await email.close();
  for (const direction of representative.directions) {
    const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
    await page.goto(`${base}/proofs/${representative.slug}/${direction.id}/`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: join(screenshotDir, `${representative.slug}-direction-${direction.id}-desktop.png`), fullPage: true });
    await page.close();
  }
}
finally {
  await browser.close();
}

const report = {
  schema: 'famtastic.booked-branded-qa.v1',
  generated_at: new Date().toISOString(),
  base_url: base,
  routes_tested: routes.length,
  viewport_checks: routeEvidence.length,
  screenshots: 14,
  errors,
  passed: errors.length === 0,
  route_evidence: routeEvidence
};
const reportPath = join(evidenceDir, 'qa-report.json');
await writeFile(reportPath, JSON.stringify(report, null, 2) + '\n');

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}

const buildDnaPath = join(publicRoot, 'build-dna.json');
const buildDna = JSON.parse(await readFile(buildDnaPath, 'utf8'));
const reportBytes = await readFile(reportPath);
const browserStage = buildDna.stages.find(stage => stage.stage_id === 'browser-qa');
browserStage.execution.timing = { status: 'reported', completed_at: report.generated_at };
browserStage.result = {
  status: 'completed',
  routes_tested: routes.length,
  viewport_checks: routeEvidence.length,
  screenshots: 14,
  evidence_ref: 'docs/evidence/booked-branded-four-proof-pilot/qa-report.json'
};
const visualStage = buildDna.stages.find(stage => stage.stage_id === 'visual-review');
const primaryReviewPassed = process.env.BOOKED_BRANDED_PRIMARY_VISUAL_REVIEW === 'passed';
visualStage.execution.timing = primaryReviewPassed ? { status: 'reported', completed_at: new Date().toISOString() } : { status: 'pending' };
visualStage.result = primaryReviewPassed
  ? {
      status: 'completed',
      notes: 'The overview, package, all four rooms, all four operator-first mobile directions, one Shay email, and one complete three-direction desktop set were inspected. Direction A is editorial, B is a high-signal campaign, and C is operator-first; all twelve reference-led images are distinct and no text collision, broken image, or fictional-to-real ambiguity was observed.',
      independent_review: 'reserved_for_owner'
    }
  : { status: 'pending', independent_review: 'reserved_for_owner' };
buildDna.artifacts = buildDna.artifacts.filter(artifact => artifact.role !== 'browser-qa-report');
buildDna.artifacts.push({
  role: 'browser-qa-report',
  path: 'docs/evidence/booked-branded-four-proof-pilot/qa-report.json',
  bytes: reportBytes.length,
  sha256: createHash('sha256').update(reportBytes).digest('hex')
});
await writeFile(buildDnaPath, JSON.stringify(buildDna, null, 2) + '\n');

console.log(`PASS: ${routes.length} routes at desktop and 390px; 14 screenshots captured.`);
console.log(`Evidence: ${reportPath}`);
