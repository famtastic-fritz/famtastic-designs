import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const root = mkdtempSync(join(tmpdir(), 'fd-credit-runtime-test-'));
const commit = 'a'.repeat(40);
const original = '<html><body><footer>Existing demo footer</footer></body></html>';
try {
  writeFileSync(join(root, '.htaccess'), 'Require all granted\n');
  for (const name of ['pc-public', 'pc-private']) {
    mkdirSync(join(root, name, 'a'), { recursive: true });
    writeFileSync(join(root, name, 'a/index.html'), original);
  }
  writeFileSync(join(root, 'pc-private/.htaccess'), 'Require all denied\n');
  const run = (...args) => execFileSync(process.execPath, ['scripts/version-public-runtime-proofs.mjs', root, commit, ...args], { encoding: 'utf8' });
  const plan = JSON.parse(run());
  assert.equal(plan.artifacts.length, 1);
  assert.equal(plan.excluded.length, 1);
  assert.equal(existsSync(join(root, 'pc-public/a/.htaccess')), false);
  const result = JSON.parse(run('--apply'));
  assert.equal(result.versioned, 1);
  for (const name of ['pc-public', 'pc-private']) assert.equal(readFileSync(join(root, name, 'a/index.html'), 'utf8'), original);
  const rendered = readFileSync(join(root, 'pc-public/a/index.creator-credit-aaaaaaaaaaaa.html'), 'utf8');
  assert.match(rendered, /Existing demo footer/);
  assert.match(rendered, /famtastic-designs-logo-v1.png/);
  assert.equal(existsSync(join(root, 'pc-private/a/.htaccess')), false);
  assert.match(readFileSync(join(root, 'pc-public/a/.htaccess'), 'utf8'), /RewriteRule/);
  assert.throws(() => run('--apply'), /Existing direction routing/);
  console.log('Runtime proof versions: dry-run, preservation, protected exclusion, routing and retry gates passed');
} finally { rmSync(root, { recursive: true }); }
