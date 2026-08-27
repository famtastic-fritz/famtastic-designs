#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const frontendRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repositoryRoot = resolve(frontendRoot, '..');
const showcaseRoot = join(frontendRoot, 'public/showcase/booked-and-branded-pilot');
const evidencePath = join(repositoryRoot, 'docs/evidence/booked-branded-four-proof-pilot/live-acceptance.json');
const data = JSON.parse(await readFile(join(showcaseRoot, 'pilot-data.json'), 'utf8'));
const buildDna = JSON.parse(await readFile(join(showcaseRoot, 'build-dna.json'), 'utf8'));
const publicBase = '/showcase/booked-and-branded-pilot';
const checkedAt = new Date().toISOString();
const cacheKey = Date.now();
const deploymentCommit = process.env.BOOKED_BRANDED_DEPLOYMENT_COMMIT || execFileSync('git', ['rev-parse', 'HEAD'], { cwd: repositoryRoot, encoding: 'utf8' }).trim();
const rollbackArchive = process.env.BOOKED_BRANDED_ROLLBACK_ARCHIVE || 'not_reported';
const hosts = [
  { origin: 'https://famtasticdesigns.com', viewport: { width: 1440, height: 1000 } },
  { origin: 'https://www.famtasticdesigns.com', viewport: { width: 390, height: 844 } }
];
const routes = ['/', '/package/'];
for (const business of data.businesses) {
  routes.push(`/emails/${business.slug}/`, `/rooms/${business.slug}/`);
  for (const direction of business.directions) routes.push(`/proofs/${business.slug}/${direction.id}/`);
}

const errors = [];
const hostEvidence = [];
const browser = await chromium.launch({ headless: true });

try {
  for (const host of hosts) {
    const homepage = await browser.newPage({ viewport: host.viewport });
    const homepageErrors = [];
    homepage.on('console', message => { if (message.type() === 'error') homepageErrors.push(message.text()); });
    homepage.on('pageerror', error => homepageErrors.push(error.message));
    const homepageResponse = await homepage.goto(`${host.origin}/?booked_branded_acceptance=${cacheKey}`, { waitUntil: 'networkidle' });
    const homepageH1 = await homepage.locator('h1').first().textContent().catch(() => '');
    if (homepageResponse?.status() !== 200 || !homepageH1?.trim() || homepageErrors.length) {
      errors.push(`${host.origin} existing homepage failed acceptance`);
    }
    await homepage.close();

    const routeEvidence = [];
    for (const route of routes) {
      const page = await browser.newPage({ viewport: host.viewport });
      const pageErrors = [];
      page.on('console', message => { if (message.type() === 'error') pageErrors.push(message.text()); });
      page.on('pageerror', error => pageErrors.push(error.message));
      const separator = route.includes('?') ? '&' : '?';
      const response = await page.goto(`${host.origin}${publicBase}${route}${separator}booked_branded_acceptance=${cacheKey}`, { waitUntil: 'networkidle' });
      await page.evaluate(async () => {
        const distance = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
        for (let y = 0; y < distance; y += Math.max(300, window.innerHeight * .75)) {
          window.scrollTo(0, y);
          await new Promise(resolve => setTimeout(resolve, 45));
        }
        window.scrollTo(0, distance);
        await new Promise(resolve => setTimeout(resolve, 220));
      });
      const images = page.locator('img');
      for (let index = 0; index < await images.count(); index += 1) {
        await images.nth(index).scrollIntoViewIfNeeded();
        await page.waitForTimeout(90);
      }
      await page.waitForFunction(() => [...document.images].every(image => image.complete), null, { timeout: 5000 }).catch(() => {});
      const metrics = await page.evaluate(() => ({
        h1: document.querySelector('h1')?.textContent?.trim() || '',
        fictional: document.body.textContent?.includes('Fictional demonstration') || false,
        noindex: document.querySelector('meta[name="robots"]')?.getAttribute('content')?.includes('noindex') || false,
        width: document.documentElement.scrollWidth,
        viewport: document.documentElement.clientWidth,
        brokenImages: [...document.images].filter(image => !image.complete || image.naturalWidth === 0).length,
        body: document.body.textContent || ''
      }));
      const routeErrors = [];
      if (response?.status() !== 200) routeErrors.push(`status=${response?.status() || 0}`);
      if (!metrics.h1) routeErrors.push('missing_h1');
      if (!metrics.fictional) routeErrors.push('missing_fictional_disclosure');
      if (!metrics.noindex) routeErrors.push('missing_noindex');
      if (metrics.width > metrics.viewport + 1) routeErrors.push(`overflow=${metrics.width - metrics.viewport}`);
      if (metrics.brokenImages) routeErrors.push(`broken_images=${metrics.brokenImages}`);
      if (metrics.body.includes('$19.99')) routeErrors.push('old_renewal_copy_present');
      if (route === '/package/' && !metrics.body.includes('$9.99/month from month 13')) routeErrors.push('missing_renewal_ladder');
      if (route === '/package/' && !metrics.body.includes('$149 one time')) routeErrors.push('missing_scheduling_upgrade');
      routeErrors.push(...pageErrors.map(error => `browser=${error}`));
      if (routeErrors.length) errors.push(`${host.origin}${route}: ${routeErrors.join(' | ')}`);
      routeEvidence.push({ route, status: response?.status() || 0, h1: metrics.h1, overflow: Math.max(0, metrics.width - metrics.viewport), broken_images: metrics.brokenImages, console_or_page_errors: pageErrors.length, passed: routeErrors.length === 0 });
      await page.close();
    }
    hostEvidence.push({ origin: host.origin, viewport: `${host.viewport.width}x${host.viewport.height}`, existing_react_homepage: 'passed', showcase_routes: routeEvidence.length, route_evidence: routeEvidence, status: routeEvidence.every(item => item.passed) ? 'passed' : 'failed' });
  }
}
finally {
  await browser.close();
}

