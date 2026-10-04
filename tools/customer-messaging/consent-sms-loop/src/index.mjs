import { createHmac, timingSafeEqual } from 'node:crypto';

const E164 = /^\+[1-9]\d{7,14}$/;
const TEXTBEE_EVENTS = new Set([
  'MESSAGE_RECEIVED', 'MESSAGE_SENT', 'MESSAGE_DELIVERED',
  'MESSAGE_FAILED', 'UNKNOWN_STATE',
]);
const STOP_WORDS = new Set(['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT', 'REVOKE', 'OPTOUT']);

export class SmsContractError extends Error {
  constructor(code) {
    super(code);
    this.name = 'SmsContractError';
    this.code = code;
  }
}

export function assertPhone(value) {
  if (typeof value !== 'string' || !E164.test(value)) throw new SmsContractError('e164_phone_required');
  return value;
}

// Intent only. The host must match an already-confirmed appointment and save any
// attendance state itself. A reply must never reserve, cancel or delete a booking.
export function classifyReply(body) {
  const word = typeof body === 'string' ? body.trim().toUpperCase() : '';
  if (STOP_WORDS.has(word) || /\b(?:STOP|UNSUBSCRIBE|OPT OUT)\b/.test(word)) return 'suppress_sms';
  if (word === 'YES' || word === 'Y') return 'attendance_confirmation_requested';
  if (word === 'NO' || word === 'N') return 'owner_follow_up_requested';
  if (word === 'HELP' || word === 'INFO') return 'help_requested';
  return 'owner_review_required';
}

export function verifyTextbeeWebhook({ rawBody, signature, signingSecret }) {
  if (typeof signingSecret !== 'string' || signingSecret.length < 20) throw new SmsContractError('webhook_secret_required');
  const bytes = Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(rawBody ?? '');
  if (!/^[a-f0-9]{64}$/.test(signature ?? '')) throw new SmsContractError('webhook_signature_invalid');
  const expected = createHmac('sha256', signingSecret).update(bytes).digest();
  if (!timingSafeEqual(expected, Buffer.from(signature, 'hex'))) throw new SmsContractError('webhook_signature_invalid');
  let event;
  try { event = JSON.parse(bytes.toString('utf8')); }
  catch { throw new SmsContractError('webhook_json_invalid'); }
  if (!event || typeof event !== 'object' || !TEXTBEE_EVENTS.has(event.webhookEvent)
    || typeof event.idempotencyKey !== 'string' || !event.idempotencyKey
    || typeof event.smsId !== 'string' || !event.smsId) {
    throw new SmsContractError('webhook_event_invalid');
  }
  if (event.webhookEvent === 'MESSAGE_RECEIVED') {
    assertPhone(event.sender);
    if (typeof event.message !== 'string') throw new SmsContractError('webhook_event_invalid');
  }
  return event;
}

// This adapter is intentionally lab-only. No default recipient, bulk send,
// fallback sender, automatic retry or hidden enablement from an API key alone.
export function createTextbeeLabAdapter({
  enabled = false,
  apiKey,
  allowedRecipients = [],
  deviceId,
  fetchImpl = globalThis.fetch,
  baseUrl = 'https://api.textbee.dev/api/v1',
} = {}) {
  if (enabled !== true) throw new SmsContractError('lab_not_enabled');
  if (typeof apiKey !== 'string' || !apiKey.trim()) throw new SmsContractError('api_key_required');
  if (!Array.isArray(allowedRecipients) || allowedRecipients.length === 0) throw new SmsContractError('lab_allowlist_required');
  const allowed = new Set(allowedRecipients.map(assertPhone));
  let parsed;
  try { parsed = new URL(baseUrl); }
  catch { throw new SmsContractError('https_base_url_required'); }
  if (parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.search || parsed.hash) {
    throw new SmsContractError('https_base_url_required');
  }
  if (typeof fetchImpl !== 'function') throw new SmsContractError('http_client_required');

  return Object.freeze({
    async send({ to, message }) {
      assertPhone(to);
      if (!allowed.has(to)) throw new SmsContractError('recipient_not_allowlisted');
      if (typeof message !== 'string' || !message.trim() || [...message].length > 160) {
        throw new SmsContractError('lab_message_invalid');
      }
      const payload = { recipients: [to], message: message.trim() };
      if (deviceId) payload.deviceId = deviceId;
      let response;
      try {
        response = await fetchImpl(new URL('gateway/send-sms', parsed.href.endsWith('/') ? parsed.href : `${parsed.href}/`), {
          method: 'POST',
          headers: { 'content-type': 'application/json', 'x-api-key': apiKey },
          body: JSON.stringify(payload),
          signal: AbortSignal.timeout(10000),
        });
      } catch {
        // The request may have reached Textbee. Never resend automatically.
        throw new SmsContractError('provider_outcome_uncertain');
      }
      if (!response.ok) throw new SmsContractError(`provider_http_${response.status}`);
      let result;
      try { result = await response.json(); }
      catch { throw new SmsContractError('provider_response_unknown'); }
      return {
        state: 'provider_accepted',
        provider: 'textbee',
        batchId: result?.data?.smsBatchId ?? result?.smsBatchId ?? null,
      };
    },
  });
}
