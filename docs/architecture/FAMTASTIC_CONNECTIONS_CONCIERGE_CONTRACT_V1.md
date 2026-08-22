# FAMtastic Connections and Concierge contract v1

## Purpose

This is the shared operating contract for Codex, Claude Code, Shay, Site
Studio, and future CLI agents that encounter a FAMtastic Designs lead or
customer conversation.

## Ownership

| Surface | Owns | Does not own |
| --- | --- | --- |
| FAMtastic Connections | Lead/status projection, communication event timeline, owner next action | Customer identity, price, payment, deployment |
| FAMtastic Concierge | Branded communication identity and channel inbox | Autonomous outreach, commercial decisions, customer/project truth |
| Drupal FAMtastic Pipeline | Prospect, Intake, customer/organization, Commerce, project, entitlement, operational ledger | Site Studio editing/runtime |
| Site Studio | Internal build, proof, QA, and delivery artifacts | Lead capture, customer communications, payment, domain purchase |
| Shay and other CLIs | Research, draft, coordination, implementation, and evidence | A parallel database, approval bypass, background authority |

## Required lifecycle

```text
Solution Finder or portal request
→ Drupal Prospect/Intake or Website Request
→ FAMtastic Connections timeline event
→ existing transactional acknowledgment and account continuation
→ human-reviewed Concierge draft/reply when appropriate
→ authenticated portal work and owner review
→ approved Site Studio packet/proof/delivery work
```

An Inkbox email or iMessage event may enrich the Connections timeline. It does
not create a new lead, change a customer’s account state, or advance a proof,
offer, payment, domain, or deployment stage by itself.

## Concierge webhook boundary

- Endpoint: `POST /api/pipeline/concierge/inkbox/webhook`.
- Required headers: `X-Inkbox-Request-ID`, `X-Inkbox-Timestamp`, and
  `X-Inkbox-Signature`.
- Signature input: `{request_id}.{timestamp}.{raw_body}` using HMAC-SHA256;
  reject events outside the five-minute replay window.
- Idempotency key: `inkbox:{event_id}`. Replays must not duplicate timeline
  entries.
- Stored facts: provider event/message/conversation identifiers, channel,
  direction, delivery state, hashed contact match, and matched Prospect ID.
- Not stored by this receiver: raw customer message body, secrets, API key,
  signing key, payment data, or browser/session data.

`INKBOX_CONCIERGE_API_KEY` is an identity-scoped backend credential used only
to configure or query the Concierge integration. The webhook receiver instead
uses `INKBOX_CONCIERGE_SIGNING_KEY`. Neither secret belongs in Git, shared CLI
configuration, prompts, tests, screenshots, or Site Studio build packets.

## Human approval gates

The following always require explicit human approval at action time:

- outgoing customer email, iMessage, SMS, or call;
- a quote, private offer, grant, recurring term, charge, refund, or checkout;
- domain registration, transfer, or renewal;
- proof delivery, public publication, or deployment.

## Current proof boundary

The receiver and signature verifier are locally tested. The canonical synthetic
customer proof was launch-blocked in this worktree because Drush could not
query the local database. Until a clean deployed certification exists, do not
describe Inkbox delivery, account continuity, or customer-visible Concierge
communication as production-proven.

## Site Studio handoff

Site Studio should consume only an approved Drupal build packet and report
build/proof/QA facts back on its existing packet boundary. It may display the
Connections correlation IDs and current human-approved next action, but it
must not send messages or infer approval from a Concierge event.
