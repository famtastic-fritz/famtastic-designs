import { createHash, createHmac, randomBytes } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const digest = value => createHash('sha256').update(value).digest('hex');

export function signedHeaders(operation, wire, worker, secret, now = Date.now(), nonce = randomBytes(16).toString('hex')) {
  if (!/^(claim|renew|finish|fail)$/.test(operation) || !/^[a-z][a-z0-9._-]{2,63}$/.test(worker) || secret.length < 32) throw new Error('worker_configuration_invalid');
  const timestamp = String(Math.floor(now / 1000));
  const path = `/api/pipeline/worker/${operation}`;
  const input = ['POST', path, worker, timestamp, nonce, digest(wire)].join('\n');
  return { 'Content-Type': 'application/json', 'X-FAMtastic-Worker': worker, 'X-FAMtastic-Timestamp': timestamp,
    'X-FAMtastic-Nonce': nonce, 'X-FAMtastic-Signature': `sha256=${createHmac('sha256', secret).update(input).digest('hex')}` };
}

export function validateEndpoint(value, path, allowLoopback = false) {
  const u = new URL(value);
  const local = ['localhost', '127.0.0.1', '[::1]'].includes(u.hostname);
  if (u.username || u.password || u.search || u.hash || u.pathname !== path || !(u.protocol === 'https:' || (allowLoopback && local && u.protocol === 'http:'))) throw new Error('worker_endpoint_invalid');
  return u.href.replace(/\/$/, '');
}

export function validateClaim(claim, now = Date.now()) {
  if (claim.capability !== 'selected-static-dispatch-v1' || claim.policy_version !== 'bounded-workers-v1'
    || !Number.isInteger(claim.job_id) || claim.job_id <= 0 || !/^[a-f0-9]{64}$/.test(claim.lease_token)
    || digest(claim.payload_wire) !== claim.payload_sha256 || claim.lease_until * 1000 <= now
    || claim.execution_deadline * 1000 <= now || claim.execution_deadline * 1000 > now + 300_000) throw new Error('claim_invalid');
  const packet = JSON.parse(claim.payload_wire).packet;
  const continuation = packet?.continuation;
  if (packet?.build_class !== 'prepayment_selected_direction_staging' || !packet.packet_id || !packet.idempotency_key
    || continuation?.spec?.capability_class !== 'static' || Object.keys(continuation.spec.backend ?? {}).length
    || Object.keys(continuation.spec.functional_contract ?? {}).length
    || !['package_existing', 'continue_build'].includes(continuation.operation)
    || continuation.requested_next_action !== 'protected_review') throw new Error('unsupported_implementation_work');
  return packet;
}

/** One claim, one idempotent dispatch, bounded receipt retries. No generation loop. */
export async function runOnce({ api, dispatch, now = Date.now, timers = globalThis }) {
  const response = await api('claim', {});
  if (!response.claim) return { state: 'no_enrolled_work', provider_calls: 0 };
  const claim = response.claim;
  const packet = validateClaim(claim, now());
  const ownership = { job_id: claim.job_id, lease_token: claim.lease_token };
  const abort = new AbortController();
  let renewPending = false;
  let lostLease = false;
  let accepted = false;
  const deadline = timers.setTimeout(() => abort.abort(), Math.max(1, claim.execution_deadline * 1000 - now()));
  const heartbeat = timers.setInterval(async () => {
    if (renewPending || lostLease) return;
    renewPending = true;
    try { await api('renew', ownership); }
    catch { lostLease = true; abort.abort(); }
    finally { renewPending = false; }
  }, 30_000);
  try {
    const result = await dispatch(packet, abort.signal);
    accepted = true;
    if (lostLease || abort.signal.aborted) throw new Error('lease_lost_after_dispatch_reconcile_receipt');
    if (result.status !== 'accepted_waiting_callback' || !result.receipt_id || result.packet_id !== packet.packet_id
      || result.idempotency_key !== packet.idempotency_key) throw new Error('dispatch_receipt_mismatch');
    // A lost HTTP acknowledgement is safe to repeat against the immutable receipt.
    let last;
    for (let i = 0; i < 2; i++) {
      try { await api('finish', { ...ownership, result }); return { state: 'handoff_completed', job_id: claim.job_id, staging_ready: false }; }
      catch (error) { last = error; }
    }
    throw new Error('completion_acknowledgement_uncertain', { cause: last });
  } catch (error) {
    // An accepted-but-unconfirmed handoff is not an excuse to regenerate/re-send mail.
    if (!accepted && !lostLease) { try { await api('fail', ownership); } catch { /* Lease expiry retains budget and exact packet. */ } }
    throw error;
  } finally {
    timers.clearInterval(heartbeat);
    timers.clearTimeout(deadline);
  }
}

async function main() {
  const worker = process.env.FAMTASTIC_WORKER_ID;
  const allowLocal = process.env.FAMTASTIC_WORKER_ALLOW_LOOPBACK === '1';
  const base = validateEndpoint(process.env.FAMTASTIC_WORKER_API_BASE, '/web', allowLocal);
  const studio = validateEndpoint(process.env.SITE_STUDIO_STAGING_URL, '/api/pipeline/staging/accept', allowLocal);
  // Mounted secret files (Cloud Secret Manager or restricted Mac paths), never logged.
  const secret = (await readFile(process.env.FAMTASTIC_WORKER_SECRET_FILE, 'utf8')).trim();
  const studioSecret = (await readFile(process.env.STUDIO_DISPATCH_SECRET_FILE, 'utf8')).trim();
  if (secret.length < 32 || studioSecret.length < 32) throw new Error('worker_secret_invalid');
  const api = async (operation, payload) => {
    const wire = JSON.stringify(payload);
    const response = await fetch(`${base}/api/pipeline/worker/${operation}`, { method: 'POST', body: wire,
      headers: signedHeaders(operation, wire, worker, secret), redirect: 'error', signal: AbortSignal.timeout(10_000) });
    if (!response.ok) throw new Error(`authority_rejected_${response.status}`);
    return response.json();
  };
  const dispatch = async (packet, signal) => {
    const body = JSON.stringify({ packet });
    const response = await fetch(studio, { method: 'POST', body, redirect: 'error', signal: AbortSignal.any([signal, AbortSignal.timeout(30_000)]),
      headers: { 'Content-Type': 'application/json', 'Idempotency-Key': packet.idempotency_key,
        'X-FAMtastic-Signature': `sha256=${createHmac('sha256', studioSecret).update(body).digest('hex')}` } });
    if (response.status !== 202) throw new Error('studio_handoff_rejected');
    const data = await response.json();
    if (data.accepted !== true || data.status !== 'accepted_waiting_callback') throw new Error('studio_handoff_unconfirmed');
    return { status: data.status, ...data.receipt };
  };
  console.log(JSON.stringify(await runOnce({ api, dispatch })));
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(() => { console.error('bounded_worker_failed: inspect the redacted authority receipt; no automatic restart'); process.exitCode = 1; });
}
