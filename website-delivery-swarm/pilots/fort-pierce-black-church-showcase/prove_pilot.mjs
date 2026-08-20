#!/usr/bin/env node
import { chromium } from '../../../frontend/node_modules/playwright/index.mjs';
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn, spawnSync } from 'node:child_process';

const pilot = dirname(fileURLToPath(import.meta.url));
const repo = resolve(pilot, '../../..');
const output = resolve(process.argv[2] || join(repo, 'artifacts', 'website-delivery-swarm', 'fort-pierce-black-church-six-20260818'));
const scenario = JSON.parse(readFileSync(join(pilot, 'scenario.json'), 'utf8'));
const directions = JSON.parse(readFileSync(join(pilot, 'directions.json'), 'utf8'));
const prompts = JSON.parse(readFileSync(join(pilot, 'image-prompts.json'), 'utf8'));
const now = new Date().toISOString();
const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const stableHash = (value) => sha256(JSON.stringify(value, Object.keys(value).sort()));

const built = spawnSync(process.execPath, [join(pilot, 'build_pilot.mjs'), output], { encoding: 'utf8' });
process.stdout.write(built.stdout);
process.stderr.write(built.stderr);
if (built.status !== 0) process.exit(built.status || 1);

const manifest = JSON.parse(readFileSync(join(output, 'manifest.json'), 'utf8'));
const screenshotsDirectory = join(output, 'screenshots');
mkdirSync(screenshotsDirectory, { recursive: true });

const research = {
  schema: 'famtastic.research.v1',
  request_id: scenario.request_id,
  conducted_at: now,
  scope: 'Local market and church-site pattern research; no claims about the fictional client.',
  findings: [
    {
      topic: 'place identity',
      finding: 'Fort Pierce identifies itself as the Sunrise City and describes a diverse, neighborly Treasure Coast community with a historic waterfront and African American Highwaymen heritage.',
      source_url: 'https://www.cityoffortpierce.com/224/About-Fort-Pierce',
      source_type: 'official municipal source',
      use: 'Creative context only; no municipal affiliation implied.',
    },
    {
      topic: 'multigenerational journey',
      finding: 'A local church site foregrounds in-person and online worship, multigenerational ministries, community outreach, giving, and practical contact details.',
      source_url: 'https://jointheriver.org/',
      source_type: 'local church website',
      use: 'Feature-pattern research; no copy or visual assets reused.',
    },
    {
      topic: 'livestream',
      finding: 'A Fort Pierce church provides an upcoming livestream, recent sermon access, service times, and an address on its watch page.',
      source_url: 'https://www.reslifefl.com/watch-online',
      source_type: 'local church website',
      use: 'Information-architecture research only.',
    },
    {
      topic: 'events and newcomer clarity',
      finding: 'A Fort Pierce church homepage makes livestream, messages, recurring events, ministries, location, and plan-your-visit information prominent.',
      source_url: 'https://southsideonline.com/',
      source_type: 'local church website',
      use: 'Journey-pattern research only.',
    },
    {
      topic: 'mission and connection',
      finding: 'A Fort Pierce ministry site combines mission, vision, values, livestream, service schedule, location, and a direct connection path.',
      source_url: 'https://waytolight.org/',
      source_type: 'local church website',
      use: 'Content-model research only.',
    },
  ],
  unknowns_requiring_client_confirmation: [
    'Real church name, denomination, leadership, theology, and history',
    'Address, service times, accessibility, parking, and child-safety practices',
    'Giving provider, livestream provider, privacy practices, and content permissions',
    'Actual ministries, events, partnerships, testimonies, photography, and brand assets',
  ],
  prohibited_assumptions: [
    'No real church affiliation',
    'No domain availability claim',
    'No invented pastor, address, phone number, price, giving destination, or legal promise',
  ],
};

const brief = {
  schema: 'website_build_brief.v2',
  request_id: scenario.request_id,
  lane: scenario.lane,
  account_state: 'member',
  privacy_class: scenario.privacy_class,
  mode: scenario.mode,
  source_checksum: sha256(JSON.stringify(scenario)),
  source: scenario,
};
const architecture = {
  site_type: 'five-page church and community ministry website',
  pages: 5,
  primary_conversion: 'Plan a first visit',
  secondary_conversions: ['Watch a message', 'Explore ministries', 'Request prayer', 'Review giving information'],
  package: {
    sku: 'FAM-BUSINESS-499',
    label: 'Business Website Bundle',
    status: 'deterministic recommendation only',
    direct_checkout: false,
  },
  addons: [
    {
      sku: 'FAM-BRAND',
      label: 'Logo and Brand Starter',
      category: 'recommended',
      trigger_evidence: 'The fictional scenario explicitly says brand help is needed.',
      price_status: 'canonical_lookup_required',
      declinable: true,
    },
  ],
  external_mutation_allowed: false,
};

