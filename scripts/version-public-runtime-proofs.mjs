// Existing public legacy proof URLs only. Original artifact bytes/DB paths stay immutable.
import { readdirSync, readFileSync, writeFileSync, existsSync, lstatSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { addCreatorCredit, sha256 } from './creator-credit.mjs';
const [rootArg, commit, mode] = process.argv.slice(2);
if (!rootArg || (mode && mode !== '--apply')) throw new Error('Usage: root commit [--apply]');
const root = resolve(rootArg);
if (!/^[a-f0-9]{40}$/.test(commit)) throw new Error('Exact committed source required');
const policy = readFileSync(join(root, '.htaccess'), 'utf8');
if (!policy.includes('Require all granted') || /RewriteRule/i.test(policy)) throw new Error('Unexpected legacy root access policy');
const plan = [], excluded = [];
for (const campaign of readdirSync(root, { withFileTypes: true })) {
  if (!campaign.isDirectory() || !/^pc-[a-z0-9-]+$/.test(campaign.name)) continue;
  const base = join(root, campaign.name);
  if (existsSync(join(base, '.htaccess'))) {
    excluded.push({ campaign: campaign.name, reason: 'campaign-access-policy-preserved-controller-presentation' });
    continue;
  }
  for (const direction of ['a', 'b', 'c', 'd', 'e', 'f']) {
    const dir = join(base, direction), original = join(dir, 'index.html');
    if (!existsSync(original)) continue;
    if (!lstatSync(dir).isDirectory() || !lstatSync(original).isFile()) throw new Error('Symlink not allowed');
    if (existsSync(join(dir, '.htaccess'))) throw new Error('Existing direction routing requires review');
    const version = `index.creator-credit-${commit.slice(0, 12)}.html`;
    if (existsSync(join(dir, version))) throw new Error('Version already exists');
    const before = readFileSync(original, 'utf8'), after = addCreatorCredit(before);
    plan.push({ path: `${campaign.name}/${direction}`, original, before, after, version, dir });
  }
}
const receiptPath = join(root, `.creator-credit-versions-${commit.slice(0, 12)}.json`);
if (existsSync(receiptPath)) throw new Error('Versioned release already attempted');
const receipt = { commit, status: 'new-presentation-versions-original-artifacts-unchanged', excluded, artifacts: plan.map(row => ({ path: row.path, version: row.version, original_sha256: sha256(row.before), presentation_sha256: sha256(row.after) })) };
if (mode !== '--apply') {
  console.log(JSON.stringify({ mode: 'read-only', ...receipt }));
  process.exit(0);
}
writeFileSync(receiptPath, JSON.stringify(receipt, null, 2) + '\n', { flag: 'wx' });
for (const row of plan) {
  if (sha256(readFileSync(row.original)) !== sha256(row.before)) throw new Error('Concurrent proof change');
  writeFileSync(join(row.dir, row.version), row.after, { flag: 'wx' });
  writeFileSync(join(row.dir, '.htaccess'), `# Owner creator-credit presentation; original index.html remains immutable.\nRewriteEngine On\nRewriteRule ^(?:index\\.html)?$ ${row.version} [END]\n`, { flag: 'wx' });
  if (sha256(readFileSync(row.original)) !== sha256(row.before)) throw new Error('Original artifact changed');
}
console.log(JSON.stringify({ commit, versioned: plan.length, protected_campaigns_preserved: excluded.length, receipt: receiptPath }));
