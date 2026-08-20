#!/usr/bin/env node
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join, relative, resolve, sep } from 'node:path';

const sha256 = (bytes) => createHash('sha256').update(bytes).digest('hex');
const posix = (path) => path.split(sep).join('/');
const readJson = (path) => JSON.parse(readFileSync(path, 'utf8'));
const [outputArg, expectedEmail, ...packageArgs] = process.argv.slice(2);

if (!outputArg || !expectedEmail || packageArgs.length < 1) {
  console.error('Usage: prove-six-direction-batch.mjs <output-directory> <expected-email> <proof-package> [proof-package ...]');
  process.exit(2);
}

const output = resolve(outputArg);
const packages = packageArgs.map((path) => resolve(path));
const failures = [];
const packageResults = [];
const allHtmlHashes = [];

for (const packageDirectory of packages) {
  const manifestPath = join(packageDirectory, 'manifest.json');
  if (!existsSync(manifestPath)) {
    failures.push(`${packageDirectory}: manifest.json missing`);
    continue;
  }
  const manifest = readJson(manifestPath);
  const directions = Array.isArray(manifest.directions) ? manifest.directions : [];
  const bands = { restrained: 0, medium: 0, ultra: 0 };
  const directionResults = [];
  for (const direction of directions) {
    const level = Number(direction.famtastic_level);
    const band = level <= 3 ? 'restrained' : level <= 7 ? 'medium' : 'ultra';
    bands[band] += 1;
    const htmlPath = join(packageDirectory, String(direction.entry || ''));
    const htmlExists = existsSync(htmlPath);
    const htmlHash = htmlExists ? sha256(readFileSync(htmlPath)) : '';
    if (htmlHash) allHtmlHashes.push(htmlHash);
    const screenshots = ['desktop', 'mobile'].map((profile) => join(packageDirectory, 'screenshots', `${direction.id}-${profile}.png`));
    directionResults.push({
      id: direction.id,
      name: direction.name,
      famtastic_level: level,
      creative_band: band,
      html_exists: htmlExists,
      html_sha256: htmlHash,
      screenshots_exist: screenshots.every(existsSync),
    });
  }
  const qaCandidates = ['qa-report.json', 'quality-report.json', 'evidence/qa-report.json'].map((path) => join(packageDirectory, path));
  const qaPath = qaCandidates.find(existsSync);
  const qa = qaPath ? readJson(qaPath) : null;
  const visualCandidates = ['visual-review.json', 'evidence/visual-review.json'].map((path) => join(packageDirectory, path));
  const visualPath = visualCandidates.find(existsSync);
  const visual = visualPath ? readJson(visualPath) : qa?.visual || null;
  const evidencePath = join(packageDirectory, 'evidence.json');
  const evidence = existsSync(evidencePath) ? readJson(evidencePath) : null;
  const qaPass = qa ? !JSON.stringify(qa).includes('"passed": false') && !JSON.stringify(qa).includes('"overall_pass": false') : false;
  const visualPass = visual
    ? visual.reviewer?.independent === true
      && visual.directions?.length === 6
      && visual.directions.every((item) => Number(item.overall) >= 8 && Object.values(item.scores || {}).every((score) => Number(score) >= 7))
      && visual.critical_defects?.length === 0
    : false;
  const checks = {
    exact_six: directions.length === 6 && Number(manifest.direction_count) === 6,
    correct_mix: bands.restrained === 1 && bands.medium === 1 && bands.ultra === 4,
    expected_customer: String(manifest.customer_email || '').toLowerCase() === expectedEmail.toLowerCase(),
    request_id_present: Boolean(manifest.request_id),
    distinct_information_architecture: new Set(directions.map((item) => item.information_architecture)).size === 6,
    all_html_exists: directionResults.every((item) => item.html_exists),
    all_screenshots_exist: directionResults.every((item) => item.screenshots_exist),
    package_html_unique: new Set(directionResults.map((item) => item.html_sha256)).size === 6,
    qa_pass: qaPass,
    visual_review_pass: visualPass,
  };
  for (const [check, passed] of Object.entries(checks)) if (!passed) failures.push(`${basename(packageDirectory)}: ${check}`);
  packageResults.push({
    package: posix(relative(process.cwd(), packageDirectory)),
    request_id: manifest.request_id,
    customer_email: manifest.customer_email,
    classification: manifest.classification || evidence?.classification || null,
    bands,
    checks,
    directions: directionResults,
  });
}

const requestIds = packageResults.map((item) => item.request_id).filter(Boolean);
const batchChecks = {
  all_requested_packages_loaded: packageResults.length === packages.length,
  unique_request_ids: new Set(requestIds).size === packageResults.length,
  simultaneous_projects_supported_by_fixture: packageResults.length > 1 && new Set(packageResults.map((item) => item.customer_email)).size === 1,
  all_direction_html_unique: new Set(allHtmlHashes).size === allHtmlHashes.length,
  all_packages_locally_proven_or_better: packageResults.every((item) => ['locally proven', 'production proven'].includes(item.classification)),
};
for (const [check, passed] of Object.entries(batchChecks)) if (!passed) failures.push(`batch: ${check}`);

const report = {
  schema: 'famtastic.six-direction-batch-evidence.v1',
  generated_at: new Date().toISOString(),
  classification: failures.length ? 'failed' : 'locally proven',
  expected_customer_email: expectedEmail,
  project_count: packageResults.length,
  direction_count: packageResults.reduce((sum, item) => sum + item.directions.length, 0),
  batch_checks: batchChecks,
  packages: packageResults,
  failures,
};
mkdirSync(output, { recursive: true });
writeFileSync(join(output, 'batch-evidence.json'), JSON.stringify(report, null, 2) + '\n');
const links = packageResults.map((item) => `<li><a href="${posix(relative(output, resolve(item.package)))}/index.html">${basename(item.package)}</a> — ${item.bands.restrained} restrained, ${item.bands.medium} medium, ${item.bands.ultra} ultra</li>`).join('\n');
writeFileSync(join(output, 'index.html'), `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FAMtastic three-project benchmark</title><style>body{font:18px/1.6 system-ui;margin:0;background:#0b0712;color:#fff}main{max-width:980px;margin:auto;padding:clamp(24px,6vw,80px)}h1{font-size:clamp(42px,8vw,88px);line-height:.95}a{color:#ffcf40}li{margin:18px 0;padding:22px;border:1px solid #6b4d82;border-radius:20px;background:#171020}.status{color:${failures.length ? '#ff8f8f' : '#70f0a0'}}</style><main><p>FAMtastic Designs internal benchmark</p><h1>Three businesses.<br>18 real directions.</h1><p class="status">${failures.length ? `FAILED — ${failures.length} unresolved gates` : 'LOCALLY PROVEN — all batch gates passed'}</p><p>One customer identity, three unique project request IDs, and six preserved website directions per project.</p><ol>${links}</ol><p>Internal proof review only. No customer email or production mutation was performed by this benchmark.</p></main></html>`);
console.log(`${failures.length ? 'FAIL' : 'PASS'}: ${packageResults.length} projects, ${report.direction_count} directions, ${failures.length} failures`);
console.log(`Evidence: ${join(output, 'batch-evidence.json')}`);
if (failures.length) process.exit(1);
