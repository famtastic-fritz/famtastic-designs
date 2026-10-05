import assert from 'node:assert/strict';
import test from 'node:test';
import { assessQuota, assessReminderEligibility, createSmsWorkflow, reconcileProviderStatus,
  reminderKey, renderSmsTemplate } from '../src/index.mjs';

const now = new Date('2030-01-01T12:00:00Z');
const appointment = { id: 'fictional-appointment-1', version: 3, state: 'confirmed',
  phone: '+12025550123', startsAt: '2030-01-02T15:00:00Z' };
const consent = { channel: 'sms', state: 'opted_in', phone: appointment.phone,
  wordingVersion: 'sms-opt-in-v1', recordedAt: '2029-12-01T12:00:00Z' };
const template = { id: 'appointment_reminder', revision: '1', status: 'approved',
  fields: ['first_name', 'time'], body: 'Hi {{first_name}}, your appointment is {{time}}. Reply STOP to opt out.' };
const values = { first_name: 'Taylor', time: 'Jan 2 at 10 AM' };

function fakeHost({ reservation = { state: 'reserved', leaseId: 'fictional-lease-1' },
  result = { state: 'provider_accepted', batchId: 'fictional-batch-1' } } = {}) {
  const log = [];
  const store = { atomicReserve: true,
    async reserve(args) { log.push(['reserve', args]); return reservation; },
    async recordOutcome(args) { log.push(['outcome', args]); },
  };
  const transport = { async send(args) { log.push(['send', args]); return result; } };
  return { store, transport, log };
}

test('disabled default never touches a provider or store', async () => {
  const workflow = createSmsWorkflow();
  await assert.rejects(workflow.sendReminder({}), /workflow_disabled/);
});

test('consent, suppression and confirmed authority are separate gates', () => {
  const input = { appointment, consent, now };
  assert.equal(assessReminderEligibility(input), 'eligible');
  assert.equal(assessReminderEligibility({ ...input, consent: null }), 'sms_consent_missing');
  assert.equal(assessReminderEligibility({ ...input, suppression: { active: true } }), 'sms_suppressed');
  assert.equal(assessReminderEligibility({ ...input, appointment: { ...appointment, state: 'requested' } }), 'appointment_not_eligible');
  assert.equal(assessReminderEligibility({ ...input, appointment: { ...appointment, startsAt: '2029-12-31T12:00:00Z' } }), 'appointment_not_eligible');
});

test('only approved, bounded templates render; no unresolved fields', () => {
  assert.match(renderSmsTemplate(template, values), /Reply STOP to opt out/);
  assert.throws(() => renderSmsTemplate({ ...template, status: 'draft' }, values), /approved_template_required/);
  assert.throws(() => renderSmsTemplate(template, { ...values, first_name: '' }), /template_value_invalid/);
  assert.throws(() => renderSmsTemplate(template, { ...values, first_name: 'A\nB' }), /template_value_invalid/);
  assert.throws(() => renderSmsTemplate({ ...template, body: 'x'.repeat(161) }, values), /sms_segment_limit_exceeded/);
});

test('quota counts committed plus reserved; key changes with appointment version', () => {
  assert.deepEqual(assessQuota({ used: 8, reserved: 1, limit: 10 }), { available: true, remaining: 1 });
  assert.deepEqual(assessQuota({ used: 8, reserved: 2, limit: 10 }), { available: false, remaining: 0 });
  const base = { businessId: 'fictional-business', appointmentId: appointment.id,
    appointmentVersion: 3, templateId: template.id, templateRevision: template.revision, phone: appointment.phone };
  assert.notEqual(reminderKey(base), reminderKey({ ...base, appointmentVersion: 4 }));
  assert.equal(reminderKey(base).includes(appointment.phone), false);
});

test('atomic reservation precedes exactly one provider send and audit', async () => {
  const host = fakeHost();
  const workflow = createSmsWorkflow({ enabled: true, ...host, clock: () => now });
  const result = await workflow.sendReminder({ businessId: 'fictional-business', appointment,
    consent, template, values, quota: { dailyLimit: 20 } });
  assert.equal(result.state, 'provider_accepted');
  assert.deepEqual(host.log.map(([event]) => event), ['reserve', 'send', 'outcome']);
  assert.equal(host.log[0][1].dailyLimit, 20);
  assert.equal(host.log[2][1].providerReference, 'fictional-batch-1');
});

test('duplicate, quota exhaustion, suppression and changed authority never send', async () => {
  for (const reservation of [{ state: 'duplicate' }, { state: 'quota_exceeded' }, { state: 'ineligible' }]) {
    const host = fakeHost({ reservation });
    const workflow = createSmsWorkflow({ enabled: true, ...host, clock: () => now });
    await workflow.sendReminder({ businessId: 'fictional-business', appointment,
      consent, template, values, quota: { dailyLimit: 20 } });
    assert.deepEqual(host.log.map(([event]) => event), ['reserve']);
  }
  const host = fakeHost();
  const workflow = createSmsWorkflow({ enabled: true, ...host, clock: () => now });
  await workflow.sendReminder({ businessId: 'fictional-business', appointment, consent,
    suppression: { active: true }, template, values, quota: { dailyLimit: 20 } });
  assert.equal(host.log.length, 0);
});

test('provider timeout and audit failure are unknown and never retried', async () => {
  const host = fakeHost();
  host.transport.send = async (args) => { host.log.push(['send', args]); throw Error('timeout'); };
  const workflow = createSmsWorkflow({ enabled: true, ...host, clock: () => now });
  const result = await workflow.sendReminder({ businessId: 'fictional-business', appointment,
    consent, template, values, quota: { dailyLimit: 20 } });
  assert.equal(result.state, 'unknown');
  assert.deepEqual(host.log.map(([event]) => event), ['reserve', 'send', 'outcome']);

  const lostAudit = fakeHost();
  lostAudit.store.recordOutcome = async () => { throw Error('storage unavailable'); };
  const result2 = await createSmsWorkflow({ enabled: true, ...lostAudit, clock: () => now })
    .sendReminder({ businessId: 'fictional-business', appointment, consent,
      template, values, quota: { dailyLimit: 20 } });
  assert.equal(result2.state, 'outcome_unknown');
  assert.equal(lostAudit.log.filter(([event]) => event === 'send').length, 1);
});

test('durable transactional store is required and provider status never regresses', () => {
  assert.throws(() => createSmsWorkflow({ enabled: true, store: { reserve() {}, recordOutcome() {} },
    transport: { send() {} } }), /durable_store_required/);
  assert.equal(reconcileProviderStatus('sent', 'provider_accepted'), 'sent');
  assert.equal(reconcileProviderStatus('delivered', 'failed'), 'needs_review');
  assert.equal(reconcileProviderStatus('delivered', 'unknown'), 'delivered');
  assert.equal(reconcileProviderStatus('provider_accepted', 'delivered'), 'delivered');
});
