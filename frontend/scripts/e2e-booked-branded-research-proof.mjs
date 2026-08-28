import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, '../..');
const evidenceRoot = join(repoRoot, 'docs/evidence/booked-branded-research-proof');
const baseUrl = process.env.FAMTASTIC_RESEARCH_PROOF_BASE_URL || 'http://127.0.0.1:4180/showcase/booked-and-branded-pilot';

const pages = [
  ['research-proof-lab', '/research-proof-lab/'],
  ['crown-ledger', '/research-proof-lab/templates/crown-ledger/'],
  ['coil-ritual', '/research-proof-lab/templates/coil-ritual/'],
  ['barrio-signal', '/research-proof-lab/templates/barrio-signal/'],
  ['salt-glass', '/research-proof-lab/templates/salt-glass/']
];

const viewports = [
  ['desktop', { width: 1440, height: 1000 }],
  ['mobile', { width: 390, height: 844 }]
];

await mkdir(evidenceRoot, { recursive: true });
const browser = await chromium.launch();
const evidence = {
  schema: 'famtastic.browser-qa.v1',
  captured_at: new Date().toISOString(),
  base_url: baseUrl,
  provider: 'playwright-local',
  pages: []
};

try {
  for (const [pageId, route] of pages) {
    for (const [viewportId, viewport] of viewports) {
      const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
      const page = await context.newPage();
      const errors = [];
      page.on('console', message => {
        if (message.type() === 'error') errors.push(`console: ${message.text()}`);
      });
      page.on('pageerror', error => errors.push(`page: ${error.message}`));
      page.on('requestfailed', request => errors.push(`request: ${request.url()} (${request.failure()?.errorText || 'failed'})`));

      const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
      if (!response || response.status() !== 200) throw new Error(`${pageId}/${viewportId} returned ${response?.status() || 'no response'}`);
      await page.addStyleTag({ content: '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;transition-duration:0s!important;scroll-behavior:auto!important}' });

      const checks = await page.evaluate(({ isTemplate }) => {
        const badImages = [...document.images]
          .filter(image => !image.complete || image.naturalWidth === 0)
          .map(image => image.getAttribute('src'));
        const missingHashTargets = [...document.querySelectorAll('a[href^="#"]')]
          .map(anchor => anchor.getAttribute('href'))
          .filter(href => href && href.length > 1 && !document.querySelector(href));
        const componentIds = [...document.querySelectorAll('[data-component-id]')]
          .map(element => element.getAttribute('data-component-id'));
        return {
          title: document.title,
          h1_count: document.querySelectorAll('h1').length,
          bad_images: badImages,
          missing_hash_targets: [...new Set(missingHashTargets)],
          horizontal_overflow_px: Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
          component_count: componentIds.length,
          unique_component_count: new Set(componentIds).size,
          template_contract_present: !isTemplate || Boolean(document.querySelector('[data-page-template-id][data-page-template-version][data-recipe-signature]')),
          decision_count: document.querySelectorAll('[data-decision-id]').length
        };
      }, { isTemplate: pageId !== 'research-proof-lab' });

      if (checks.h1_count !== 1) errors.push(`expected one h1, got ${checks.h1_count}`);
      if (checks.bad_images.length) errors.push(`broken images: ${checks.bad_images.join(', ')}`);
      if (checks.missing_hash_targets.length) errors.push(`missing hash targets: ${checks.missing_hash_targets.join(', ')}`);
      if (checks.horizontal_overflow_px > 1) errors.push(`horizontal overflow: ${checks.horizontal_overflow_px}px`);
      if (!checks.template_contract_present) errors.push('missing page-template contract');
      if (pageId !== 'research-proof-lab' && checks.unique_component_count < 11) errors.push(`expected at least 11 unique components, got ${checks.unique_component_count}`);

      const screenshot = `${pageId}-${viewportId}.jpg`;
      await page.screenshot({ path: join(evidenceRoot, screenshot), type: 'jpeg', quality: 84, fullPage: true });
      evidence.pages.push({ page_id: pageId, route, viewport: viewportId, dimensions: viewport, screenshot, checks, errors });
      await context.close();
    }
  }
} finally {
  await browser.close();
}

evidence.result = evidence.pages.every(page => page.errors.length === 0) ? 'passed' : 'failed';
await writeFile(join(evidenceRoot, 'browser-qa.json'), `${JSON.stringify(evidence, null, 2)}\n`);

if (evidence.result !== 'passed') {
  const failures = evidence.pages.filter(page => page.errors.length).map(page => `${page.page_id}/${page.viewport}: ${page.errors.join('; ')}`);
  throw new Error(failures.join('\n'));
}

console.log(`PASS: ${evidence.pages.length} browser captures, ${pages.length} pages, ${viewports.length} viewports.`);
console.log(`Evidence: ${join(evidenceRoot, 'browser-qa.json')}`);
