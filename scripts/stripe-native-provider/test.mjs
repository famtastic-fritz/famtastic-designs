import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { assessJournal, expectedInterruption } from './recovery.mjs';
import { resolveTestKey, validSignature, ownEvent, finalizeProbe, assertNoRemoteDestinations, stopOwnedGroup, PROFILE, parseScenario, eventFor, unpaidNative, completedNative } from './run.mjs';
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
for (const scenario of ['decline', 'action-required', 'abandonment', 'recovery']) {
  test(`explicit scenario ${scenario}`, () => {
    assert.deepEqual(parseScenario(['--scenario', scenario]), { scenario, offline: false });
    const copy = structuredClone(event); copy.type = eventFor(scenario);
    assert.equal(ownEvent(copy, { ...binding, scenario }), true);
    assert.equal(ownEvent(event, { ...binding, scenario }), scenario === 'recovery');
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

const hash = value => crypto.createHash('sha256').update(value).digest('hex');
const recoveryBinding = { ...binding, scenario: 'recovery', verified_account: 'acct_1TqwE9DDGtWR2WVN' };
const original = { seq: 1, run_id: binding.run_id, account_id: recoveryBinding.verified_account, intent_id: binding.intent_id,
  method: 'post', path: '/v1/payment_intents/pi_Test/confirm', params_sha256: hash(JSON.stringify({ payment_method: 'pm_card_visa' })),
  idempotency_sha256: hash(`${binding.run_id}-confirm`) };
const interruption = { ...original, fault: 'exit_before_response_observation', exit_code: 86 };
const exited = { status: 86, stdout: '', signal: null, killed: false };
test('exact Drush exit and unmatched durable attempt identify controlled interruption', () =>
  assert.equal(expectedInterruption(exited, interruption, [original], [], recoveryBinding), true));
for (const [key, value] of Object.entries({ status: 1, stdout: ' ', signal: 'SIGTERM', killed: true }))
  test(`interruption refuses child ${key}`, () => assert.equal(expectedInterruption({ ...exited, [key]: value }, interruption, [original], [], recoveryBinding), false));
for (const [key, value] of Object.entries({ run_id: 'foreign', intent_id: 'pi_Foreign', account_id: 'acct_Foreign', params_sha256: 'wrong',
  idempotency_sha256: 'wrong', fault: 'other', exit_code: 1, seq: 2, path: '/v1/payment_intents/pi_Other/confirm' }))
  test(`interruption refuses marker ${key}`, () => assert.equal(expectedInterruption(exited, { ...interruption, [key]: value }, [original], [], recoveryBinding), false));
test('interruption refuses absent, duplicated or already responded attempt', () => {
  assert.equal(expectedInterruption(exited, interruption, [], [], recoveryBinding), false);
  assert.equal(expectedInterruption(exited, interruption, [original, original], [], recoveryBinding), false);
  assert.equal(expectedInterruption(exited, interruption, [original], [{ seq: original.seq }], recoveryBinding), false);
});
const read = { ...original, seq: 2, method: 'get', path: '/v1/payment_intents/pi_Test', params_sha256: hash('[]'), idempotency_sha256: null };
const replay = { ...original, seq: 3 };
const responses = [read, replay].map(x => ({ seq: x.seq, method: x.method, path: x.path, status: 200, provider_object_id: 'pi_Test', idempotent_replayed: x.seq === 3 }));
const resolution = { schema: 'famtastic.native-confirm-reconciliation.v1', run_id: binding.run_id, intent_id: 'pi_Test',
  account_id: recoveryBinding.verified_account, livemode: false, amount_received: 19900, currency: 'usd', provider_status: 'succeeded',
  charge_id: 'ch_Test', covered_seq: 1, read_seq: 2, replay_seq: 3 };
test('unknown response remains unresolved without exact evidence', () => assert.deepEqual(assessJournal([original], [], null, recoveryBinding), { unresolved: [1], reconciled: [] }));
test('read and provider-idempotent replay reconcile only the one original gap', () => assert.deepEqual(assessJournal([original, read, replay], responses, resolution, recoveryBinding), { unresolved: [], reconciled: [1] }));
test('fully observed requests have no reconciliation claim', () => assert.deepEqual(assessJournal([read, replay], responses, null, recoveryBinding), { unresolved: [], reconciled: [] }));
for (const [key, value] of Object.entries({ run_id: 'foreign', intent_id: 'pi_Foreign', account_id: 'acct_Foreign', livemode: true,
  amount_received: 100, currency: 'eur', provider_status: 'requires_action', charge_id: null, covered_seq: 4, read_seq: 3, replay_seq: 2 }))
  test(`reconciliation rejects ${key}`, () => assert.throws(() => assessJournal([original, read, replay], responses, { ...resolution, [key]: value }, recoveryBinding)));
for (const [key, value] of Object.entries({ params_sha256: 'different', idempotency_sha256: 'new_key', intent_id: 'pi_Foreign', method: 'get' }))
  test(`replay must match original ${key}`, () => assert.throws(() => assessJournal([original, read, { ...replay, [key]: value }], responses, resolution, recoveryBinding)));
test('cannot hide additional unknown request', () => assert.throws(() => assessJournal([original, read, replay, { ...original, seq: 4 }], responses, resolution, recoveryBinding)));
test('cannot resolve response without provider replay header', () => assert.throws(() => assessJournal([original, read, replay], [responses[0], { ...responses[1], idempotent_replayed: false }], resolution, recoveryBinding)));
test('duplicate attempt IDs refuse', () => assert.throws(() => assessJournal([original, original], [], null, recoveryBinding)));
test('duplicate response IDs refuse', () => assert.throws(() => assessJournal([read], [responses[0], responses[0]], null, recoveryBinding)));
test('unmatched response refuses', () => assert.throws(() => assessJournal([], [responses[0]], null, recoveryBinding)));
test('wrong account attempt refuses', () => assert.throws(() => assessJournal([{ ...original, account_id: 'acct_Foreign' }], [], null, recoveryBinding)));
const paid = { payment_count: 1, payment_state: 'completed', order_state: 'completed', balance_zero: true, balance: '0', captured_mail_count: 1 };
test('recovery completion requires exact native completed state', () => assert.equal(completedNative(paid), true));
for (const [key, value] of Object.entries({ payment_count: 2, payment_state: 'new', order_state: 'validation', balance_zero: false, balance: '199', captured_mail_count: 2 }))
  test(`recovery rejects incomplete or duplicate ${key}`, () => assert.equal(completedNative({ ...paid, [key]: value }), false));
