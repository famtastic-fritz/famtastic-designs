import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { inspectTestProfile, cliEnvironment, PreflightRefusal, parseCliResult } from './stripe-provider-preflight.mjs';

const now = Date.parse('2026-09-19T07:00:00Z');
const env = { HOME: '/tmp/preflight-unit-only', PATH: '/usr/bin:/bin',
  FAMTASTIC_STRIPE_TEST_PROFILE: 'famtastic-sandbox-auth', FAMTASTIC_STRIPE_EXPECTED_ACCOUNT: 'acct_UnitTest1234' };
const identity = () => ({ authenticated: true, profile_name: env.FAMTASTIC_STRIPE_TEST_PROFILE,
  account_id: env.FAMTASTIC_STRIPE_EXPECTED_ACCOUNT,
  test_mode_key: { available: true, expires_at: '2026-11-09' }, live_mode_key: { available: true },
  unexpected_secret: 'must-not-be-logged' });
const balance = () => ({ object: 'balance', livemode: false, available: [{ amount: 987654 }] });
function harness(replies = [identity(), balance(), identity()], input = env) {
  const calls = [];
  return { calls, run: () => inspectTestProfile({ env: input, now,
    read: (args, childEnv) => { calls.push({ args, childEnv }); return replies[calls.length - 1]; } }) };
}
function denied(fn, code) { assert.throws(fn, error => error instanceof PreflightRefusal && error.code === code); }

test('only exact read commands; sanitized receipt cannot imply payment readiness', () => {
  const h = harness(), receipt = h.run();
  assert.equal(receipt.status, 'read_only_preflight_passed');
  assert.equal(receipt.e2e_proven, false); assert.equal(receipt.checkout_activated, false);
  assert.equal(receipt.provider_mutations, 0); assert.equal(receipt.live_credential_available, true);
  assert.equal(receipt.secret_values_read_or_exported, false);
  assert.deepEqual(h.calls.map(x => x.args), [
    ['whoami', '--project-name=famtastic-sandbox-auth', '--format', 'json'],
    ['balance', 'retrieve', '--project-name=famtastic-sandbox-auth', '--color=off'],
    ['whoami', '--project-name=famtastic-sandbox-auth', '--format', 'json'],
  ]);
  assert(!JSON.stringify(receipt).includes('must-not-be-logged'));
  assert(!JSON.stringify(receipt).includes('987654'));
});

for (const field of ['FAMTASTIC_STRIPE_TEST_PROFILE', 'FAMTASTIC_STRIPE_EXPECTED_ACCOUNT']) {
  for (const bad of [undefined, '', ' --live', 'x\n--api-key=bad', '../another', 'a'.repeat(100)]) {
    test(`reject invalid explicit binding ${field} ${JSON.stringify(bad)}`, () => {
      const h = harness(undefined, { ...env, [field]: bad });
      denied(h.run, field.endsWith('PROFILE') ? 'explicit_test_profile_required' : 'explicit_expected_account_required');
      assert.equal(h.calls.length, 0);
    });
  }
}

for (const [mutate, code] of [
  [i => { i.authenticated = false; }, 'stripe_not_authenticated'],
  [i => { i.profile_name = 'alreadybuilt-revenue-trial'; }, 'stripe_profile_mismatch'],
  [i => { i.account_id = 'acct_Another123'; }, 'stripe_account_mismatch'],
  [i => { i.test_mode_key.available = false; }, 'stripe_test_credential_unavailable'],
]) test(code, () => {
  const i = identity(); mutate(i); const h = harness([i]); denied(h.run, code); assert.equal(h.calls.length, 1);
});
for (const expiry of [undefined, '', '2026-09-18', '2026-09-19', '2026-99-01', '2027-02-30', '2026-12-01T00:00:00Z']) {
  test(`reject expiry ${expiry}`, () => { const i = identity(); i.test_mode_key.expires_at = expiry;
    denied(harness([i]).run, 'stripe_test_credential_expired_or_unknown'); });
}
for (const mode of [undefined, null, true, 'false', 0]) {
  test(`refuse ambiguous/live balance ${JSON.stringify(mode)}`, () => {
    const b = balance(); b.livemode = mode;
    denied(harness([identity(), b]).run, 'stripe_test_mode_not_proven');
  });
}
test('refuse a response that is not a balance', () => {
  denied(harness([identity(), { object: 'error', livemode: false }]).run, 'stripe_test_mode_not_proven');
});
test('profile account changed after test-mode read is refused', () => {
  const last = identity(); last.account_id = 'acct_Another123';
  denied(harness([identity(), balance(), last]).run, 'stripe_account_mismatch');
});
test('credential metadata changed during read is refused', () => {
  const last = identity(); last.test_mode_key.expires_at = '2027-01-01';
  denied(harness([identity(), balance(), last]).run, 'stripe_profile_changed_during_read');
});
test('ambient keys, proxies, alternate configs and runtime injection not inherited', () => {
  const child = cliEnvironment({ ...env, STRIPE_API_KEY: 'never-read', STRIPE_SECRET_KEY: 'never-read',
    STRIPE_ACCOUNT: 'another', XDG_CONFIG_HOME: '/other', NODE_OPTIONS: '--require=bad',
    HTTPS_PROXY: 'https://other', STRIPE_CLI_PROJECT: 'other', LD_PRELOAD: '/bad', DYLD_INSERT_LIBRARIES: '/bad' });
  assert.deepEqual(child, { HOME: env.HOME, PATH: env.PATH, LANG: 'C', NO_COLOR: '1' });
});
for (const patch of [{ HOME: '' }, { HOME: 'relative' }, { HOME: '/tmp\0bad' }, { PATH: '' }, { PATH: undefined }]) {
  test(`reject CLI environment ${JSON.stringify(patch)}`, () => denied(() => cliEnvironment({ ...env, ...patch }), 'invalid_cli_environment'));
}
test('reject invalid clock before any CLI access', () => {
  denied(() => inspectTestProfile({ env, now: NaN, read: () => assert.fail('must not call') }), 'invalid_clock');
});

for (const result of [
  { status: 1, stdout: 'sensitive-provider-output', stderr: 'sensitive-diagnostic' },
  { status: null, error: new Error('sensitive-timeout'), stdout: '' },
  { status: 0, error: new Error('sensitive-spawn-failure'), stdout: '{}' },
]) test(`subprocess refusal is sanitized: ${result.status}`, () => denied(() => parseCliResult(result), 'stripe_read_failed'));
test('malformed successful stdout is sanitized', () => {
  denied(() => parseCliResult({ status: 0, stdout: 'sensitive-invalid-json' }), 'stripe_read_invalid_json');
});
test('valid CLI JSON parses without diagnostics', () => {
  assert.deepEqual(parseCliResult({ status: 0, stdout: '{"object":"balance","livemode":false}', stderr: 'diagnostic-not-a-receipt' }), balanceWithoutAmounts());
});
function balanceWithoutAmounts() { return { object: 'balance', livemode: false }; }
test('wrapper keeps refusal stdout machine-readable, diagnostics on stderr; no Stripe invocation', () => {
  const script = fileURLToPath(new URL('./stripe-provider-e2e.sh', import.meta.url));
  const result = spawnSync('bash', [script, '--preflight'], {
    env: cliEnvironment(process.env), encoding: 'utf8', timeout: 20000,
  });
  assert.equal(result.status, 2);
  assert.equal(JSON.parse(result.stdout).reason, 'explicit_test_profile_required');
  assert.match(result.stderr, /catalog products match/);
});
