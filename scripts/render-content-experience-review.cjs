const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const root = path.resolve(__dirname, '..');
process.chdir(root);
const dir = '.local-email-preview/content-experience';
const evidence = 'docs/evidence/content-experience-web-basics';
const qa = JSON.parse(fs.readFileSync(`${evidence}/browser-results.json`));
if (qa.status !== 'passed') throw new Error('Browser review checks must pass first');
for (const phase of ['before', 'after']) for (const width of [390, 1440]) {
  if (!fs.existsSync(`${dir}/${phase}-${width}.png`)) throw new Error(`Missing ${phase}-${width} capture`);
}
fs.copyFileSync('scripts/content-experience-review.html', `${dir}/index.html`);
const dnaPath = `${evidence}/build-dna.json`;
const dna = JSON.parse(fs.readFileSync(dnaPath));
dna.classification = 'local_tested_owner_review_pending';
dna.stages[0].result = { status: 'gated', technical: 'passed', visual: 'author inspected; owner review pending' };
dna.stages = dna.stages.filter(s => s.stage_id !== 'browser-qa');
dna.stages.push({ stage_id: 'browser-qa', attempt: 1, capability: 'read-only presentation acceptance', execution: {
  provider: { id: 'local-chromium', transport: 'Playwright' }, model: { status: 'not_applicable' },
  timing: { status: 'partial', completed_at: qa.checkedAt }, cost: { status: 'not_applicable' },
  output: { artifact: `${evidence}/browser-results.json` }
}, result: { status: 'passed', limits: ['not production', 'no customer state writes', 'not independent owner visual approval'] } });
const sources = [
  'frontend/src/components/content-experience/index.jsx',
  'frontend/src/components/content-experience/WebBasicsExperience.jsx',
  'frontend/src/components/content-experience/content-experience.css',
  'frontend/src/components/content-experience/recipes.js',
  'frontend/src/pages/PackagePage.jsx',
  'frontend/src/components/RelatedEducation.jsx',
  'frontend/src/api/drupal.js',
  'frontend/src/utils/jsonApiPagination.js',
  'docs/design/FAMTASTIC-CONTENT-EXPERIENCE.md',
  'scripts/test-content-experience.cjs',
  `${evidence}/browser-results.json`,
];
const screenshots = ['before-390.png','before-1440.png','after-390.png','after-1440.png'];
dna.artifacts = dna.artifacts.filter(a => a.role === 'immutable_source_logo');
for (const file of sources) dna.artifacts.push({ role: 'source_or_acceptance', path: file, sha256: createHash('sha256').update(fs.readFileSync(file)).digest('hex'), rights: 'agency-owned source and sanitized test result' });
// Screenshots remain ignored local artifacts; portable source evidence stays verifiable.
dna.local_review_artifacts = screenshots.map(file => ({ path: `${dir}/${file}`, sha256: createHash('sha256').update(fs.readFileSync(`${dir}/${file}`)).digest('hex'), retention: 'local ignored review output, not part of portable validation' }));
fs.writeFileSync(dnaPath, JSON.stringify(dna, null, 2) + '\n');
console.log(`Review ready: http://127.0.0.1:8765/content-experience/\nBuild DNA updated: ${dnaPath}`);
