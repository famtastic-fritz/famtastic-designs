import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve, join } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const script = resolve(root, 'scripts/package-tighten-up-your-locs-proof.mjs');
const check = (...args) => spawnSync(process.execPath, [script, ...args], { cwd: root, encoding: 'utf8' });

test('historical Locs evidence supports a clearly read-only check', () => {
  const result = check('--check');
  assert.equal(result.status, 0, result.stderr);
  assert.match(result.stdout, /historical/);
  assert.match(result.stdout, /No package written/);
  assert.match(result.stdout, /site-tighten-up-your-locs/);
});

test('retired writer rejects absent, output and mixed check arguments without modifying targets', () => {
  const temporary = mkdtempSync(join(tmpdir(), 'locs-agency-retirement-test-'));
  try {
    const existing = join(temporary, 'existing.json');
    const absent = join(temporary, 'absent.json');
    writeFileSync(existing, 'authored source stays intact\n');
    for (const args of [[], ['--output', absent], ['--campaign=pc-example', '--job=1', '--event=test', `--output=${existing}`], ['--check', `--output=${absent}`]]) {
      const result = check(...args);
      assert.notEqual(result.status, 0);
      assert.match(result.stderr, /RETIRED_WRITE_PATH/);
      assert.equal(readFileSync(existing, 'utf8'), 'authored source stays intact\n');
      assert.equal(existsSync(absent), false);
    }
  } finally {
    rmSync(temporary, { recursive: true });
  }
});

test('agency index contains only the two canonical pointers, not duplicate Locs working source', () => {
  const prefixes = ['customer-apps/tighten-up-your-locs', 'website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12'];
  for (const prefix of prefixes) {
    const files = execFileSync('git', ['ls-files', '--', prefix], { cwd: root, encoding: 'utf8' }).trim().split('\n');
    assert.deepEqual(files, [`${prefix}/README.md`]);
    assert.match(readFileSync(resolve(root, files[0]), 'utf8'), /github\.com\/famtastic-fritz\/site-tighten-up-your-locs/);
  }
});

test('agency source history remains recoverable without rewriting the original revision', () => {
  const original = 'e261a10084f49b7e32ca70e4de63e51f495c60f1';
  for (const path of ['customer-apps/tighten-up-your-locs/application/composer.lock', 'website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/index.html']) {
    assert.ok(execFileSync('git', ['show', `${original}:${path}`], { cwd: root }).length > 0);
  }
});

test('retired working locations keep old ignored/private test state out of accidental staging', () => {
  for (const path of ['customer-apps/tighten-up-your-locs/application/.env', 'customer-apps/tighten-up-your-locs/application/bootstrap/cache/packages.php', 'website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/index.html']) {
    const result = spawnSync('git', ['check-ignore', '--no-index', path], { cwd: root, encoding: 'utf8' });
    assert.equal(result.status, 0, path);
  }
});
