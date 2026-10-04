import assert from 'node:assert/strict';
import { createHmac } from 'node:crypto';
import test from 'node:test';
import { classifyReply, createTextbeeLabAdapter, verifyTextbeeWebhook } from '../src/index.mjs';

const key = 'fictional-test-key';
const to = '+12025550123';

test('lab stays off unless explicitly enabled and recipient allowlisted', async () => {
  assert.throws(() => createTextbeeLabAdapter({ apiKey: key, allowedRecipients: [to] }), /lab_not_enabled/);
  const adapter = createTextbeeLabAdapter({ enabled: true, apiKey: key, allowedRecipients: [to], fetchImpl: () => { throw Error('network reached'); } });
  await assert.rejects(adapter.send({ to: '+12025550124', message: 'test' }), /recipient_not_allowlisted/);
});

test('one allowlisted lab send is provider accepted, not called delivered', async () => {
  let calls = 0;
  const adapter = createTextbeeLabAdapter({ enabled: true, apiKey: key, allowedRecipients: [to], fetchImpl: async (url, opts) => {
    calls++;
    assert.equal(url.href, 'https://api.textbee.dev/api/v1/gateway/send-sms');
    assert.equal(opts.headers['x-api-key'], key);
    assert.deepEqual(JSON.parse(opts.body), { recipients: [to], message: 'FAMtastic lab test' });
    return { ok: true, json: async () => ({ data: { smsBatchId: 'fictional-batch' } }) };
  } });
  assert.deepEqual(await adapter.send({ to, message: 'FAMtastic lab test' }), { state: 'provider_accepted', provider: 'textbee', batchId: 'fictional-batch' });
  assert.equal(calls, 1);
});

test('uncertain send is never retried', async () => {
  let calls = 0;
  const adapter = createTextbeeLabAdapter({ enabled: true, apiKey: key, allowedRecipients: [to], fetchImpl: async () => { calls++; throw Error('timeout'); } });
  await assert.rejects(adapter.send({ to, message: 'test' }), /provider_outcome_uncertain/);
  assert.equal(calls, 1);
});

test('signed webhook verifies raw bytes and exposes idempotency key', () => {
  const secret = 'fictional-test-secret-32-chars-long';
  const rawBody = Buffer.from(JSON.stringify({ webhookEvent: 'MESSAGE_RECEIVED', idempotencyKey: 'fixture-1', smsId: 'fixture-sms', sender: to, message: 'YES' }));
  const signature = createHmac('sha256', secret).update(rawBody).digest('hex');
  const event = verifyTextbeeWebhook({ rawBody, signature, signingSecret: secret });
  assert.equal(event.idempotencyKey, 'fixture-1');
  assert.equal(classifyReply(event.message), 'attendance_confirmation_requested');
  assert.throws(() => verifyTextbeeWebhook({ rawBody: Buffer.from(rawBody.toString().replace('YES', 'STOP')), signature, signingSecret: secret }), /webhook_signature_invalid/);
});

test('STOP suppresses messaging, while NO is follow-up rather than unsubscribe', () => {
  assert.equal(classifyReply(' STOP '), 'suppress_sms');
  assert.equal(classifyReply('Please stop texting me'), 'suppress_sms');
  assert.equal(classifyReply('NO'), 'owner_follow_up_requested');
});