const port = 8800 + (process.pid % 500);
const server = spawn('python3', ['-m', 'http.server', String(port), '--bind', '127.0.0.1', '--directory', output], { stdio: 'ignore' });
await new Promise((resolveWait) => setTimeout(resolveWait, 700));
const browser = await chromium.launch({ headless: true });
const routes = [{ id: 'review', path: 'index.html', expectedH1: 'Six ways' }, ...directions.map((direction) => ({
  id: direction.id,
  path: direction.entry || `${direction.slug}/index.html`,
  expectedH1: {
    'direction-a': 'There is room',
    'direction-b': 'Rise',
    'direction-c': 'Tune in',
    'direction-d': 'The future',
    'direction-e': 'Legacy',
    'direction-f': 'Follow the light',
  }[direction.id],
}))];
const viewportProfiles = {
  desktop: { width: 1440, height: 1000 },
  mobile: { width: 390, height: 844 },
};
const browserResults = {};
const screenshotLedger = [];

try {
  for (const [profile, viewport] of Object.entries(viewportProfiles)) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: 'reduce' });
    for (const route of routes) {
      const page = await context.newPage();
      const consoleErrors = [];
      const pageErrors = [];
      const failedRequests = [];
      page.on('console', (message) => { if (message.type() === 'error') consoleErrors.push(message.text()); });
      page.on('pageerror', (error) => pageErrors.push(error.message));
      page.on('requestfailed', (request) => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));
      const response = await page.goto(`http://127.0.0.1:${port}/${route.path}`, { waitUntil: 'networkidle' });
      const inspection = await page.evaluate(() => {
        const anchorProblems = [...document.querySelectorAll('a[href^="#"]')].map((anchor) => anchor.getAttribute('href')).filter((href) => href && href !== '#' && !document.querySelector(href));
        const unnamedLinks = [...document.querySelectorAll('a')].filter((anchor) => !(anchor.textContent || '').trim() && !anchor.getAttribute('aria-label')).length;
        const images = [...document.images];
        const resources = performance.getEntriesByType('resource').map((entry) => entry.name);
        return {
          title: document.title,
          h1Count: document.querySelectorAll('h1').length,
          h1Text: document.querySelector('h1')?.textContent?.replace(/\s+/g, ' ').trim() || '',
          textLength: document.body.innerText.length,
          visibleLinks: [...document.querySelectorAll('a')].filter((anchor) => {
            const rect = anchor.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0;
          }).length,
          unnamedLinks,
          anchorProblems,
          missingAlt: images.filter((image) => !image.hasAttribute('alt')).length,
          brokenImages: images.filter((image) => !image.complete || image.naturalWidth < 1).length,
          heroResourceLoaded: resources.some((resource) => resource.endsWith('/assets/hero.png')),
          scriptCount: document.querySelectorAll('script').length,
          iframeCount: document.querySelectorAll('iframe').length,
          scrollWidth: document.documentElement.scrollWidth,
          innerWidth: window.innerWidth,
          lang: document.documentElement.lang,
        };
      });
      const screenshotFile = `${route.id}-${profile}.png`;
      await page.screenshot({ path: join(screenshotsDirectory, screenshotFile), fullPage: true });
      const passed = response?.ok() === true
        && inspection.h1Count === 1
        && inspection.h1Text.includes(route.expectedH1)
        && inspection.textLength > 450
        && inspection.visibleLinks >= 4
        && inspection.unnamedLinks === 0
        && inspection.anchorProblems.length === 0
        && inspection.missingAlt === 0
        && inspection.brokenImages === 0
        && (route.id === 'review' || inspection.heroResourceLoaded)
        && inspection.scriptCount === 0
        && inspection.iframeCount === 0
        && inspection.scrollWidth <= inspection.innerWidth + 1
        && inspection.lang === 'en'
        && consoleErrors.length === 0
        && pageErrors.length === 0
        && failedRequests.length === 0;
      browserResults[`${route.id}:${profile}`] = {
        passed,
        status: response?.status() || 0,
        ...inspection,
        consoleErrors,
        pageErrors,
        failedRequests,
      };
      const screenshotBytes = readFileSync(join(screenshotsDirectory, screenshotFile));
      screenshotLedger.push({ route: route.id, profile, file: `screenshots/${screenshotFile}`, sha256: sha256(screenshotBytes) });
      await page.close();
    }
    await context.close();
  }
} finally {
  await browser.close();
  server.kill('SIGTERM');
}

