# FAMtastic Operations Dashboard

The authenticated Drupal dashboard at `/admin/famtastic` is the operator source
of truth for outreach and proof work. It uses the existing pipeline tables and
entities rather than introducing a disconnected campaign module.

## What an operator can inspect

- campaign source, status, prospects, ready proofs, messages, clicks, sales,
  open jobs, exceptions, and build count;
- each exact recipient, subject, delivery state, timestamp, and proof link;
- the exact rendered email body, envelope From, provider, provider Message-ID,
  and attributed lifecycle events;
- every proof campaign and its three published directions;
- deterministic or Site Studio build provider, agent, flow, task, prompt,
  machine input, output manifest, checksum, release/source SHA, status, and
  error.

Access requires `administer famtastic pipeline`. Message bodies and build inputs
can contain contact or customer intake data and must remain admin-only.

## Historical truth

The first image-free pilot proofs were produced by Drupal's deterministic
renderer. Their backfilled telemetry therefore says provider
`drupal_deterministic_renderer` and agent `none`; no Shay prompt existed for
those builds. Historical email body snapshots can be reconstructed from the
stored campaign message keys with the explicit backfill command documented in
`docs/EMAIL_AUTOMATION.md`.

## Measurement boundary

The current plain-text outreach template measures sends, proof-link clicks,
provider delivery/bounce/complaint events, unsubscribe, selection, payment, and
launch. It does not embed an open-tracking pixel, so the dashboard intentionally
does not present an open rate as reliable campaign evidence.

## Verification

```bash
./scripts/e2e-email-campaign.sh
./scripts/e2e-local-proof-promotion.sh
```

After deployment, log in as a pipeline administrator and verify:

1. `/admin/famtastic` returns 200 and shows the target campaign.
2. The campaign page exposes recipient messages, proof directions, and builds.
3. A message drill-down shows its exact audit snapshot and lifecycle events.
4. A build drill-down shows the prompt/input/output and actual provider/agent.
