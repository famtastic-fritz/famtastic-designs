# Autonomous Lead-to-Launch Completion Plan

## Priority 0 — Public proof-to-payment recovery

Status: **active implementation plan** (August 22, 2026).

### Controlled P.I.T. evidence update — August 23, 2026

P.I.T. received one **manual compatibility** public package: exactly three
direct, view-only static proofs and one recorded FAMtastic transactional-outbox
send. The evidence is campaign
`pc-pros-in-training-aka-the-p-i-t-0c6c69ad2e639b44`, local Build ID
`pit-public-initial-three-20260823`, outbox key
`public-proof:30:22:initial-v1`, and the Drive delivery record. It proves that
FAMtastic can promote a reviewed `a/b/c` bundle into its artifact slots and
send it once through the outbox. It does **not** complete any unchecked
canonical-lifecycle gate below: it was not a signed/revocable preview delivery,
did not prove same-email claim/history, detailed refined-six generation,
revision candidate delivery, Stripe, or the autonomous runner.

The old Solution Finder path called SMTP directly, then marked a lead
`acknowledged`. That was not a delivery workflow: no durable message receipt,
claimable request, proof job, owner proof-send gate, or account continuation
was created. All new public leads and legacy recovery work use this order:

```text
public intake
→ durable public-preview delivery + owner alert outbox
→ exactly three proof directions (Safe / Medium FAMtastic / Ultra FAMtastic)
→ Build DNA + owner review
→ explicit owner approval of one frozen customer email
→ unlisted, view-only concept room
→ same-email verified account claim
→ detailed portal intake
→ six refinements (1 Normal / 1 Medium / 4 Ultra)
→ Build DNA + owner review + approved portal email
→ selection → versioned revision request if needed
→ package / terms / Stripe Checkout → paid-project fulfillment
```

Each transition is idempotent and must leave a Drupal record, filesystem Build
DNA evidence, and an Operations-visible state. SMTP acceptance is never
treated as inbox delivery or proof of customer completion. The unlisted public
room never exposes account data, selection, revision, pricing, payment, or
files. Customer emails, proof publication, offers, payment, and deployment
each remain a human approval at the action boundary.

P.I.T. recovery control: Prospect #30 / Intake #13 is the controlled first
canonical case. A manual legacy-compatible `a/b/c` delivery occurred on August
23 and must not be replayed or used as proof of the new path. Use
`drush famtastic:public-preview-prepare 30 --intake-id=13 --confirm=30` only
after the deployed migration and source SHA are verified. That command creates
no customer email; it only queues owner-visible proof work. The customer
invitation is separately staged and approved in Operations.

### Priority 0 implementation checklist — August 22, 2026

- [x] Replace public direct-SMTP lead acknowledgement with a durable preview
  delivery, owner-alert outbox record, and canonical proof job.
- [x] Bind every public, refined, and revision proof request to the sole
  `website_proof.generate.v1` routine, an immutable source correlation, a
  Build DNA preflight, and a declared direction/profile contract.
- [x] Keep the public `a`–`c` history immutable; create a distinct detailed
  `portal_refined_six.v1` campaign with fresh `a`–`f` proofs rather than
  appending directions to the public campaign.
- [x] Make proof selection and "Make changes" durable: a revision snapshots
  the selected baseline, queues one source-bound direction, and keeps the
  candidate owner-only until explicit approval.
- [x] Add fresh-sandbox acceptance coverage for public intake, owner gates,
  same-email exact claim, refined-six lineage, stale-callback rejection,
  selection, revision idempotency, checkout gates, and unavailable-provider
  failure. The fixture proves safety plumbing only, not a creative provider.
- [x] Verify a clean Drupal installation and migrations from the current
  source branch.
- [ ] Remove the retired Site Studio dispatch/callback path from the preview
  release lane. Site Studio is not this routine's provider: FAMtastic owns
  preview generation, artifact slots, proof rooms, owner review, email, and
  request/project truth. The inspected local-only Studio endpoint remains
  historical evidence only and cannot satisfy the public/refined/revision
  preview contract.
- [ ] Configure and prove a compatible FAMtastic-controlled preview runner,
  including actual provider/model receipts, complete Build DNA, browser QA,
  an independent visual-review decision, and FAMtastic-owned artifact storage.
  It must pass one production callback acceptance test without exposing or
  configuring Site Studio as a preview endpoint.
