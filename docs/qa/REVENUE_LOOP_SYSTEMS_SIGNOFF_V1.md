# Revenue-loop systems signoff v1

An independent reviewer—not an implementation-lane author—uses this protocol
before FAMtastic represents a revenue loop as ready.

## Claim under review

An attributed customer can complete the same production-shaped path from
discovery through authenticated research and proof selection, verified payment,
fulfilled project, mobile Owner Desk, and launch evidence. The reviewer issues
only one classification: `locally_proven`, `test_provider_proven`,
`production_smoke_tested`, or `launch_blocked`.

## Required evidence

- Public CTAs stay in the branded app at desktop and 390px mobile widths.
- Catalog, terms, comparison, intake recommendation, proof, checkout, receipt,
  fulfillment, and portal show the same offer-contract version/hash.
- Website checkout rejects missing request, missing selection, wrong owner,
  wrong package, stale contract, and unsupported SKU.
- Every proof-first request has immutable research and exactly three directions;
  any reset/edit allowance and selection are durable and account-scoped.
- Stripe TEST checkout proves the actual Commerce webhook path, including
  duplicate replay, decline, and 3DS recovery. A direct service call is not
  payment evidence.
- Customer-owned payment handoffs are valid, scoped, revocable, and labeled as
  a handoff; no view/open is counted as a payment or FAMtastic revenue.
- Freshness state exposes age, owner, blocker, next safe action, and release
  compatibility. A held campaign stays held and an overdue customer item creates
  one actionable owner record rather than automated outreach.
- Customer and cross-account tests cover portal, proof, Owner Desk, files,
  payment handoff, and booking controls.
- A paid conversion provisions an Owner Desk binding without publishing booking
  or payment behavior by itself.

## Required artifact bundle

`evidence.json`, assertion results, catalog contract snapshot, lifecycle record
chain, redacted Stripe TEST receipts, outbox/provider results, desktop/mobile
captures, analytics/redaction evidence, release compatibility receipt, rollback
evidence, exception list, and reviewer decision.

## Stop conditions

Return `launch_blocked` for route/admin leakage, public contract drift, a
payment shortcut, missing research/proof evidence, cross-account access,
unverified payment handoff, unknown worker/queue state, incompatible release
receipts, or any customer state stronger than its durable evidence.

## Commands

Run `scripts/revenue-loop-signoff.sh <repo>` for static validation. Set
`FAMTASTIC_REVENUE_SIGNOFF_FULL=1` to add the canonical fresh-runtime synthetic
journey. The journey wrapper creates its own temporary Drupal + SQLite runtime,
records a retained redacted evidence bundle, and deletes that runtime after the
run; it must never be repointed at production. Stripe TEST Checkout, production
smoke, live payments, customer sends, domains, and deployment remain separately
authorized.
