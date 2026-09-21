import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, symlinkSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runOnce, validateEndpoint, validateClaim, signedHeaders } from './worker.mjs';
const now = () => 1789700000_000;
function fixture() {
  const packet = { build_class: 'prepayment_selected_direction_staging', packet_id: 'fixture', idempotency_key: 'fixture-key',
    continuation: { spec: { capability_class: 'static' }, operation: 'package_existing', requested_next_action: 'protected_review' } };
  const payload_wire = JSON.stringify({ packet });
  return { job_id: 991, policy_version: 'bounded-workers-v1', capability: 'selected-static-dispatch-v1', payload_wire,
    payload_sha256: createHash('sha256').update(payload_wire).digest('hex'), lease_token: 'a'.repeat(64), lease_until: now() / 1000 + 90, execution_deadline: now() / 1000 + 300 };
}
const receipt = { status: 'accepted_waiting_callback', receipt_id: 'receipt', packet_id: 'fixture', idempotency_key: 'fixture-key' };
test('no work exits without provider calls', async () => {
  let called = false;
  assert.deepEqual(await runOnce({ api: async () => ({ claim: null }), dispatch: async () => { called = true; }, now }), { state: 'no_enrolled_work', provider_calls: 0 });
  assert.equal(called, false);
});
test('completion retry preserves one dispatch and does not claim staging readiness', async () => {
  let dispatches = 0; let finishes = 0;
  const api = async operation => { if (operation === 'claim') return { claim: fixture() }; if (operation === 'finish' && ++finishes === 1) throw new Error('lost acknowledgement'); return { result: {} }; };
  const result = await runOnce({ api, dispatch: async () => { dispatches++; return receipt; }, now });
  assert.equal(dispatches, 1); assert.equal(finishes, 2); assert.equal(result.staging_ready, false);
});
test('dispatch failure reports failure once without build loops', async () => {
  const operations = [];
  await assert.rejects(runOnce({ api: async op => { operations.push(op); return op === 'claim' ? { claim: fixture() } : {}; }, dispatch: async () => { throw new Error('provider unavailable'); }, now }));
  assert.deepEqual(operations, ['claim', 'fail']);
});
test('lost completion acknowledgement does not redispatch or fail an accepted handoff', async () => {
  const operations = [];
  await assert.rejects(runOnce({ api: async op => { operations.push(op); if (op === 'claim') return { claim: fixture() }; throw new Error('network lost'); }, dispatch: async () => receipt, now }), /completion_acknowledgement_uncertain/);
  assert.deepEqual(operations, ['claim', 'finish', 'finish']);
});
test('changed payload, expired token, unsupported capability fail before dispatch', () => {
  for (const [key, value] of [['payload_wire', '{}'], ['lease_until', 1], ['capability', 'ecommerce-build'], ['execution_deadline', now()/1000 + 900]]) assert.throws(() => validateClaim({ ...fixture(), [key]: value }, now()));
});
test('endpoint safety refuses credentials, queries, wrong paths, non-TLS cloud', () => {
  for (const url of ['http://example.com/web', 'https://user:pass@example.com/web', 'https://example.com/web?x=1', 'https://example.com/other']) assert.throws(() => validateEndpoint(url, '/web'));
  assert.equal(validateEndpoint('https://example.com/web', '/web'), 'https://example.com/web');
});
test('static build class does not authorize ecommerce work', () => {
  const c = fixture(), payload = JSON.parse(c.payload_wire);
  payload.packet.continuation.spec.backend = { engine: 'woocommerce' };
  c.payload_wire = JSON.stringify(payload);
  c.payload_sha256 = createHash('sha256').update(c.payload_wire).digest('hex');
  assert.throws(() => validateClaim(c, now()), /unsupported_implementation_work/);
});
test('signatures bind exact action, worker, bytes and nonce', () => {
  const secret = 's'.repeat(32), nonce = 'a'.repeat(32);
  const base = signedHeaders('claim', '{}', 'cloud-run', secret, now(), nonce);
  assert.notEqual(base['X-FAMtastic-Signature'], signedHeaders('finish', '{}', 'cloud-run', secret, now(), nonce)['X-FAMtastic-Signature']);
  assert.notEqual(base['X-FAMtastic-Signature'], signedHeaders('claim', '{}', 'mac-fallback', secret, now(), nonce)['X-FAMtastic-Signature']);
});
test('direct execution through a symlink runs and fails closed without configuration; imports stay inert', () => {
  const root = mkdtempSync(join(tmpdir(), 'bounded-cli-'));
  const source = fileURLToPath(new URL('./worker.mjs', import.meta.url));
  const link = join(root, 'worker-link.mjs');
  try {
    symlinkSync(source, link);
    for (const args of [[link], ['--preserve-symlinks-main', link]]) {
      const direct = spawnSync(process.execPath, args, { env: {}, encoding: 'utf8', timeout: 5000 });
      assert.equal(direct.status, 1, direct.stderr);
      assert.equal(direct.stdout, '');
      assert.match(direct.stderr, /bounded_worker_failed/);
    }
    const imported = spawnSync(process.execPath, ['--input-type=module', '-e', `await import(${JSON.stringify(new URL('./worker.mjs', import.meta.url).href)})`], { env: {}, encoding: 'utf8', timeout: 5000 });
    assert.equal(imported.status, 0, imported.stderr);
    assert.equal(imported.stdout + imported.stderr, '');
  } finally { rmSync(root, { recursive: true, force: true }); }
});
