#!/usr/bin/env node
// Explicitly opt-in test-only native Commerce bridge. No real account fixture copy.
import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline';
import http from 'node:http';
import crypto from 'node:crypto';
import { spawn, spawnSync, execFile } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { inspectTestProfile, cliEnvironment } from '../stripe-provider-preflight.mjs';
import { assessJournal, expectedInterruption } from './recovery.mjs';

export const PROFILE = 'famtastic-sandbox-auth';
export const ACCOUNT = 'acct_1TqwE9DDGtWR2WVN';
const fail = reason => { throw new Error(reason); };
const writePrivate = (file, value) => fs.writeFileSync(file, JSON.stringify(value), { mode: 0o600 });

export function parseScenario(args) {
  if (args.length === 0) return { scenario: 'success', offline: false };
  if (args.length === 1 && args[0] === '--offline') return { scenario: 'success', offline: true };
  if (args.length === 2 && args[0] === '--scenario' && ['decline', 'action-required', 'abandonment', 'recovery'].includes(args[1]))
    return { scenario: args[1], offline: false };
  fail('scenario_refused');
}
export const eventFor = scenario => ({ success: 'payment_intent.succeeded', recovery: 'payment_intent.succeeded', decline: 'payment_intent.payment_failed',
  'action-required': 'payment_intent.requires_action', abandonment: 'payment_intent.canceled' })[scenario];
export const unpaidNative = state => state?.payment_count === 0 && state.order_state === 'draft'
  && state.balance_zero === false && Number(state.balance) === 199 && state.captured_mail_count === 0;
export const completedNative = state => state?.payment_count === 1 && state.payment_state === 'completed'
  && state.order_state === 'completed' && state.balance_zero === true && Number(state.balance) === 0 && state.captured_mail_count === 1;

// Evidence/report errors must never bypass secret/runtime cleanup.
export async function finalizeProbe({ collect, cleanup, persist }) {
  let collectionFailed = false, cleanupFailed = false;
  try { await collect(); } catch { collectionFailed = true; }
  finally { try { await cleanup(); } catch { cleanupFailed = true; } }
  await persist({ collectionFailed, cleanupFailed });
}

export async function stopOwnedGroup(pid, kill = process.kill.bind(process), wait = ms => new Promise(resolve => setTimeout(resolve, ms))) {
  if (!Number.isInteger(pid) || pid <= 1) fail('invalid_owned_group');
  const signal = value => { try { kill(-pid, value); return true; } catch (error) { if (error.code === 'ESRCH') return false; throw error; } };
  if (!signal('SIGTERM')) return;
  for (let n = 0; n < 30; n++) { if (!signal(0)) return; await wait(50); }
  if (!signal('SIGKILL')) return;
  for (let n = 0; n < 20; n++) { if (!signal(0)) return; await wait(50); }
  fail('listener_group_still_running');
}

export function assertNoRemoteDestinations(classic, modern) {
  if (classic?.object !== 'list' || !Array.isArray(classic.data) || classic.has_more !== false
    || !Array.isArray(modern?.data) || !Object.hasOwn(modern, 'next_page_url') || modern.next_page_url !== null
    || classic.data.length !== 0 || modern.data.length !== 0) fail('remote_destinations_not_isolated');
}

function inspectRemoteDestinations(env) {
  const results = [['webhook_endpoints', 'list'], ['v2', 'core', 'event_destinations', 'list']].map(command => {
    const result = spawnSync('stripe', [...command, `--project-name=${PROFILE}`, '--limit=100', '--color=off'],
      { env, encoding: 'utf8', timeout: 20000, maxBuffer: 1024 * 1024 });
    if (result.status !== 0) fail('destination_inventory_failed');
    try { return JSON.parse(result.stdout); } catch { fail('destination_inventory_invalid'); }
  });
  assertNoRemoteDestinations(...results);
}

