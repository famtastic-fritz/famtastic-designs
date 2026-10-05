import { createHash } from 'node:crypto';

const E164 = /^\+[1-9]\d{7,14}$/;
const TOKEN = /\{\{([a-z][a-z0-9_]*)\}\}/g;
const PROVIDER_STATES = new Set(['provider_accepted', 'sent', 'delivered', 'failed', 'unknown']);

export class SmsWorkflowError extends Error {
  constructor(code) {
    super(code);
    this.name = 'SmsWorkflowError';
    this.code = code;
  }
}

function requiredText(value, code) {
  if (typeof value !== 'string' || !value.trim()) throw new SmsWorkflowError(code);
  return value.trim();
}

export function renderSmsTemplate(template, values = {}) {
  if (!template || template.status !== 'approved' || !template.id || !template.revision) {
    throw new SmsWorkflowError('approved_template_required');
  }
  const body = requiredText(template.body, 'template_body_required');
  const fields = [...new Set([...body.matchAll(TOKEN)].map((match) => match[1]))];
  if (!Array.isArray(template.fields) || fields.some((field) => !template.fields.includes(field))) {
    throw new SmsWorkflowError('template_field_contract_invalid');
  }
  const rendered = body.replace(TOKEN, (_, field) => {
    const value = values[field];
    if (typeof value !== 'string' || !value.trim() || /[\r\n]/.test(value)) {
      throw new SmsWorkflowError('template_value_invalid');
    }
    return value.trim();
  });
  if (/\{\{|\}\}/.test(rendered) || [...rendered].length > 160) {
    throw new SmsWorkflowError('sms_segment_limit_exceeded');
  }
  return rendered;
}

export function assessReminderEligibility({ appointment, consent, suppression, now = new Date() }) {
  if (!appointment || appointment.state !== 'confirmed' || !Number.isSafeInteger(appointment.version)
    || appointment.version < 1 || !E164.test(appointment.phone ?? '')) return 'appointment_not_eligible';
  const when = new Date(appointment.startsAt);
  if (Number.isNaN(when.getTime()) || when <= new Date(now)) return 'appointment_not_eligible';
  if (!consent || consent.channel !== 'sms' || consent.state !== 'opted_in'
    || consent.phone !== appointment.phone || !consent.wordingVersion || !consent.recordedAt) {
    return 'sms_consent_missing';
  }
  if (suppression?.active === true) return 'sms_suppressed';
  return 'eligible';
}

export function assessQuota({ used, reserved, limit }) {
  if (![used, reserved, limit].every((count) => Number.isSafeInteger(count) && count >= 0)) {
    throw new SmsWorkflowError('quota_contract_invalid');
  }
  return { available: used + reserved < limit, remaining: Math.max(0, limit - used - reserved) };
}

export function reminderKey({ businessId, appointmentId, appointmentVersion, templateId, templateRevision, phone }) {
  const parts = [businessId, appointmentId, templateId, templateRevision, phone].map((part) => requiredText(part, 'reminder_key_invalid'));
  if (!Number.isSafeInteger(appointmentVersion) || appointmentVersion < 1 || !E164.test(phone)) {
    throw new SmsWorkflowError('reminder_key_invalid');
  }
  return `sms-reminder-v1:${createHash('sha256').update(JSON.stringify([...parts, appointmentVersion])).digest('hex')}`;
}

// The host's reserve MUST atomically re-read appointment, consent and suppression,
// claim the unique key, and reserve one unit of its business/sender quota. The
// package cannot establish those guarantees without the customer's database.
export function createSmsWorkflow({ enabled = false, store, transport, clock = () => new Date() } = {}) {
  if (enabled !== true) return Object.freeze({
    async sendReminder() { throw new SmsWorkflowError('workflow_disabled'); },
  });
  if (!store || typeof store.reserve !== 'function' || typeof store.recordOutcome !== 'function'
    || store.atomicReserve !== true) throw new SmsWorkflowError('durable_store_required');
  if (!transport || typeof transport.send !== 'function') throw new SmsWorkflowError('transport_required');

  return Object.freeze({
    async sendReminder({ businessId, appointment, consent, suppression, template, values, quota }) {
      const eligibility = assessReminderEligibility({ appointment, consent, suppression, now: clock() });
      if (eligibility !== 'eligible') return { state: 'not_sent', reason: eligibility };
      const message = renderSmsTemplate(template, values);
      const idempotencyKey = reminderKey({ businessId, appointmentId: appointment.id,
        appointmentVersion: appointment.version, templateId: template.id,
        templateRevision: template.revision, phone: appointment.phone });
      if (!quota || !Number.isSafeInteger(quota.dailyLimit) || quota.dailyLimit < 0) {
        throw new SmsWorkflowError('quota_contract_invalid');
      }
      const reservation = await store.reserve({ businessId, appointmentId: appointment.id,
        appointmentVersion: appointment.version, phone: appointment.phone,
        consentRecordedAt: consent.recordedAt, templateId: template.id,
        templateRevision: template.revision, idempotencyKey, dailyLimit: quota.dailyLimit,
        quotaUnits: 1, message });
      if (reservation?.state === 'duplicate') return { state: 'duplicate', idempotencyKey };
      if (reservation?.state === 'quota_exceeded') return { state: 'not_sent', reason: 'quota_exceeded' };
      if (reservation?.state === 'ineligible') return { state: 'not_sent', reason: 'eligibility_changed' };
      if (reservation?.state !== 'reserved' || typeof reservation.leaseId !== 'string'
        || !reservation.leaseId) throw new SmsWorkflowError('reservation_contract_invalid');

      let outcome;
      try {
        outcome = await transport.send({ to: appointment.phone, message });
      } catch {
        outcome = { state: 'unknown' }; // A network error may follow provider acceptance.
      }
      if (!outcome || !['provider_accepted', 'failed', 'unknown'].includes(outcome.state)) {
        outcome = { state: 'unknown' };
      }
      try {
        await store.recordOutcome({ businessId, idempotencyKey, leaseId: reservation.leaseId,
          state: outcome.state, providerReference: outcome.batchId ?? null, at: clock().toISOString() });
      } catch {
        // Never retry: the SMS may have left the device/provider already.
        return { state: 'outcome_unknown', reason: 'audit_write_failed', idempotencyKey };
      }
      return { state: outcome.state, idempotencyKey, providerReference: outcome.batchId ?? null };
    },
  });
}

// Only a verified, business-matched provider event may call this function. The
// customer host owns raw-body signature verification, event deduplication and
// durable audit. Conflicting terminal evidence needs owner review.
export function reconcileProviderStatus(current, incoming) {
  if (!PROVIDER_STATES.has(incoming)) throw new SmsWorkflowError('provider_state_invalid');
  if (incoming === 'unknown') return current ?? 'unknown';
  if (current === 'delivered') return incoming === 'delivered' ? 'delivered' : 'needs_review';
  if (current === 'failed') return incoming === 'failed' ? 'failed' : 'needs_review';
  if (incoming === 'delivered' || incoming === 'failed') return incoming;
  if (current === 'sent' && incoming === 'provider_accepted') return 'sent';
  return incoming;
}