- [ ] Keep Site Studio private until a customer selects a direction. Its only
  later role is to consume the immutable selected-build packet and return a
  signed build-success packet; that handoff is not a prerequisite for a
  FAMtastic proof-room or proof-email release.
- [ ] Deploy the reviewed backend migration and source SHA through the
  production runbook, then browser-verify the live public and portal lanes.
- [ ] Run the controlled P.I.T. recovery job, review its real three-proof
  package, and explicitly approve its frozen customer invitation.
- [ ] Verify a real test inbox delivery, a Stripe test checkout/fulfillment
  handoff, a revision candidate owner approval, and the negative production
  authorization cases before calling the lifecycle production-proven.

## Objective

Raise FAMtastic Designs from a manually operated proof to a production-safe,
autonomous lead-to-launch business flow. GitHub issue
[#15](https://github.com/famtastic-fritz/famtastic-designs/issues/15) is the
acceptance gate and source of status truth.

## Agreed operating assumptions

- Initial scale is under 1,000 imported prospects per month.
- Outreach is email-first and requires no routine phone calls.
- Leads come from lawful public or licensed sources and retain provenance.
- A public prospect receives three owner-approved, view-only directions; after
  same-email account claim and detailed intake, the portal prepares six
  owner-approved refinements. The customer selects a direction, may request a
  versioned change, then selects an eligible package, accepts terms, and pays
  through hosted Stripe Checkout.
- The first year of hosting is included; recurring hosting starts in month 13.
- Customer sites initially share the FAMtastic hosting account with isolated
  deployment records and a custom domain pointing to the site.
- The customer owns or controls the domain; FAMtastic can manage it with
  authorization.
- Real outreach, live billing, purchases, paid cloud resources, DNS changes,
  and production deployment require explicit approval.

## Delivery slices

1. Reconcile the approved/deployed pipeline work into `main`; make Git and the
   Drupal database schema reproducible; add backend deploy and rollback.
2. Define canonical campaign, offer, consent, deployment, subscription, event,
   and exception models with lifecycle rules.
3. Add CSV/API lead ingestion, normalization, provenance, deduplication,
   suppression, and qualification.
4. Dispatch initial three- and portal six-direction proof jobs through the
   durable adapter/queue, receive callbacks safely, register Build DNA, and
   present signed view-only previews or authenticated portal proofs as
   appropriate.
5. Add transactional template/version tracking, recipient and share-hash
   snapshots, acceptance/bounce events, retries/dead letters, and exact owner
   send gates. Campaign outreach remains separate.
6. Make package selection authoritative; version terms; use Stripe test
   Checkout and signature-verified, idempotent webhooks.
7. Complete intake, assets, revisions, approval, and exception handling.
8. Add isolated customer build/deploy records, custom-domain and SSL checks,
   rollback, and launch verification.
9. Schedule month-13 hosting renewal with consent, dunning/cancellation, portal
   status, receipts, and optional upsells.
10. Add funnel analytics, security controls, automated tests, runbooks,
    staging proof, and a gated production release.

## Definition of done

The project is complete only when every checkbox in issue #15 has evidence.
Minimum evidence includes:

- clean install/update from the exact Git commit;
- lint, dependency audit, unit/integration tests, frontend build, and a
  deterministic end-to-end test;
- two-prospect isolation proof and negative authorization tests;
- duplicate webhook, callback, email, and deployment retry proof;
- $199 and $499 Stripe test transactions with the exact selected package;
- three distinct public proof artifacts, then six account refinements after
  detailed intake, selected and carried into fulfillment;
- lead source through revenue attribution;
- a customer deployment with domain/SSL/rollback verification in staging;
- portal and month-13 renewal schedule verification;
- operator exception queue and retry evidence;
- deployment and rollback runbooks usable by any authorized agent;
- no production or billable action taken without its approval record.

## Status protocol

For every delivery slice, record:

- acceptance criteria completed;
- commit SHA and branch;
- commands/tests run and their results;
- screenshots, logs, or API evidence where useful;
- unresolved risks and explicit approval gates;
- rollback procedure for any changed runtime surface.

Claims such as "deployed," "paid," "secure," or "complete" require evidence from
the target environment. A local build alone never proves production.