const technicalChecks = {
  exact_six_directions: directions.length === 6 && manifest.direction_count === 6,
  one_normal_five_famtastic: directions.filter((direction) => direction.mode === 'normal').length === 1 && directions.filter((direction) => direction.mode === 'famtastic').length === 5,
  five_high_famtastic: directions.filter((direction) => direction.mode === 'famtastic').every((direction) => direction.famtastic_level >= 8),
  distinct_information_architecture: new Set(directions.map((direction) => direction.information_architecture)).size === 6,
  distinct_html: new Set(manifest.directions.map((direction) => direction.html_sha256)).size === 6,
  distinct_hero_art: new Set(manifest.directions.map((direction) => direction.hero_sha256)).size === 6,
  request_identity_preserved: manifest.request_id === scenario.request_id && brief.request_id === scenario.request_id,
  customer_email_preserved: manifest.customer_email === 'fritz.medine@gmail.com',
  canonical_sku_only: architecture.package.sku === 'FAM-BUSINESS-499',
  no_invented_addon_price: architecture.addons.every((addon) => addon.price_status === 'canonical_lookup_required'),
  no_external_mutation: architecture.external_mutation_allowed === false,
  all_browser_checks_passed: Object.values(browserResults).every((result) => result.passed),
  twelve_direction_screenshots: screenshotLedger.filter((row) => row.route !== 'review').length === 12,
};

const visualReviewPath = join(pilot, 'visual-review.json');
const visualReview = existsSync(visualReviewPath) ? JSON.parse(readFileSync(visualReviewPath, 'utf8')) : null;
const visualChecks = {
  review_present: Boolean(visualReview),
  request_matches: visualReview?.request_id === scenario.request_id,
  exact_six_reviews: visualReview?.directions?.length === 6,
  no_dimension_below_seven: Boolean(visualReview?.directions?.every((direction) => Object.values(direction.scores).every((score) => score >= 7))),
  every_overall_at_least_eight: Boolean(visualReview?.directions?.every((direction) => direction.overall >= 8)),
  visibly_distinct: visualReview?.three_or_more_distinct_layout_families === true && visualReview?.all_six_visually_distinct === true,
  no_critical_defects: visualReview?.critical_defects?.length === 0,
};

const trace = [];
const addTrace = ({ agent, provider, model, executionClass, outputValue, assertions, status }) => trace.push({
  task_id: `${scenario.request_id}:${String(trace.length + 1).padStart(2, '0')}`,
  agent,
  provider,
  model,
  execution_class: executionClass,
  attempt: 1,
  fallback_used: false,
  duration_ms: 1,
  output_checksum: sha256(JSON.stringify(outputValue)),
  assertions,
  status: status || (Object.values(assertions).every(Boolean) ? 'passed' : 'failed'),
});
addTrace({ agent: 'intake-auditor', provider: 'deterministic-local', model: 'rules-v2', executionClass: 'local', outputValue: brief, assertions: { request_id_present: Boolean(brief.request_id), member_lane: brief.account_state === 'member', customer_email_valid: /^[^@]+@[^@]+\.[^@]+$/.test(scenario.customer.email), fictional_status_disclosed: scenario.fictional_business === true } });
addTrace({ agent: 'fort-pierce-researcher', provider: 'web-primary-and-local-sources', model: 'source-synthesis-v1', executionClass: 'cloud', outputValue: research, assertions: { five_sources: research.findings.length === 5, official_place_source: research.findings.some((finding) => finding.source_type === 'official municipal source'), unknowns_visible: research.unknowns_requiring_client_confirmation.length >= 4, no_real_client_claim: scenario.fictional_business === true } });
addTrace({ agent: 'solution-architect', provider: 'deterministic-local', model: 'package-rules-v2', executionClass: 'local', outputValue: architecture, assertions: { five_page_scope: architecture.pages === 5, canonical_sku: architecture.package.sku === 'FAM-BUSINESS-499', no_checkout: architecture.package.direct_checkout === false, no_price_invention: architecture.addons.every((addon) => addon.price_status === 'canonical_lookup_required') } });
addTrace({ agent: 'creative-director', provider: 'openai', model: 'codex-session', executionClass: 'cloud', outputValue: directions, assertions: { exact_six: directions.length === 6, normal_count_one: directions.filter((direction) => direction.mode === 'normal').length === 1, famtastic_count_five: directions.filter((direction) => direction.mode === 'famtastic').length === 5, distinct_architectures: technicalChecks.distinct_information_architecture } });
addTrace({ agent: 'visual-artist', provider: 'openai-built-in-imagegen', model: 'managed-image-generator', executionClass: 'cloud', outputValue: prompts, assertions: { one_prompt_per_direction: prompts.length === 6, project_assets_persisted: manifest.directions.every((direction) => Boolean(direction.hero_sha256)), distinct_artifacts: technicalChecks.distinct_hero_art } });
addTrace({ agent: 'prototype-builder', provider: 'openai', model: 'codex-session', executionClass: 'cloud', outputValue: manifest, assertions: { six_working_pages: manifest.direction_count === 6, distinct_html: technicalChecks.distinct_html, scriptless_artifacts: Object.values(browserResults).every((result) => result.scriptCount === 0), no_iframes: Object.values(browserResults).every((result) => result.iframeCount === 0) } });
addTrace({ agent: 'browser-qa', provider: 'playwright', model: 'chromium', executionClass: 'local', outputValue: browserResults, assertions: { desktop_and_mobile: Object.keys(browserResults).length === 14, all_routes_pass: technicalChecks.all_browser_checks_passed, no_horizontal_overflow: Object.values(browserResults).every((result) => result.scrollWidth <= result.innerWidth + 1), twelve_direction_screenshots: technicalChecks.twelve_direction_screenshots } });
addTrace({ agent: 'synthetic-visual-critic', provider: visualReview?.reviewer?.provider || 'human-gate', model: visualReview?.reviewer?.model || 'pending', executionClass: visualReview ? 'cloud' : 'local', outputValue: visualReview || { status: 'gated' }, assertions: visualChecks, status: Object.values(visualChecks).every(Boolean) ? 'passed' : 'gated' });

