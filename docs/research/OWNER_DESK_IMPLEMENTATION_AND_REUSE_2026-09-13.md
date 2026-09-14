# Owner Desk implementation and reuse

## Source ownership

The operating backend for Tighten Up Your Locs belongs to the FAMtastic Designs repository. The implementation lane is `codex/locs-owner-desk`, worktree `/private/tmp/famtastic-locs-owner-desk`, continuing the existing `f3467f46` appointment implementation. A bounded repository search found no separate Tighten Up Your Locs Git repository. The customer pilot lives under `website-delivery-swarm/pilots/shay-tighten-up-your-locs`; hosting provisioning is a separate FAMtastic Hosting concern, not a duplicate appointment backend.

## Delivered scope

The booking-first source provides owner-bound requests, appointments, calendar/day selection, openings, confirmations, proposed alternatives, rescheduling, cancellation/completion, and a token-scoped proposal response page. The review hardens retries, transaction/outbox behavior, expiry, time conversion, source privacy and private-page routing. Tests classify database-backed lifecycle checks separately from browser fixtures.

The reusable React presentation component is captured into Component Studio with an adapter boundary and byte/hash parity. Customer branding is supplied by the Designs adapter; generic source does not identify Shay or another business. Existing account/session and exact-site authorization remain in Drupal. No browser property grants ownership.

Site Studio's `/api/component-recipes` endpoint discovers the specification and preserves its readiness fields. It does not execute an owner application or authorize a customer build. Teaching, group seats, payments and LMS integration remain later phases; their research contracts are not enabled buttons or working features in this release.

## Owner entry after release

The implemented entry is `https://famtasticdesigns.com/portal?section=booking`. A signed-out visitor is sent to login and returned to that section after authentication. Only verified accounts with an authoritative site-owner binding receive site data. There is no separate password database and no newly issued credential in this change.

This URL describes the source route and intended release entry, not a claim that the new desk is live. No production migration or deployment was run.

## Review lessons

- Inspect existing branches before generating a replacement: the appointment implementation was already committed by the delivery session.
- An outbox insertion must share the appointment transaction; a saved booking followed by a failed queue insert is not a recoverable notification guarantee on its own.
- Retain idempotency keys across ambiguous retries, but retire them after a verified operation succeeds. Distinguish owner actor/site scope and reject a different payload with the same key.
- Appointment proposals are expiring holds, not permanent blockers. Cancelled/expired links cannot regain authority through a stale browser response.
- Private bearer pages require direct-host routing, no-index/no-referrer behavior and analytics exclusion. New links place tokens in a browser fragment and send them to the API in a header/body, not a query URL.
- A component extraction is useful only when the actual app consumes the captured bytes and its backend/brand dependencies are declared.

## Release gates

Review the exact source commit, run production-equivalent migration and persistent-lock contention tests, and verify the real owner/customer lifecycle under explicit deployment authority. In-flight provider delivery cannot necessarily be recalled after cancellation. Database fixtures and an in-memory mail capture do not establish real mailbox delivery or live concurrent reservations. Preserve these distinctions in capability records.
