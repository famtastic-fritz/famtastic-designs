# Customer messaging pilot — FAMtastic Designs

Status: `source_only`, `fictional_lab_ready`; no Textbee account/key/phone verification, actual SMS, public/admin UI, Drupal integration, customer sender, product price or launch from this record.

Fritz wants a repeatable reminder/reply capability for service clients. The neutral contract and Node reference package live in Component Studio `consent-sms-loop` 0.1.0, pinned in `tools/customer-messaging/SOURCE.md`. The agency site has a **manual, private technical probe** at `scripts/textbee-lab.mjs`; it never reads customer records and cannot send to a destination outside the one exact runtime allowlist. Run `--check` first. A real `--send` requires `TEXTBEE_LAB_ENABLED=1`, `TEXTBEE_API_KEY`, `TEXTBEE_LAB_TO` (a Fritz-controlled E.164 test number), and `TEXTBEE_LAB_ACK=FRITZ_ONLY_FICTIONAL_TEST`. Keep all values in a private secret channel, never a committed `.env`, shell transcript or chat. The script makes one send attempt only; an uncertain outcome must be inspected in Textbee before another attempt.

The output reports `provider_accepted`, not handset delivery. Confirm recipient receipt and a YES reply on the test devices/Textbee dashboard. A signed webhook into an authenticated application is a later integration, not proven by this CLI. Textbee's [webhook specification](https://textbee.dev/docs/webhooks) requires public HTTPS, raw-body HMAC verification and duplicate suppression. Remove the temporary test subscription after any tunnel-based lab.

## Future FAMtastic Designs service

FAMtastic Designs may manage onboarding and a staff setup dashboard, but each customer's site must own consent, appointment authority, outbox, suppression and owner reply review. The agency runtime must not become the booking or customer-record authority. A real launch needs a sanctioned business SMS sender for each client/brand, approved copy/consent, privacy review, provider cost and quota disclosure, per-client integration and release-matched phone acceptance. The umbrella contract is `docs/agent-startup/CUSTOMER-MESSAGING-CONTRACT.v1.md` in the FAMtastic root; this repo keeps a link rather than a duplicate platform spec.

The existing business email offer is email-only. T-Mobile explicitly [prohibits business messages on its email-to-text gateway](https://www.t-mobile.com/support/plans-features/consumer-versus-non-consumer-text-messaging). Do not route reminder texts through a mailbox or personal phone in production.
