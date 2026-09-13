# Tighten Up Your Locs — unfinished Owner Desk delivery

## Status and correction

The public site is live. The dedicated owner application is now implemented and
locally proven, but no owner application changes are deployed. Request capture,
a shared portal inbox and workflow status updates remain distinct from the new
durable appointment lifecycle. Keep the analytics-consent/footer correction as
a separate narrow release.

Local source now includes Today/Openings/Requests owner views, durable
appointments and append-only events, site-scoped locks, idempotency, optimistic
revisions, overlap protection, owner confirmation/proposals/rescheduling/
cancellation/completion, and expiring token-scoped customer acceptance. Local
unit, lint, design-contract, build and 390/1280 browser checks pass. Production
schema update, exact release, live login, real owner data and real outbox
delivery are still required before this plan can be closed.

## Authoritative approval evidence

Read-only production Drupal inspection on September 13 confirmed request12,
campaign50, selected direction `b`, review `selected`, selected_at1788955567.
Variant104 is **Open Chair / Ruby Signal**. Its retained artifact is
`web/proofs/pc-tighten-up-your-locs-1d7c7b2e7a5a4480/b/index.html`, SHA256
`5209629c7a5db40627b9e98ac3c97b08ce771d637353128e3f9731f0c70d3c1d`.
The actual selected artifact says:

- “Shay sees the request in her Owner Desk.”
- “Shay confirms, offers another time, or closes it.”
- “The future Owner Desk gives Shay one place to review requests, keep
  availability honest, and decide what happens next.”

Its proof-stage disclaimer establishes that the preview did not execute live
calendar actions. It does not cancel the functionality presented for delivery.
Selection of the public direction alone does not prove approval of every pixel
of a separate owner page: retain its established structure and reconcile the
Ruby visual system instead of inventing a replacement design.

## Retained design and omission trace

`docs/design/proofs/tighten-up-your-locs-v2/owner/index.html` contains Today,
Openings, Requests and Grow. The availability composer captures date, start/end,
services and publication choice. `owner.js` can call configured availability
endpoints but only lists requests; no confirm/reschedule persistence exists in
that artifact. `backend-consolidation.md` explicitly requires an authenticated
phone desk and durable owner response/proposed-time records before notification.

Commit `48cc88ce` introduced the release recipe at
`website-delivery-swarm/pilots/shay-tighten-up-your-locs/releases/2026-09-12/site-recipe.json`.
It includes public-home only and explicitly withholds owner-desk because no
authenticated release was proven. The five-file static release therefore cannot
contain the owner application. BookingRequestService later supplied protected
request status changes and alerts, but explicitly no appointment/calendar action.

Root cause: the launch checklist narrowed to public infrastructure and request
inbox proof, then described that subset as complete. Excluding unsafe static
owner code was appropriate; leaving its promised replacement unfinished and
not keeping that delivery gate open was not.

## Alex Touch comparison

The retained V2 at
`frontend/public/showcase/booked-and-branded-pilot/alex-touch-prototype-v2/`
has Today, Requests, Services, Hours and Links/QR panels and a Confirm action.
Its manifest specifies same-device local storage; prototype.js reads/writes
localStorage and includes seeded demonstration requests. It provides interaction
reference, not a durable authenticated backend to deploy or transplant as-is.
Preserve both Alex versions and customer identity boundaries.

## Implementation continuation

1. Reuse existing customer11/org11/request12/project5/site binding and verified
   account/session controls. Add Shay's distinct branded owner experience using
   the retained Today/Openings/Requests/Grow structure. Resolve its owner entry
   and safe login return path; no public data or browser-only authorization.
2. Extend existing request/availability services with durable appointments,
   owner decisions, proposed alternatives, cancellations and an audit timeline.
   Store UTC instants plus explicit business timezone, service duration and
   request linkage. Enforce overlap protection atomically, revision checks,
   idempotency and legal state transitions; changing a label is not acceptance.
3. Implement an actual owner calendar/day agenda backed by those records.
   Publish/hide openings, confirm a request into a reserved appointment, propose
   and accept another time, reschedule and cancel. Keep an old reservation until
   a proposed replacement is accepted under the defined policy. Do not imply
   Google/Booksy synchronization; external integration is a separate connector.
4. Persist the decision before queuing its notification. Use scoped transactional
   outbox keys, delivery/error states and retries. Customer proposal acceptance
   must be scoped, expiring and replay-safe. No new charges or provider purchases.
5. Prove on mobile and desktop: owner login; publish opening; visitor request;
   owner confirm; durable calendar reservation; customer notice; alternative-time
   acceptance; reschedule/cancel; reload and second-device persistence. Include
   cross-account rejection, duplicate submission, concurrent slot contention,
   DST/timezone behavior, expired links, failed delivery and recovery.
6. Deploy clean exact source through existing release lanes with backups and
   rollback. Verify real owner URL and live end-to-end evidence. Notify Fritz
   and Shay only with the verified capabilities and any remaining limitations.

## Completion gate

The business operating experience stays incomplete until the authenticated desk,
calendar and acceptance/reschedule lifecycle are production-proven. Public launch
evidence remains valid for its narrow checks; do not erase it or relabel it as
proof of appointment management. Mailbox deliverability remains separately open.
