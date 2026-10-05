# Customer messaging pilot — FAMtastic Designs

Status: `source_only`, `fictional_lab_ready`; Fritz reports his own Textbee phone test, but this repository has no independently inspected send/delivery/reply receipt. There is no public/admin UI, Drupal integration, customer sender, product price or launch from this record.

Fritz wants a repeatable reminder/reply capability for service clients. The neutral contract and Node reference package live in Component Studio `consent-sms-loop` 0.1.0, pinned in `tools/customer-messaging/SOURCE.md`. The agency site has a **manual, private technical probe** at `scripts/textbee-lab.mjs`; it never reads customer records and cannot send to a destination outside the one exact runtime allowlist.

On Fritz's Mac, run `node scripts/textbee-lab.mjs --setup` in an interactive terminal. The script invokes macOS Keychain's hidden prompts for the Textbee API key and Fritz's own E.164 test number. Neither value belongs in chat, shell arguments, shell history, a committed `.env` or a transcript. `--check` reports readiness without printing either value or sending. `--send` shows the fixed fictional message and last four digits, then requires Fritz to type `SEND` for one attempt. `--forget` removes the two local Keychain items after confirmation. A Keychain prompt is local setup only; it does not configure the agency website or Shay's site.

The older environment-only path remains for explicitly controlled automation: `TEXTBEE_LAB_ENABLED=1`, `TEXTBEE_API_KEY`, `TEXTBEE_LAB_TO` (Fritz-controlled E.164 number), and `TEXTBEE_LAB_ACK=FRITZ_ONLY_FICTIONAL_TEST`. If either credential value is provided through the environment, the script never mixes it with a Keychain value. This path should use a private secret channel, not a pasted shell command. The script makes one send attempt only; an uncertain outcome must be inspected in Textbee before another attempt.

The output reports `provider_accepted`, not handset delivery. Confirm recipient receipt and a YES reply on the test devices/Textbee dashboard. A signed webhook into an authenticated application is a later integration, not proven by this CLI. Textbee's [webhook specification](https://textbee.dev/docs/webhooks) requires public HTTPS, raw-body HMAC verification and duplicate suppression. Remove the temporary test subscription after any tunnel-based lab.

## Future FAMtastic Designs service

FAMtastic Designs may manage onboarding and a staff setup dashboard, but each customer's site must own consent, appointment authority, outbox, suppression and owner reply review. The agency runtime must not become the booking or customer-record authority. A real launch needs a sanctioned business SMS sender for each client/brand, approved copy/consent, privacy review, provider cost and quota disclosure, per-client integration and release-matched phone acceptance. The umbrella contract is `docs/agent-startup/CUSTOMER-MESSAGING-CONTRACT.v1.md` in the FAMtastic root; this repo keeps a link rather than a duplicate platform spec.

The existing business email offer is email-only. T-Mobile explicitly [prohibits business messages on its email-to-text gateway](https://www.t-mobile.com/support/plans-features/consumer-versus-non-consumer-text-messaging). Do not route reminder texts through a mailbox or personal phone in production.