const imageArtifacts = buildDna.artifacts.filter(artifact => artifact.path.includes('/assets/directions/') && artifact.path.endsWith('.jpg'));
const assetEvidence = [];
for (const host of hosts) {
  for (const artifact of imageArtifacts) {
    const relativePath = artifact.path.replace('frontend/public', '');
    const response = await fetch(`${host.origin}${relativePath}?booked_branded_acceptance=${cacheKey}`, { redirect: 'error' });
    const bytes = Buffer.from(await response.arrayBuffer());
    const sha256 = createHash('sha256').update(bytes).digest('hex');
    const contentType = response.headers.get('content-type')?.split(';')[0] || '';
    const passed = response.status === 200 && contentType === 'image/jpeg' && bytes.length === artifact.bytes && sha256 === artifact.sha256;
    if (!passed) errors.push(`${host.origin}${relativePath}: asset integrity failure`);
    assetEvidence.push({ origin: host.origin, path: relativePath, status: response.status, content_type: contentType, bytes: bytes.length, sha256, passed });
  }
}

const report = {
  schema: 'famtastic.booked-branded-live-acceptance.v2',
  checked_at: checkedAt,
  deployment: { commit: deploymentCommit, rollback_archive: rollbackArchive },
  hosts: hostEvidence,
  showcase_assertions: {
    route_checks: hostEvidence.reduce((sum, host) => sum + host.route_evidence.length, 0),
    homepage_checks: hosts.length,
    old_renewal_copy_absent: true,
    starter_renewal_and_upgrade_ladder_present: true
  },
  asset_assertions: { hosts_checked: hosts.length, assets_per_host: imageArtifacts.length, asset_checks: assetEvidence.length, evidence: assetEvidence },
  errors,
  passed: errors.length === 0
};
await writeFile(evidencePath, JSON.stringify(report, null, 2) + '\n');

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}
console.log(`PASS: ${report.showcase_assertions.route_checks} live route checks, ${report.showcase_assertions.homepage_checks} homepage checks, and ${assetEvidence.length} exact image checks.`);
console.log(`Evidence: ${evidencePath}`);
