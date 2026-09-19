#!/usr/bin/env node
/**
 * Run: node --test scripts/promote-local-proof-regression.test.mjs
 * Exercises --apply OFFLINE: synthetic files only, isolated PATH, mocked SSH/SCP.
 * Never imports into Drupal, reads customer bundles, or executes a remote command.
 */
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {createHash} from 'node:crypto';
import {
  accessSync, constants, mkdirSync, mkdtempSync, readFileSync, realpathSync,
  rmSync, symlinkSync, writeFileSync,
} from 'node:fs';
import {tmpdir} from 'node:os';
import {delimiter, dirname, join, resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
import {before, test} from 'node:test';

const scriptPath = join(dirname(fileURLToPath(import.meta.url)), 'promote-local-proof-godaddy.sh');
const source = readFileSync(scriptPath, 'utf8');
const target = 'regression@fixture.invalid';
const eventId = 'offline-promote-regression';
const campaignId = 'pc-offline-promote-regression';
const jobId = `local-${'0'.repeat(32)}`;
// Valid 1x1 solid-color test images, created independently of customer artwork.
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAAPoAAAD6AG1e1JrAAAADElEQVQImWNI61gFAALwAZmgOO/iAAAAAElFTkSuQmCC', 'base64');
const jpeg = Buffer.from('/9j/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAL/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAAAAAAAAAAAAAAAAAAAABf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhEDEQA/ALANCH//2Q==', 'base64');
const sha256 = bytes => createHash('sha256').update(bytes).digest('hex');

function executable(name) {
  for (const directory of (process.env.PATH || '').split(delimiter)) {
    if (!directory) continue;
    const candidate = resolve(directory, name);
    try {
      accessSync(candidate, constants.X_OK);
      return realpathSync(candidate);
    } catch { /* Try the next explicitly named utility. */ }
  }
  throw new Error(`Missing test prerequisite: ${name}`);
}

const mockTransport = `#!/usr/bin/env node
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const tool = path.basename(process.argv[1]);
const args = process.argv.slice(2);
const capture = process.env.FAM_PROOF_TEST_CAPTURE;
assert.ok(capture, 'test capture directory required');
fs.appendFileSync(path.join(capture, 'calls.jsonl'), JSON.stringify({tool, args}) + '\\n');
if (tool === 'ssh') {
  assert.equal(args.length, 3);
  assert.equal(args[0], '-T');
  assert.equal(args[1], 'regression@fixture.invalid');
  // Deliberately DO NOT evaluate args[2]. No child process, shell or socket here.
} else if (tool === 'scp') {
  assert.equal(args.length, 3);
  assert.equal(args[0], '-q');
  assert.match(args[2], /^regression@fixture\\.invalid:\\.config\\/famtastic\\/proof-inbox\\/offline-promote-regression-[a-f0-9]{64}\\.json$/);
  assert.equal(path.basename(args[1]), 'payload.json');
  assert.match(path.basename(path.dirname(args[1])), /^famtastic-proof-promotion\\./);
  fs.copyFileSync(args[1], path.join(capture, 'payload.json'));
} else {
  throw new Error('Unexpected mocked transport: ' + tool);
}
`;

function runOffline(scriptSource) {
  // Prevent a future absolute SSH/SCP invocation from bypassing the PATH shims.
  assert.doesNotMatch(scriptSource, /\S*\/(?:ssh|scp)(?:\s|$)/m);
  const scratch = mkdtempSync(join(tmpdir(), 'famtastic-promote-regression-'));
  try {
    const bin = join(scratch, 'bin');
    const capture = join(scratch, 'capture');
    const scripts = join(scratch, 'isolated-repo', 'scripts');
    const bundle = join(scratch, 'synthetic-bundle');
    for (const directory of [bin, capture, scripts, bundle]) mkdirSync(directory, {recursive: true});

    // No inherited PATH fallback and no credentials/environment carried through.
    for (const utility of ['bash', 'dirname', 'jq', 'openssl', 'base64', 'rg', 'mktemp', 'rm', 'wc', 'tr', 'awk', 'mv']) {
      symlinkSync(executable(utility), join(bin, utility));
    }
    symlinkSync(process.execPath, join(bin, 'node'));
    for (const tool of ['ssh', 'scp']) writeFileSync(join(bin, tool), mockTransport, {mode: 0o755});
    const isolatedScript = join(scripts, 'promote-local-proof-godaddy.sh');
    writeFileSync(isolatedScript, scriptSource, {mode: 0o755});
    writeFileSync(join(bundle, 'manifest.json'), JSON.stringify({
      campaign_id: campaignId, job_id: jobId, event_id: eventId,
      provider: 'offline_regression', agent_name: 'automated_test',
    }));
    for (const direction of ['a', 'b', 'c']) {
      const directory = join(bundle, direction);
      mkdirSync(join(directory, 'assets'), {recursive: true});
      writeFileSync(join(directory, 'index.html'), `<!doctype html><html lang="en"><title>Offline fixture ${direction}</title><h1>Synthetic proof ${direction}</h1></html>`);
      writeFileSync(join(directory, 'thumbnail.png'), png);
      const assets = ['hero', 'detail'].map(asset_id => ({asset_id, relative_path: `${asset_id}.jpg`, media_type: 'image/jpeg'}));
      for (const asset of assets) writeFileSync(join(directory, 'assets', asset.relative_path), jpeg);
      writeFileSync(join(directory, 'assets.json'), JSON.stringify(assets));
    }
    const result = spawnSync(join(bin, 'bash'), [isolatedScript, bundle, '--apply'], {
      cwd: scratch,
      env: {
        PATH: bin, LANG: 'C', LC_ALL: 'C',
        FAMTASTIC_SSH_TARGET: target, FAMTASTIC_REMOTE_ROOT: 'fixture-webroot',
        FAM_PROOF_TEST_CAPTURE: capture,
      },
      encoding: 'utf8', timeout: 30_000,
    });
    assert.ifError(result.error);
    assert.equal(result.status, 0, `Offline --apply failed:\n${result.stdout}\n${result.stderr}`);
    const bytes = readFileSync(join(capture, 'payload.json'));
    const calls = readFileSync(join(capture, 'calls.jsonl'), 'utf8').trim().split('\n').map(line => JSON.parse(line));
    assert.deepEqual(calls.map(call => call.tool), ['ssh', 'scp', 'ssh']);
    assert.equal(calls[1].args[2], `${target}:.config/famtastic/proof-inbox/${eventId}-${sha256(bytes)}.json`);
    return {payload: JSON.parse(bytes), calls, checksum: sha256(bytes)};
  } finally {
    // Only this exact mkdtemp directory; the promotion script cleans its own temp.
    rmSync(scratch, {recursive: true, force: true});
  }
}

function assertMediaTypes({payload}) {
  assert.equal(payload.campaign_id, campaignId);
  assert.equal(payload.job_id, jobId);
  assert.equal(payload.event_id, eventId);
  assert.deepEqual(payload.variants.map(v => v.direction_id), ['a', 'b', 'c']);
  for (const variant of payload.variants) {
    assert.equal(variant.thumbnail_media_type, 'image/png', `${variant.direction_id}: PNG thumbnail MIME must survive JPEG asset loop`);
    assert.deepEqual(Buffer.from(variant.thumbnail_base64, 'base64'), png);
    assert.equal(variant.assets.length, 2);
    for (const asset of variant.assets) {
      assert.equal(asset.media_type, 'image/jpeg');
      assert.deepEqual(Buffer.from(asset.base64, 'base64'), jpeg);
      assert.equal(asset.sha256, sha256(jpeg));
    }
  }
}

function assertCliPhp({calls, checksum}) {
  const command = calls[2].args[2];
  assert.match(command, /&& \/usr\/local\/bin\/php vendor\/bin\/drush\.php famtastic:proof-local-import /, 'remote import must invoke explicit CLI PHP');
  assert.ok(command.includes('cd "$HOME/fixture-webroot"'));
  assert.ok(command.includes(`--confirm='${campaignId}' --checksum='${checksum}'`));
}

let promoted;
before(() => { promoted = runOffline(source); });

test('three PNG thumbnail MIME types survive multiple JPEG assets under mocked --apply', () => {
  assertMediaTypes(promoted);
});

test('captured remote importer invokes explicit CLI PHP with the scoped payload checksum', () => {
  assertCliPhp(promoted);
});

test('regression guard detects thumbnail MIME overwritten by the JPEG asset loop', () => {
  const mutant = source.replace('--arg media_type "$thumbnail_media_type"', '--arg media_type "$media_type"');
  assert.notEqual(mutant, source, 'MIME mutation must exercise the production argument');
  const result = runOffline(mutant);
  assert.throws(() => assertMediaTypes(result), /PNG thumbnail MIME must survive JPEG asset loop/);
});

test('regression guard detects fallback to the drush shebang instead of CLI PHP', () => {
  const mutant = source.replace('/usr/local/bin/php vendor/bin/drush.php', 'vendor/bin/drush');
  assert.notEqual(mutant, source, 'PHP mutation must exercise the production command');
  const result = runOffline(mutant);
  assert.throws(() => assertCliPhp(result), /remote import must invoke explicit CLI PHP/);
});