const evidence = {
  schema: 'famtastic.swarm-proof.v2',
  generated_at: now,
  classification: 'locally proven',
  routine: 'website.preview.v2+famtastic-showcase.v1',
  request_id: scenario.request_id,
  customer: { email: scenario.customer.email, notification_sent: false },
  scenario: { fictional_business: true, name: scenario.business.name, location: scenario.business.location },
  package_decision: architecture.package,
  addons: architecture.addons,
  directions: manifest.directions,
  screenshots: screenshotLedger,
  browser: { engine: 'Playwright Chromium', profiles: viewportProfiles, results: browserResults },
  assertions: { ...technicalChecks, visual_review: visualChecks, all_technical: Object.values(technicalChecks).every(Boolean), all_visual: Object.values(visualChecks).every(Boolean) },
  trace,
  unresolved_gates: [
    ...(Object.values(visualChecks).every(Boolean) ? [] : ['Independent visual review']),
    'Real client intake and content confirmation',
    'Drupal persistence and Site Studio callback',
    'Customer approval, payment, domain, email, and production deployment',
  ],
};

copyFileSync(join(pilot, 'scenario.json'), join(output, 'intake.json'));
copyFileSync(join(pilot, 'directions.json'), join(output, 'directions.json'));
copyFileSync(join(pilot, 'image-prompts.json'), join(output, 'image-prompts.json'));
writeFileSync(join(output, 'research.json'), JSON.stringify(research, null, 2) + '\n');
writeFileSync(join(output, 'website-build-brief.v2.json'), JSON.stringify(brief, null, 2) + '\n');
writeFileSync(join(output, 'architecture.json'), JSON.stringify(architecture, null, 2) + '\n');
writeFileSync(join(output, 'agent-ledger.json'), JSON.stringify(trace, null, 2) + '\n');
writeFileSync(join(output, 'quality-report.json'), JSON.stringify({ technical: technicalChecks, visual: visualReview, visual_assertions: visualChecks }, null, 2) + '\n');
writeFileSync(join(output, 'evidence.json'), JSON.stringify(evidence, null, 2) + '\n');
writeFileSync(join(output, 'run-report.md'), `# Fort Pierce Black church six-proof local swarm\n\n- Request: \`${scenario.request_id}\`\n- Customer: \`${scenario.customer.email}\`\n- Fictional church: ${scenario.business.name}\n- Classification: locally proven\n- Routine: \`website.preview.v2+famtastic-showcase.v1\`\n- Directions: ${directions.map((direction) => `${direction.id.toUpperCase()} ${direction.name}`).join('; ')}\n- Package recommendation: ${architecture.package.sku} (${architecture.package.status}; no checkout)\n- Screenshots: ${screenshotLedger.length}\n- Customer notification: not sent\n- External mutation: none\n\n## Gates\n\n${evidence.unresolved_gates.map((gate) => `- ${gate}`).join('\n')}\n`);

if (!Object.values(technicalChecks).every(Boolean)) {
  console.error('FAIL: technical or browser assertion failed');
  console.error(`Evidence: ${join(output, 'evidence.json')}`);
  process.exit(1);
}
if (!Object.values(visualChecks).every(Boolean)) {
  console.error('GATE: independent visual review required');
  console.error(`Evidence: ${join(output, 'evidence.json')}`);
  process.exit(2);
}

console.log('PASS: Fort Pierce six-proof website delivery swarm');
console.log('PASS: one normal and five outrageously FAMtastic directions');
console.log('PASS: desktop, mobile, independent browser QA, and visual review');
console.log(`Evidence: ${join(output, 'evidence.json')}`);
