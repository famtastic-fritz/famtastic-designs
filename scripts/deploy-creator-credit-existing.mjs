// Invoked by the canonical frontend deployer AFTER its exact-main build.
// Existing public HTML only; never publishes new customers or touches Drupal state.
import { readdirSync, readFileSync, writeFileSync, existsSync, mkdirSync, copyFileSync, lstatSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { addCreatorCredit, sha256, approvedLogo } from './creator-credit.mjs';
const [distArg, productionArg, releaseArg, commit] = process.argv.slice(2);
const dist = resolve(distArg), production = resolve(productionArg), release = resolve(releaseArg);
if (!/^[a-f0-9]{40}$/.test(commit)) throw new Error('Exact committed SHA required');
if (production === '/' || production === dist || release.startsWith(production + '/')) throw new Error('Invalid release boundaries');
approvedLogo();
const inventory = JSON.parse(readFileSync(join(dist, 'creator-credit-build-v1.json')));
const backup = join(release, 'creator-credit-backup');
if (existsSync(backup)) throw new Error('Scoped release already attempted; inspect receipt before retry');
const candidates = [];
const excluded = [];
for (const row of inventory.artifacts) {
  const path = row.path;
  if (!/^[a-zA-Z0-9_./-]+\.html$/.test(path) || path.split('/').includes('..')) throw new Error('Unsafe build path');
  const target = join(production, path);
  if (!existsSync(target)) { excluded.push({ path, reason: 'not-already-live' }); continue; }
  if (!lstatSync(target).isFile()) throw new Error('Non-file live target');
  const before = readFileSync(target, 'utf8');
  const after = row.credit === 'react-app' ? readFileSync(join(dist, path), 'utf8') : addCreatorCredit(before);
  candidates.push({ path, before, after });
}
// Existing unlisted marketing artifacts are versioned from LIVE bytes, never rebuilt from old approvals.
function unlisted(dir) {
  if (!existsSync(dir)) return;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const target = join(dir, entry.name);
    if (entry.isSymbolicLink()) throw new Error('Symlink in live proof tree');
    if (entry.isDirectory()) unlisted(target);
    else if (entry.name.endsWith('.html')) {
      const before = readFileSync(target, 'utf8');
      candidates.push({ path: target.slice(production.length + 1), before, after: addCreatorCredit(before) });
    }
  }
}
unlisted(join(production, 'proofs/unlisted'));
unlisted(join(production, 'proofs/friends-20260918'));
// Back up every exact HTML target before writes. Old artifact evidence stays untouched.
mkdirSync(backup, { recursive: true });
for (const row of candidates) {
  const file = join(backup, row.path);
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, row.before);
}
const receipt = { commit, scope: 'existing-html-and-referenced-react-assets-only', backup, artifacts: candidates.map(({ path, before, after }) => ({ path, before_sha256: sha256(before), after_sha256: sha256(after) })), excluded };
writeFileSync(join(release, 'creator-credit-plan.json'), JSON.stringify(receipt, null, 2) + '\n');
const assets = [...readFileSync(join(dist, 'index.html'), 'utf8').matchAll(/(?:src|href)="\/(assets\/[^"<>]+)"/g)].map(match => match[1]);
for (const asset of assets) {
  if (!/^assets\/[a-zA-Z0-9_.-]+$/.test(asset)) throw new Error('Unsafe compiled asset');
  const target = join(production, asset);
  if (existsSync(target) && sha256(readFileSync(target)) !== sha256(readFileSync(join(dist, asset)))) throw new Error('Immutable asset collision');
  copyFileSync(join(dist, asset), target);
}
for (const row of candidates.sort((a, b) => (a.path === 'index.html') - (b.path === 'index.html'))) {
  const target = join(production, row.path);
  if (sha256(readFileSync(target)) !== sha256(row.before)) throw new Error(`Concurrent live modification: ${row.path}`);
  writeFileSync(target, row.after);
  if (sha256(readFileSync(target)) !== sha256(row.after)) throw new Error(`Readback failed: ${row.path}`);
}
writeFileSync(join(production, '.creator-credit-release.json'), JSON.stringify(receipt, null, 2) + '\n');
console.log(`Scoped creator credit deployed: ${candidates.length} existing HTML files; ${excluded.length} absent files excluded; backup ${backup}`);
