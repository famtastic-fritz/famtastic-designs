import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('./textbee-lab.mjs', import.meta.url));
const secret = 'synthetic-key-must-not-print';
const phone = '+15555550123';

function run(mode, env = {}) {
  return spawnSync(process.execPath, [script, mode], {
    encoding: 'utf8',
    env: { PATH: process.env.PATH, ...env },
  });
}

test('readiness redacts the key and phone and never sends', () => {
  const result = run('--check', {
    TEXTBEE_API_KEY: secret,
    TEXTBEE_LAB_TO: phone,
    TEXTBEE_LAB_ENABLED: '1',
  });
  assert.equal(result.status, 0);
  assert.deepEqual(JSON.parse(result.stdout), {
    mode: 'fritz_only_fictional_lab',
    credential_source: 'environment',
    key_present: true,
    test_recipient_valid: true,
    device_selected: false,
    ready: true,
    sent: false,
  });
  assert.equal((result.stdout + result.stderr).includes(secret), false);
  assert.equal((result.stdout + result.stderr).includes(phone), false);
});

test('common US phone input is normalized without exposing it', () => {
  const result = run('--check', {
    TEXTBEE_API_KEY: secret,
    TEXTBEE_LAB_TO: '(555) 555-0123',
    TEXTBEE_LAB_ENABLED: '1',
  });
  assert.equal(result.status, 0);
  assert.equal(JSON.parse(result.stdout).test_recipient_valid, true);
  assert.equal((result.stdout + result.stderr).includes('555'), false);
});

test('send requires a separate acknowledgement and cannot run from this test', () => {
  const result = run('--send', {
    TEXTBEE_API_KEY: secret,
    TEXTBEE_LAB_TO: phone,
    TEXTBEE_LAB_ENABLED: '1',
  });
  assert.equal(result.status, 2);
  assert.match(result.stderr, /lab_ack_required/);
  assert.equal(result.stdout, '');
  assert.equal((result.stdout + result.stderr).includes(secret), false);
  assert.equal((result.stdout + result.stderr).includes(phone), false);
});

test('private setup and removal refuse non-interactive execution', () => {
  for (const mode of ['--setup', '--set-phone', '--forget']) {
    const result = run(mode);
    assert.equal(result.status, 2);
    assert.match(result.stderr, /interactive_terminal_required/);
  }
});
