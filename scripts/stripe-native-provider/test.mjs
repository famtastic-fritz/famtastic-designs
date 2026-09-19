import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { resolveTestKey, validSignature, ownEvent, finalizeProbe, assertNoRemoteDestinations, stopOwnedGroup, PROFILE, parseScenario, eventFor, unpaidNative } from './run.mjs';
const key = ['rk', 'test', 'synthetic_not_real'].join('_');
test('only exact test field, never other profile or live field', async () => {
  assert.equal(await resolveTestKey(['[other]', `test_mode_api_key = "ignored"`, `[${PROFILE}]`, 'live_mode_api_key = "never_return_this"', `test_mode_api_key = "${key}"`], PROFILE), key);
});
for (const [name, lines, profile] of [
  ['wrong profile', [], 'default'], ['missing', [`[${PROFILE}]`], PROFILE],
  ['live key', [`[${PROFILE}]`, 'test_mode_api_key = "rk_live_synthetic"'], PROFILE],
  ['duplicate section', [`[${PROFILE}]`, `[${PROFILE}]`], PROFILE],
  ['duplicate key', [`[${PROFILE}]`, `test_mode_api_key = "${key}"`, `test_mode_api_key = "${key}"`], PROFILE],
  ['unsafe escape', [`[${PROFILE}]`, 'test_mode_api_key = "rk_test_a\\nb"'], PROFILE],
]) test(`refuse ${name}`, async () => assert.rejects(() => resolveTestKey(lines, profile)));
const body = '{"synthetic":true}', secret = 'test-only-secret', time = 1800000000;
const signature = `t=${time},v1=${crypto.createHmac('sha256', secret).update(`${time}.${body}`).digest('hex')}`;
test('valid signature', () => assert.equal(validSignature(body, signature, secret, time), true));
for (const [name, b, s, t] of [['tampered', body + ' ', signature, time], ['expired', body, signature, time + 301], ['duplicate timestamp', body, `${signature},t=${time}`, time], ['missing', body, undefined, time]])
  test(`signature ${name}`, () => assert.equal(validSignature(b, s, secret, t), false));
const binding = { run_id: 'synthetic-run', intent_id: 'pi_Test', order_id: '1', store_id: '1' };
const event = { id: 'evt_Test', livemode: false, type: 'payment_intent.succeeded', data: { object: { id: binding.intent_id, livemode: false, amount: 19900, currency: 'usd', metadata: { native_probe: binding.run_id, order_id: '1', store_id: '1' } } } };
test('own event', () => assert.equal(ownEvent(event, binding), true));
for (const [name, change] of [['live', e => e.livemode = true], ['connected', e => e.account = 'acct_Foreign'], ['wrong run', e => e.data.object.metadata.native_probe = 'foreign'], ['wrong order', e => e.data.object.metadata.order_id = '17'], ['wrong amount', e => e.data.object.amount = 20000], ['wrong intent', e => e.data.object.id = 'pi_Foreign']])
  test(`drop event ${name}`, () => { const copy = structuredClone(event); change(copy); assert.equal(ownEvent(copy, binding), false); });

for (const collectionFailure of [false, true]) for (const cleanupFailure of [false, true]) {
  test(`unconditional cleanup collection=${collectionFailure} cleanup=${cleanupFailure}`, async () => {
    const calls = [];
    await finalizeProbe({ collect: () => { calls.push('collect'); if (collectionFailure) throw new Error('injected'); },
      cleanup: () => { calls.push('cleanup'); if (cleanupFailure) throw new Error('injected'); },
      persist: result => { calls.push('persist'); assert.deepEqual(result, { collectionFailed: collectionFailure, cleanupFailed: cleanupFailure }); } });
    assert.deepEqual(calls, ['collect', 'cleanup', 'persist']);
  });
}
test('persistence failure happens after cleanup', async () => {
  let cleaned = false;
  await assert.rejects(() => finalizeProbe({ collect: () => { throw new Error('collection'); }, cleanup: () => { cleaned = true; }, persist: () => { throw new Error('persist'); } }));
  assert.equal(cleaned, true);
});
test('only complete empty endpoint inventories pass', () => {
  assert.doesNotThrow(() => assertNoRemoteDestinations({ object: 'list', data: [], has_more: false }, { data: [], next_page_url: null }));
});
for (const [name, classic, modern] of [
  ['classic destination', { object: 'list', data: [{ status: 'enabled' }], has_more: false }, { data: [], next_page_url: null }],
  ['thin destination', { object: 'list', data: [], has_more: false }, { data: [{ status: 'enabled' }], next_page_url: null }],
  ['classic pagination', { object: 'list', data: [], has_more: true }, { data: [], next_page_url: null }],
  ['thin pagination', { object: 'list', data: [], has_more: false }, { data: [], next_page_url: '/more' }],
  ['unknown shape', { data: [] }, { data: [] }],
]) test(`remote endpoint isolation ${name}`, () => assert.throws(() => assertNoRemoteDestinations(classic, modern)));
test('group cleanup escalates even after wrapper exit', async () => {
  const signals = []; let exists = true;
  await stopOwnedGroup(12345, (group, signal) => { assert.equal(group, -12345); signals.push(signal);
    if (!exists) throw Object.assign(new Error('gone'), { code: 'ESRCH' });
    if (signal === 'SIGKILL') exists = false;
  }, async () => {});
  assert.equal(exists, false); assert.ok(signals.includes('SIGKILL'));
});
test('group remaining after escalation is cleanup failure', async () => {
  await assert.rejects(() => stopOwnedGroup(12345, () => true, async () => {}), /listener_group_still_running/);
});
test('invalid group never signaled', async () => {
  await assert.rejects(() => stopOwnedGroup(1, () => { throw new Error('must not call'); }), /invalid_owned_group/);
});
for (const scenario of ['decline', 'action-required', 'abandonment']) {
  test(`explicit scenario ${scenario}`, () => {
    assert.deepEqual(parseScenario(['--scenario', scenario]), { scenario, offline: false });
    const copy = structuredClone(event); copy.type = eventFor(scenario);
    assert.equal(ownEvent(copy, { ...binding, scenario }), true);
    assert.equal(ownEvent(event, { ...binding, scenario }), false);
  });
}
for (const args of [['--scenario', 'live'], ['--scenario'], ['--offline', '--scenario', 'decline'], ['--scenario', 'decline', 'extra']])
  test(`reject scenario arguments ${args.join(' ')}`, () => assert.throws(() => parseScenario(args), /scenario_refused/));
test('legacy offline and success remain explicit modes', () => {
  assert.deepEqual(parseScenario([]), { scenario: 'success', offline: false });
  assert.deepEqual(parseScenario(['--offline']), { scenario: 'success', offline: true });
});
const unpaid = { payment_count: 0, order_state: 'draft', balance_zero: false, balance: '199.00', captured_mail_count: 0 };
test('nonpayment requires draft full balance no payment and no receipt', () => assert.equal(unpaidNative(unpaid), true));
for (const [key, value] of Object.entries({ payment_count: 1, order_state: 'completed', balance_zero: true, balance: '0', captured_mail_count: 1 }))
  test(`refuse nonpayment claim with ${key}`, () => assert.equal(unpaidNative({ ...unpaid, [key]: value }), false));
