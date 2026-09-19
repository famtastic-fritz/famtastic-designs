import crypto from 'node:crypto';

const hash = text => crypto.createHash('sha256').update(text).digest('hex');

// An arbitrary child failure is never an expected interruption. Match the exact
// durable attempt and prove no response for it was recorded, including offline.
export function expectedInterruption(result, fault, attempts, responses, binding) {
  const original = attempts.find(row => row.seq === fault?.seq);
  return result.status === 86 && result.stdout === '' && result.signal === null && result.killed === false
    && binding.scenario === 'recovery' && fault?.fault === 'exit_before_response_observation' && fault.exit_code === 86
    && fault.run_id === binding.run_id && fault.account_id === 'acct_1TqwE9DDGtWR2WVN'
    && fault.intent_id === binding.intent_id && fault.method === 'post'
    && fault.path === `/v1/payment_intents/${binding.intent_id}/confirm`
    && fault.params_sha256 === hash(JSON.stringify({ payment_method: 'pm_card_visa' }))
    && fault.idempotency_sha256 === hash(`${binding.run_id}-confirm`)
    && Number.isInteger(fault.seq) && fault.seq > 0 && Boolean(original)
    && attempts.filter(row => row.seq === fault.seq).length === 1
    && Object.entries(original).every(([key, value]) => fault[key] === value)
    && !responses.some(row => row.seq === fault.seq);
}
// A missing response is never erased. Exactly one narrowly evidenced confirm
// operation may be covered by a read + identical provider-idempotent replay.
export function assessJournal(attempts, results, resolution, binding) {
  const refuse = () => { throw new Error('journal_reconciliation_refused'); };
  const bySeq = new Map(), responses = new Map();
  for (const row of attempts) {
    if (!Number.isInteger(row.seq) || row.seq < 1 || bySeq.has(row.seq) || row.run_id !== binding.run_id
      || row.account_id !== 'acct_1TqwE9DDGtWR2WVN') refuse();
    bySeq.set(row.seq, row);
  }
  for (const row of results) {
    const attempt = bySeq.get(row.seq);
    if (!attempt || responses.has(row.seq) || row.path !== attempt.path || row.method !== attempt.method) refuse();
    responses.set(row.seq, row);
  }
  const missing = attempts.filter(row => !responses.has(row.seq));
  if (!resolution) return { unresolved: missing.map(row => row.seq), reconciled: [] };
  if (missing.length !== 1 || resolution.schema !== 'famtastic.native-confirm-reconciliation.v1'
    || binding.scenario !== 'recovery' || resolution.run_id !== binding.run_id || resolution.intent_id !== binding.intent_id
    || resolution.account_id !== binding.verified_account || resolution.account_id !== 'acct_1TqwE9DDGtWR2WVN'
    || resolution.livemode !== false || resolution.amount_received !== 19900 || resolution.currency !== 'usd'
    || resolution.provider_status !== 'succeeded' || !/^ch_[A-Za-z0-9]+$/.test(resolution.charge_id ?? '')) refuse();
  const original = missing[0], read = bySeq.get(resolution.read_seq), replay = bySeq.get(resolution.replay_seq);
  const readResult = responses.get(resolution.read_seq), replayResult = responses.get(resolution.replay_seq);
  if (original.seq !== resolution.covered_seq || original.method !== 'post'
    || original.path !== `/v1/payment_intents/${binding.intent_id}/confirm` || original.intent_id !== binding.intent_id
    || original.params_sha256 !== hash(JSON.stringify({ payment_method: 'pm_card_visa' }))
    || original.idempotency_sha256 !== hash(`${binding.run_id}-confirm`)
    || !read || read.seq <= original.seq || read.method !== 'get' || read.path !== `/v1/payment_intents/${binding.intent_id}`
    || readResult?.status !== 200 || readResult.provider_object_id !== binding.intent_id
    || !replay || replay.seq <= read.seq || replay.intent_id !== binding.intent_id
    || replay.path !== original.path || replay.method !== original.method
    || replay.params_sha256 !== original.params_sha256 || replay.idempotency_sha256 !== original.idempotency_sha256
    || replayResult?.status !== 200 || replayResult.provider_object_id !== binding.intent_id || replayResult.idempotent_replayed !== true) refuse();
  return { unresolved: [], reconciled: [original.seq] };
}
