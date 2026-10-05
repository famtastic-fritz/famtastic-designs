# Consent SMS loop 0.1.0

Build-time package for a business-owned reminder and reply workflow. It contains a small Node reference adapter for an **allowlisted Textbee technical lab**, signed webhook verification, and reply-intent classification. The customer's site owns its own appointment authority, consent/suppression data, outbox, sender, webhook route, and owner inbox. No agency runtime service or credentials are installed with this package.

## What it does today

- A lab send requires `enabled: true`, an API key supplied privately at runtime, and an exact E.164 recipient allowlist. The adapter sends one SMS and makes no automatic retries or fallback sends. An accepted API response is `provider_accepted`, never `delivered`.
- Signed Textbee webhooks are checked over the raw request bytes using HMAC-SHA256 and a constant-time comparison. The host must persist `idempotencyKey` with a unique index before acting on an event, acknowledge quickly, and keep secrets outside source.
- `STOP` and equivalent single-word commands, plus clear opt-out phrases, classify as `suppress_sms`; `YES` requests attendance confirmation for an already-confirmed appointment; `NO` requests owner follow-up. None of these directly changes a booking. A host must match the sender and message to one current appointment and perform the authoritative write.

## Independent install

From a pinned Component Studio checkout:

```sh
node scripts/install-package.mjs consent-sms-loop /absolute/empty/destination
cd /absolute/empty/destination
npm test
```

The installer copies source, tests, and a hashed receipt to an empty independent directory. This is library install proof, not a site integration or production sender.

## Host integration contract

1. Keep appointment and consent tables in the customer's own application. Text consent is separate from email/newsletter consent; log the source, wording/version, timestamp and phone. Never infer consent from a phone number alone.
2. Enqueue reminders only for durable confirmed appointments. Recheck appointment version, time, recipient, consent and suppression immediately before send. Edits/cancellations supersede unsent messages.
3. Use one durable idempotency key per appointment/version/reminder window. Do not automatically repeat a send with an uncertain outcome.
4. Use a registered/approved business SMS route for production. The Textbee adapter here is a controlled lab; a personal-SIM or carrier email-to-text gateway is not a customer-sending fallback.
5. Receive provider events through a publicly reachable HTTPS webhook. Verify its signature first, deduplicate the event, and distinguish `provider_accepted`, `sent`, `delivered`, `failed`, `unknown` and customer reply. A missing delivery report is not delivery proof.
6. Surface STOP suppression and unmatched/ambiguous replies to the owner. Do not send after STOP. Do not use YES to turn a request into a reserved appointment or NO to cancel it without owner policy and durable conflict checks.
7. Use a per-business sender/brand, scoped secret, consent ledger and suppression list. FAMtastic may manage provider setup but customer records stay in the independent site.

## Current limits

No live Textbee key, phone, webhook subscription, real SMS, production business route, customer consent, recipient, or hosted install was used to validate this package. The reference code is Node 20+ and needs a host-specific adapter for Laravel/Drupal. Textbee cloud processing must receive a privacy review before any customer data is sent. A future real client release requires a registered sender, current provider terms, acceptance tests, and an owner handoff.

Sources checked 2026-10-04: [Textbee send](https://textbee.dev/docs/sending-sms/sending-sms), [webhooks](https://textbee.dev/docs/webhooks), [delivery states](https://textbee.dev/docs/sending-sms/delivery-status), [T-Mobile business messaging](https://www.t-mobile.com/support/plans-features/consumer-versus-non-consumer-text-messaging).
