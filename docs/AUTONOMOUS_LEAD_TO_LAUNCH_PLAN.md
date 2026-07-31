# Autonomous Lead-to-Launch Completion Plan

## Objective

Raise FAMtastic Designs from a manually operated proof to a production-safe,
autonomous lead-to-launch business flow. GitHub issue
[#15](https://github.com/famtastic-fritz/famtastic-designs/issues/15) is the
acceptance gate and source of status truth.

## Agreed operating assumptions

- Initial scale is under 1,000 imported prospects per month.
- Outreach is email-first and requires no routine phone calls.
- Leads come from lawful public or licensed sources and retain provenance.
- A prospect receives three design proofs, selects one, selects either the $199
  or $499 package, accepts terms, and pays through hosted Stripe Checkout.
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
4. Dispatch three Site Studio proof jobs through a durable adapter/queue,
   receive callbacks safely, and present token-scoped previews.
5. Add compliant campaign email, template/version tracking, link attribution,
   unsubscribe, bounce/complaint handling, and retry rules.
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
- three distinct proof artifacts selected and carried into fulfillment;
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
