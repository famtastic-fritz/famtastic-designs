/** Generate local review and finalize this rollout's existing Build DNA, not its parent. */
const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const root = path.resolve(__dirname, '..'); process.chdir(root);
const dir = '.local-email-preview/content-rollout';
const evidence = 'docs/evidence/content-experience-rollout';
const sha = p => createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const qa = JSON.parse(fs.readFileSync(`${evidence}/browser-results.json`));
const regression = JSON.parse(fs.readFileSync(`${evidence}/web-basics-regression.json`));
if (qa.status !== 'passed' || regression.status !== 'passed') throw new Error('Both browser suites must pass');
const shots = [];
for (const name of ['packages', 'package', 'services', 'solution', 'about', 'contact']) for (const width of [390, 1440]) {
  for (const phase of ['before', 'after']) for (const suffix of ['', '-hero', ...(phase === 'after' ? ['-detail'] : [])]) {
    const file = `${dir}/${phase}-${name}-${width}${suffix}.png`;
    if (!fs.existsSync(file)) throw new Error(`Missing capture: ${file}`); shots.push(file);
  }
}
for (const phase of ['before', 'after']) {
  const capture = JSON.parse(fs.readFileSync(`${dir}/${phase}.json`));
  if (capture.length !== 12 || capture.some(c => c.overflow || c.errors.length)) throw new Error(`Invalid ${phase} capture`);
}
fs.copyFileSync('scripts/content-families-review.html', `${dir}/index.html`);
const dnaPath = `${evidence}/build-dna.json`; const dna = JSON.parse(fs.readFileSync(dnaPath));
dna.classification = 'owner_approved_direction_local_tested_family_review_ready';
dna.stages[0].result = { status: 'gated', technical: 'passed', visual: 'author inspected desktop/mobile; expanded owner review pending', release: 'not authorized in this milestone' };
dna.stages = dna.stages.filter(s => s.stage_id !== 'browser-qa');
// Preserve observed iteration history instead of relabeling the first attempt a pass.
for (const [attempt, status, note] of [
  [1, 'failed', 'Overly exact focus assertion expected 2px; existing visible focus was 3px. Assertion now enforces at least2px and adds actual keyboard entry.'],
  [2, 'failed', 'Intermittent networkidle timeout during repeated public CMS reads. Final suite reuses freshly fetched snapshots/cached anonymous GETs; screenshot capture remains live read-only.'],
  [3, 'passed', '68 cases passed before the final short-price typography refinement; superseded by attempt4. Intermediate report not retained separately.'],
]) dna.stages.push({ stage_id: 'browser-qa', attempt, capability: 'local presentation acceptance iteration', execution: {
  provider: { id: 'local-chromium', transport: 'Playwright' }, model: { status: 'not_applicable' },
  timing: { status: 'not_recorded_per_attempt' }, cost: { status: 'not_applicable' },
}, result: { status, note, record_timing: 'reconstructed from same-turn tool outputs at handoff' } });
dna.stages.push({ stage_id: 'browser-qa', attempt: 4, capability: 'family presentation and non-sending mocked-form acceptance', execution: {
  provider: { id: 'local-chromium', transport: 'Playwright' }, model: { status: 'not_applicable' },
  timing: { status: 'partial', completed_at: qa.checkedAt }, cost: { status: 'not_applicable' },
  output: { artifact: `${evidence}/browser-results.json` }
}, result: { status: 'passed', limits: ['not production', 'no real customer writes', 'no provider delivery evidence', 'not full accessibility certification'] } });
const sources = [
  ...fs.readdirSync('frontend/src/components/content-experience').map(f => `frontend/src/components/content-experience/${f}`),
  ...['PackagePage', 'PackagesHubPage', 'ServicePage', 'ServicesHubPage', 'AliasPage', 'ContactPage'].map(f => `frontend/src/pages/${f}.jsx`),
  'frontend/src/components/v1/ContactForm.jsx', 'frontend/src/components/v1/FAQAccordion.jsx',
  'frontend/src/components/RelatedEducation.jsx', 'docs/design/FAMTASTIC-CONTENT-EXPERIENCE.md',
  'scripts/test-content-families.cjs', 'scripts/test-content-experience.cjs', 'scripts/capture-content-families.cjs',
  'scripts/content-families-review.html', 'scripts/render-content-families-review.cjs',
  `${evidence}/browser-results.json`, `${evidence}/web-basics-regression.json`,
];
dna.artifacts = dna.artifacts.filter(a => a.role === 'immutable_source_logo');
for (const file of sources) dna.artifacts.push({ role: 'source_or_acceptance', path: file, sha256: sha(file), rights: 'agency-owned source and sanitized test result' });
dna.local_review_artifacts = shots.map(file => ({ path: file, sha256: sha(file), retention: 'ignored local review artifact, not portable source validation' }));
fs.writeFileSync(dnaPath, JSON.stringify(dna, null, 2) + '\n');
console.log(`Review ready: http://127.0.0.1:8765/content-rollout/\nBuild DNA: ${dnaPath}`);