// Read only the exact test-key field from the selected profile. Never list config,
// return other fields, resolve a default profile, or copy the configuration file.
export async function resolveTestKey(lines, profile) {
  if (profile !== PROFILE) fail('profile_refused');
  let selected = false, sections = 0, key = null;
  for await (const raw of lines) {
    const line = raw.trim();
    if (line.startsWith('[')) {
      selected = line === `[${profile}]`;
      if (selected && ++sections !== 1) fail('ambiguous_profile');
    }
    else if (selected && /^test_mode_api_key\s*=/.test(line)) {
      if (key) fail('ambiguous_test_key');
      const match = line.match(/^test_mode_api_key\s*=\s*(["'])((?:sk|rk)_test_[A-Za-z0-9_]+)\1\s*$/);
      if (!match) fail('test_key_format_refused');
      key = match[2];
    }
  }
  if (!key) fail('exact_test_key_unavailable');
  return key;
}

export function validSignature(body, header, secret, now = Math.floor(Date.now() / 1000)) {
  if (typeof header !== 'string') return false;
  const parts = header.split(',');
  const times = parts.filter(x => /^t=\d+$/.test(x));
  if (times.length !== 1) return false;
  const time = Number(times[0].slice(2));
  if (Math.abs(now - time) > 300) return false;
  const digest = crypto.createHmac('sha256', secret).update(`${time}.${body}`).digest();
  return parts.some(x => /^v1=[a-f0-9]{64}$/.test(x) && crypto.timingSafeEqual(Buffer.from(x.slice(3), 'hex'), digest));
}

export function ownEvent(event, binding) {
  const object = event?.data?.object;
  return event?.livemode === false && event?.type === eventFor(binding.scenario ?? 'success')
    && !event.account && /^evt_[A-Za-z0-9]+$/.test(event.id ?? '')
    && object?.livemode === false && object?.id === binding.intent_id
    && object?.metadata?.native_probe === binding.run_id
    && String(object.metadata.order_id) === binding.order_id && String(object.metadata.store_id) === binding.store_id
    && object.amount === 19900 && object.currency === 'usd';
}

async function main() {
  const { offline, scenario } = parseScenario(process.argv.slice(2));
  if (!offline && process.env.FAMTASTIC_STRIPE_NATIVE_TEST !== '1') fail('explicit_native_test_opt_in_required');
  if (process.env.FAMTASTIC_STRIPE_TEST_PROFILE !== PROFILE || process.env.FAMTASTIC_STRIPE_EXPECTED_ACCOUNT !== ACCOUNT) fail('exact_profile_account_required');
  const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
  const borrowed = process.env.FAMTASTIC_BACKEND_VENDOR ? path.resolve(process.env.FAMTASTIC_BACKEND_VENDOR, '..') : '';
  if (!borrowed || !fs.existsSync(`${borrowed}/vendor/autoload.php`)) fail('matching_runtime_required');
  if (!offline) inspectTestProfile();
  const runtime = fs.realpathSync(fs.mkdtempSync('/tmp/famtastic-stripe-native.'));
  fs.chmodSync(runtime, 0o700);
  const runId = `native-probe-${crypto.randomUUID()}`;
  const evidence = `${root}/.artifacts/stripe-native-provider/${runId}`;
  fs.mkdirSync(evidence, { recursive: true, mode: 0o700 });
  const bindingPath = `${runtime}/binding.json`;
  writePrivate(bindingPath, { run_id: runId, scenario, expires_at: Math.floor(Date.now() / 1000) + 900 });
  const baseEnv = cliEnvironment(process.env);
  const childEnv = { PATH: baseEnv.PATH, HOME: `${runtime}/home`, TMPDIR: `${runtime}/tmp`, LANG: 'C', NATIVE_PROBE_ROOT: runtime, NATIVE_PROBE_EVIDENCE: evidence };
  const report = { schema: 'famtastic.native-stripe-provider.v1', run_id: runId, started_at: new Date().toISOString(), status: 'running', offline, scenario,
    source_sha: spawnSync('git', ['-C', root, 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout.trim(),
    limits: ['Native plugin in a fresh SQLite installation, not production or the request16 private purchase.',
      'CLI-forwarded signed test event then native onNotify in process, not hosted Apache webhook middleware.',
      'Anonymous synthetic native order; no browser Payment Element, login, 3DS, catalog matrix or agency entitlements claim.'],
    profile: PROFILE, account_id: ACCOUNT, customer_records_copied: false, production_checkout_activated: false, phases: {}, child_diagnostics: [], checks: {} };
  if (scenario === 'recovery') {
    report.limits[1] = 'Signed CLI-forwarded body is discarded without native handling; recovery retrieves the genuine Event over authenticated API, not signed redelivery.';
    report.limits.push('Controlled PHP exit after response dispatch and handler body loss, not whole-host crash recovery or partial native-fulfillment recovery.');
  }
  report.source_hashes = Object.fromEntries(['run.mjs', 'native.php', 'Guard.php', 'prepare.sh', 'recovery.mjs'].map(name => [name,
    crypto.createHash('sha256').update(fs.readFileSync(`${root}/scripts/stripe-native-provider/${name}`)).digest('hex')]));
  let listener, server, activeNative, key, webhook, packet, cancelled = false;
  const stopListener = signal => {
    // npm's CLI launcher forks the real executable. Kill only the process group
    // this detached spawn created, not just the launcher or unrelated listeners.
    if (listener?.pid) { try { process.kill(-listener.pid, signal); } catch (error) { if (error.code !== 'ESRCH') throw error; } }
  };
  const cancel = () => { cancelled = true; stopListener('SIGTERM'); if (activeNative) activeNative.kill('SIGTERM'); };
  process.on('SIGINT', cancel); process.on('SIGTERM', cancel);
  const native = async phase => {
    if (cancelled) fail('probe_cancelled');
    const args = ['-d', 'memory_limit=512M', '-d', 'allow_url_fopen=0', '-d', 'display_errors=0',
      '-d', `disable_functions=${offline ? 'curl_exec,' : ''}curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail`, '-d', 'sendmail_path=/usr/bin/false',
      `${runtime}/backend/vendor/drush/drush/drush.php`, `--root=${runtime}/backend/web`, '--uri=http://native-probe.example.test',
      'php:script', `${runtime}/probe/native.php`];
    const result = await new Promise(resolve => {
      activeNative = execFile('php', args, { env: { ...childEnv, NATIVE_PROBE_PHASE: phase },
        encoding: 'utf8', timeout: 60000, maxBuffer: 1024 * 1024 }, (error, stdout, stderr) => {
        activeNative = undefined;
        resolve({ status: error ? (Number.isInteger(error.code) ? error.code : 2) : 0, stdout,
          signal: error?.signal ?? null, killed: error?.killed === true, stderr_bytes: Buffer.byteLength(stderr ?? '') });
      });
      activeNative.stdin.on('error', () => {}); // EPIPE is reported by process result.
      activeNative.stdin.end(JSON.stringify({ key, webhook, packet }));
    });
    report.child_diagnostics.push({ phase, exit_code: result.status, signal: result.signal, killed: result.killed,
      stdout_bytes: Buffer.byteLength(result.stdout), stderr_bytes: result.stderr_bytes });
    let record;
    if ((scenario === 'recovery' && phase === 'confirm') || (offline && phase === 'offline_interrupt')) {
      const directory = phase === 'offline_interrupt' ? `${evidence}/offline-interruption` : evidence;
      const fault = JSON.parse(fs.readFileSync(`${directory}/interruption.json`));
      const rows = name => fs.existsSync(`${directory}/${name}.jsonl`) ? fs.readFileSync(`${directory}/${name}.jsonl`, 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse) : [];
      const exactBinding = JSON.parse(fs.readFileSync(phase === 'offline_interrupt' ? `${directory}/binding.json` : bindingPath));
      if (!expectedInterruption(result, fault, rows('attempts'), rows('requests'), exactBinding)) fail('interruption_evidence_refused');
      record = { phase, status: 'interrupted', exit_code: 86, response_observed_by_sdk: false };
    }
    else { try { record = JSON.parse(result.stdout.trim()); } catch { fail(`native_${phase}_invalid_result`); } }
    let phaseKey = phase, occurrence = 1;
    while (Object.hasOwn(report.phases, phaseKey)) phaseKey = `${phase}_${++occurrence}`;
    report.phases[phaseKey] = record;
    if ((result.status !== 0 && record.status !== 'interrupted') || record.status === 'refused') fail(`native_${phase}_failed`);
    return record;
  };
  const journalState = () => {
    const rows = name => fs.existsSync(`${evidence}/${name}.jsonl`) ? fs.readFileSync(`${evidence}/${name}.jsonl`, 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse) : [];
    const binding = JSON.parse(fs.readFileSync(bindingPath));
    const attempts = rows('attempts'), results = rows('requests');
    const resolution = fs.existsSync(`${evidence}/reconciliation.json`) ? JSON.parse(fs.readFileSync(`${evidence}/reconciliation.json`)) : null;
    return { attempts, results, ...assessJournal(attempts, results, resolution, binding) };
  };
  try {
    const installLog = fs.openSync(`${evidence}/install.log`, 'wx', 0o600);
    try {
      const result = spawnSync('bash', [`${root}/scripts/stripe-native-provider/prepare.sh`, root, runtime, borrowed],
        { env: baseEnv, stdio: ['ignore', installLog, installLog], timeout: 300000 });
      if (result.status !== 0) fail('fresh_runtime_install_failed');
    } finally { fs.closeSync(installLog); }
    if (offline) {
      key = ['rk', 'test', 'synthetic_not_real'].join('_'); webhook = 'whsec_synthetic';
      const fixture = await native('inspect');
      report.checks.ephemeral_gateway_reload = fixture.ephemeral_gateway_reload_verified === true && fixture.credentials_persisted === false;
      if (!report.checks.ephemeral_gateway_reload) fail('ephemeral_gateway_reload_failed');
      const fault = await native('offline_interrupt');
      const after = await native('inspect');
      report.checks.drush_guard_interruption = fault.status === 'interrupted' && fault.exit_code === 86;
      report.checks.offline_order_unchanged = unpaidNative(after) && after.credentials_persisted === false;
      if (Object.values(report.checks).includes(false)) fail('offline_interruption_assertion_failed');
      report.status = 'offline_runtime_prepared'; report.classification = 'locally proven: native fixture, ephemeral config and fake-transport Drush interruption only'; return;
    }
    const config = `${baseEnv.HOME}/.config/stripe/config.toml`;
    const stat = fs.lstatSync(config);
    if (!stat.isFile() || stat.isSymbolicLink() || stat.uid !== process.getuid() || (stat.mode & 0o077)) fail('credential_file_permissions_refused');
    const stream = fs.createReadStream(config);
    try { key = await resolveTestKey(readline.createInterface({ input: stream, crlfDelay: Infinity }), PROFILE); }
    finally { stream.destroy(); }
    // Other installed endpoints would also receive this test event. Refuse them
    // all rather than risk a native order-ID collision in another installation.
    inspectRemoteDestinations({ ...baseEnv, STRIPE_API_KEY: key });
    report.checks.no_registered_remote_destinations = true;
    // Buffer only exact own-run signed success event; drop unrelated events without
    // logging bodies. No publicly exposed tunnel or registered webhook is created.
    server = http.createServer((request, response) => {
      if (request.method !== 'POST' || request.url !== '/callback') { response.writeHead(404).end(); return; }
      let body = '', size = 0;
      request.on('data', chunk => { size += chunk.length; if (size > 128 * 1024) request.destroy(); else body += chunk; });
      request.on('end', () => {
        try {
          const signature = request.headers['stripe-signature'];
          if (!webhook || !validSignature(body, signature, webhook)) { response.writeHead(400).end(); return; }
          const binding = JSON.parse(fs.readFileSync(bindingPath));
          const event = JSON.parse(body);
          if (ownEvent(event, binding)) {
            if (scenario === 'recovery') {
              // Deliberately acknowledge without queueing/processing the body.
              // Retain only an observed Event ID, never a body/signature packet.
              const lostPath = `${evidence}/lost-callback.json`;
              if (!fs.existsSync(lostPath)) {
                const fd = fs.openSync(lostPath, 'wx', 0o600);
                try {
                  fs.writeFileSync(fd, JSON.stringify({ run_id: runId, intent_id: binding.intent_id, event_id: event.id,
                    signature_verified: true, body_retained: false, native_handler_called: false,
                    body_sha256: crypto.createHash('sha256').update(body).digest('hex') }));
                  fs.fsyncSync(fd);
                } finally { fs.closeSync(fd); }
              }
            }
            else if (!packet) packet = { body, signature };
          }
          response.writeHead(200).end();
        } catch { response.writeHead(400).end(); }
      });
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const port = server.address().port;
    listener = spawn('stripe', ['listen', `--project-name=${PROFILE}`, '--color=off', '--skip-update', `--events=${eventFor(scenario)}`,
      `--forward-to=http://127.0.0.1:${port}/callback`], { env: { ...baseEnv, STRIPE_API_KEY: key }, detached: true, stdio: ['ignore', 'pipe', 'pipe'] });
    await new Promise((resolve, reject) => {
      let pending = '';
      const timer = setTimeout(() => reject(new Error('listener_not_ready')), 20000);
      const capture = chunk => {
        pending = (pending + chunk.toString()).slice(-4096);
        const match = pending.match(/whsec_[A-Za-z0-9]+/);
        if (match) { webhook = match[0]; pending = ''; clearTimeout(timer); resolve(); }
      };
      listener.stdout.on('data', capture); listener.stderr.on('data', capture);
      listener.once('error', () => { clearTimeout(timer); reject(new Error('listener_failed')); });
      listener.once('exit', () => { clearTimeout(timer); if (!webhook) reject(new Error('listener_exited')); });
    });
    inspectRemoteDestinations({ ...baseEnv, STRIPE_API_KEY: key });
    await native('create');
    if (scenario === 'recovery') {
      const interrupted = await native('confirm');
      report.checks.confirm_response_interrupted = interrupted.status === 'interrupted' && interrupted.response_observed_by_sdk === false;
      const deadline = Date.now() + 30000;
      while (!fs.existsSync(`${evidence}/lost-callback.json`) && !cancelled && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 200));
      if (!fs.existsSync(`${evidence}/lost-callback.json`)) fail('lost_callback_not_observed');
      const before = await native('inspect'), unresolved = journalState();
      report.journal_before_recovery = { unresolved: unresolved.unresolved, reconciled: unresolved.reconciled };
      report.checks.unpaid_during_unknown = unpaidNative(before) && unresolved.unresolved.length === 1 && !packet;
      if (Object.values(report.checks).includes(false)) fail('interruption_assertion_failed');
      const reconciled = await native('reconcile'), resolved = journalState();
      report.checks.same_intent_confirm_reconciled = reconciled.confirmation_reconciled === true && unpaidNative(reconciled)
        && resolved.unresolved.length === 0 && resolved.reconciled.length === 1;
      if (!report.checks.same_intent_confirm_reconciled) fail('confirmation_reconciliation_failed');
      const paid = await native('recover_callback'), replay = await native('recover_replay');
      report.checks.api_event_recovery_once = paid.recovery_event_verified === true && completedNative(paid);
      report.checks.recovery_replay_once = replay.payment_id === paid.payment_id && replay.order_id === paid.order_id
        && completedNative(replay);
      if (Object.values(report.checks).includes(false)) fail('recovery_assertion_failed');
      const refunded = await native('refund'), durable = await native('inspect');
      report.checks.native_refund = refunded.payment_state === 'refunded' && refunded.refunded_full === true;
      report.checks.refund_persisted = durable.payment_id === paid.payment_id && durable.payment_state === 'refunded' && durable.refunded_full === true;
      report.checks.credentials_not_saved = Object.values(report.phases).filter(x => x.status !== 'interrupted').every(x => x.credentials_persisted === false);
      if (Object.values(report.checks).includes(false)) fail('recovery_assertion_failed');
      report.status = 'passed'; report.classification = 'test-provider proven: injected response and callback-processing loss recovery only';
      return;
    }
    if (scenario === 'abandonment') {
      const waiting = await native('observe');
      report.checks.abandoned_unconfirmed = unpaidNative(waiting) && waiting.provider_status === 'requires_payment_method' && waiting.amount_received === 0;
      await native('cancel');
    }
    else {
      await native('confirm');
      if (scenario !== 'success') {
        const waiting = await native('observe');
        report.checks.expected_nonpayment = unpaidNative(waiting) && waiting.amount_received === 0
          && (scenario === 'decline' ? waiting.provider_status === 'requires_payment_method' && waiting.decline_verified === true
            : waiting.provider_status === 'requires_action' && waiting.action_required === true);
      }
    }
    const deadline = Date.now() + 30000;
    while (!packet && !cancelled && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 200));
    if (!packet) fail('own_signed_callback_not_received');
    const paid = await native('callback');
    const replay = await native('replay');
    if (scenario !== 'success') {
      report.checks.signed_nonpayment_callback = paid.callback_verified === true && paid.event_type === eventFor(scenario);
      report.checks.nonpayment_replay_no_receipt = unpaidNative(paid) && unpaidNative(replay) && paid.order_id === replay.order_id;
      if (scenario !== 'abandonment') await native('cancel');
      const durable = await native('observe');
      report.checks.provider_canceled_unpaid = durable.provider_status === 'canceled' && durable.amount_received === 0 && unpaidNative(durable);
      report.checks.credentials_not_saved = Object.values(report.phases).every(x => x.credentials_persisted === false);
      if (Object.values(report.checks).includes(false)) fail('nonpayment_assertion_failed');
      report.status = 'passed'; report.classification = 'test-provider proven: native nonpayment boundary only';
      return;
    }
    report.checks.native_paid_once = paid.payment_count === 1 && paid.payment_state === 'completed' && paid.balance_zero === true && paid.order_state !== 'draft';
    report.checks.signed_callback = paid.callback_verified === true && /^evt_/.test(paid.event_id);
    report.checks.replay_no_duplicate = replay.payment_count === 1 && replay.payment_id === paid.payment_id && replay.order_id === paid.order_id;
    report.checks.memory_receipt_once = paid.captured_mail_count === 1 && replay.captured_mail_count === 1;
    if (Object.values(report.checks).includes(false)) fail('native_assertion_failed');
    const refunded = await native('refund');
    const durable = await native('inspect');
    report.checks.native_refund = refunded.payment_state === 'refunded' && refunded.refunded_full === true;
    report.checks.refund_persisted = durable.payment_id === paid.payment_id && durable.payment_state === 'refunded' && durable.refunded_full === true;
    report.checks.credentials_not_saved = Object.values(report.phases).every(x => x.credentials_persisted === false);
    if (Object.values(report.checks).includes(false)) fail('native_assertion_failed');
    report.status = 'passed'; report.classification = 'test-provider proven: native bridge only';
  } catch (error) {
    report.status = 'failed';
    report.reason = /^[a-z_]+$/.test(error.message) ? error.message : 'probe_failed';
    process.exitCode = 2;
  } finally {
    await finalizeProbe({
      collect: () => {
        // Durable sanitized journals are written directly outside the disposable
        // runtime BEFORE network dispatch. Cleanup never removes these journals.
        const binding = JSON.parse(fs.readFileSync(bindingPath));
        report.intent_id = binding.intent_id ?? null; report.event_id = binding.event_id ?? null;
        const { attempts, results, unresolved, reconciled } = journalState();
        report.intent_id ||= results.find(x => x.path === '/v1/payment_intents')?.provider_object_id ?? null;
        report.provider_write_attempts = attempts.filter(x => x.method === 'post').length;
        report.journal_after_recovery = { unresolved, reconciled };
        report.uncertain_provider_response = unresolved.length !== 0;
        if (report.status === 'passed' && unresolved.length) fail('unresolved_provider_response');
      },
      cleanup: async () => {
        // Shut down sockets in a nested try; even a shutdown error cannot prevent
        // the synchronous secret unlink and exact-root cleanup in finally.
        try {
          if (listener?.pid) await stopOwnedGroup(listener.pid);
          if (server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
        } finally {
          key = undefined; webhook = undefined; packet = undefined;
          process.removeListener('SIGINT', cancel); process.removeListener('SIGTERM', cancel);
          const permissions = spawnSync('chmod', ['-R', 'u+rwX', runtime], { env: baseEnv, stdio: 'ignore' });
          if (permissions.status === 0) fs.rmSync(runtime, { recursive: true });
          if (fs.existsSync(runtime)) fail('disposable_cleanup_failed');
        }
      },
      persist: ({ collectionFailed, cleanupFailed }) => {
        if (collectionFailed) {
          report.status = 'failed'; report.reason = 'evidence_collection_failed';
          report.uncertain_provider_response = true; report.provider_write_attempts ??= 'unknown'; process.exitCode = 2;
        }
        if (cleanupFailed) { report.status = 'failed'; report.reason = 'disposable_cleanup_failed'; process.exitCode = 2; }
        report.disposable_runtime_removed = !fs.existsSync(runtime);
        if (!report.disposable_runtime_removed) report.cleanup_required_path = runtime;
        report.completed_at = new Date().toISOString();
        report.provider_objects_retained_in_test_mode = report.intent_id ? true : (report.uncertain_provider_response ? 'unknown' : false);
        report.test_refund_confirmed = report.checks.refund_persisted === true;
        report.test_cancellation_confirmed = report.checks.provider_canceled_unpaid === true;
        report.reconciliation_required = report.uncertain_provider_response === true
          || Boolean(report.intent_id && !report.test_refund_confirmed && !report.test_cancellation_confirmed);
        // Raw SDK/CLI outputs, keys, SQLite and signed payload never enter evidence.
        try { writePrivate(`${evidence}/evidence.json`, report); }
        catch {
          report.status = 'failed'; report.reason = 'receipt_persist_failed'; report.reconciliation_required = true;
          report.uncertain_provider_response = true; process.exitCode = 2;
        }
        console.log(JSON.stringify({ ...report, evidence_file: `${evidence}/evidence.json` }, null, 2));
      },
    });
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(error => {
    console.log(JSON.stringify({ status: 'refused', reason: /^[a-z_]+$/.test(error.message) ? error.message : 'unexpected_probe_failure', payment_success_claimed: false }));
    process.exitCode = 2;
  });
}
