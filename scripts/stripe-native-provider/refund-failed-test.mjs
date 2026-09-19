#!/usr/bin/env node
// Exact failed-probe cleanup, not native refund/checkout acceptance evidence.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { randomUUID, createHash } from 'node:crypto';
import { inspectTestProfile, cliEnvironment } from '../stripe-provider-preflight.mjs';
import { PROFILE, ACCOUNT, assertNoRemoteDestinations } from './run.mjs';

const stop = code => { throw new Error(code); };
const run = process.argv[2];
const cleanupId = randomUUID();
let report = { schema: 'famtastic.failed-native-probe-cleanup.v1', status: 'refused', native_refund_proven: false, cleanup_id: cleanupId,
  source_sha256: createHash('sha256').update(fs.readFileSync(fileURLToPath(import.meta.url))).digest('hex') };
let directory;
const persist = (file, data) => { const fd = fs.openSync(file, 'wx', 0o600); try { fs.writeFileSync(fd, JSON.stringify(data, null, 2)); fs.fsyncSync(fd); } finally { fs.closeSync(fd); } };
try {
  if (process.argv.length !== 3 || !/^native-probe-[a-f0-9-]{36}$/.test(run ?? '')
    || process.env.FAMTASTIC_STRIPE_NATIVE_CLEANUP !== '1' || process.env.FAMTASTIC_STRIPE_TEST_PROFILE !== PROFILE
    || process.env.FAMTASTIC_STRIPE_EXPECTED_ACCOUNT !== ACCOUNT) stop('explicit_failed_test_cleanup_required');
  const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
  directory = `${root}/.artifacts/stripe-native-provider/${run}`;
  const original = JSON.parse(fs.readFileSync(`${directory}/evidence.json`));
  if (original.status !== 'failed' || original.run_id !== run || original.account_id !== ACCOUNT || original.offline !== false
    || !/^pi_[A-Za-z0-9]+$/.test(original.intent_id ?? '') || original.customer_records_copied !== false) stop('failed_synthetic_record_required');
  report = { ...report, run_id: run, account_id: ACCOUNT, intent_id: original.intent_id, observed_at: new Date().toISOString(), refund_write_attempted: false };
  inspectTestProfile();
  const cli = command => {
    const result = spawnSync('stripe', [...command, `--project-name=${PROFILE}`, '--color=off'],
      { env: cliEnvironment(process.env), encoding: 'utf8', timeout: 20000, maxBuffer: 1024 * 1024 });
    if (result.status !== 0) stop('provider_call_failed_or_uncertain');
    let object; try { object = JSON.parse(result.stdout); } catch { stop('provider_response_invalid_or_uncertain'); }
    if (object.error) stop('provider_response_error');
    return object;
  };
  assertNoRemoteDestinations(cli(['webhook_endpoints', 'list', '--limit=100']), cli(['v2', 'core', 'event_destinations', 'list', '--limit=100']));
  const pi = cli(['payment_intents', 'retrieve', original.intent_id]);
  if (pi.id !== original.intent_id || pi.livemode !== false || pi.status !== 'succeeded' || pi.amount !== 19900 || pi.amount_received !== 19900
    || pi.currency !== 'usd' || pi.metadata?.native_probe !== run || pi.metadata?.order_id !== '1' || pi.metadata?.store_id !== '1'
    || pi.customer || pi.receipt_email) stop('synthetic_payment_binding_refused');
  const existing = cli(['refunds', 'list', `--payment-intent=${pi.id}`, '--limit=100']);
  if (existing.object !== 'list' || existing.has_more !== false || !Array.isArray(existing.data)) stop('refund_inventory_incomplete');
  let refund;
  if (existing.data.length === 1) refund = existing.data[0];
  else if (existing.data.length === 0) {
    report.refund_write_attempted = true; report.status = 'refund_attempting'; report.reconciliation_required = true;
    persist(`${directory}/cleanup-attempt-${cleanupId}.json`, report);
    refund = cli(['refunds', 'create', `--payment-intent=${pi.id}`, '--amount=19900', `--idempotency=${run}-cleanup-refund`,
      '-d', `metadata[native_probe]=${run}`, '--confirm']);
  } else stop('multiple_existing_refunds_require_reconciliation');
  if (refund.object !== 'refund' || refund.payment_intent !== pi.id || refund.amount !== 19900 || refund.currency !== 'usd'
    || refund.status !== 'succeeded') stop('refund_not_confirmed');
  report = { ...report, status: 'test_cleanup_refund_confirmed', refund_id: refund.id, amount_cents: refund.amount, currency: refund.currency,
    reconciliation_required: false, production_mutations: false, native_refund_proven: false };
  persist(`${directory}/cleanup-receipt-${cleanupId}.json`, report);
}
catch (error) {
  report.status = 'failed_or_refused'; report.reason = /^[a-z_]+$/.test(error.message) ? error.message : 'cleanup_failed';
  report.reconciliation_required = report.refund_write_attempted === true; process.exitCode = 2;
}
console.log(JSON.stringify(report, null, 2));
