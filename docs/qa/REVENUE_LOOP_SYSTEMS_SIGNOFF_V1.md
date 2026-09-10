# Revenue-loop systems signoff v1

An independent reviewer—not an implementation-lane author—uses this protocol
before FAMtastic represents a revenue loop as ready.

## Claim under review

An attributed customer can complete the same production-shaped path from
discovery through authenticated research and proof selection, immutable
staging handoff, packet-bound staging receipt, customer staging acceptance,
verified payment, fulfilled project, mobile Owner Desk, and launch evidence. The reviewer issues
only one classification: `locally_proven`, `test_provider_proven`,
`production_smoke_tested`, or `launch_blocked`.

## Required evidence

- Public CTAs stay in the branded app at desktop and 390px mobile widths.
- Catalog, terms, comparison, intake recommendation, proof, checkout, receipt,
  fulfillment, and portal show the same offer-contract version/hash.
- Website checkout rejects missing request, missing selection, missing or
  mismatched staging receipt, missing customer staging acceptance, wrong owner,
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
- Staging receipt replay is idempotent; wrong packet, request/project, selected
  artifact, or manifest identity is rejected before customer review. Staging
  deployment never counts as customer acceptance.
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

Protected staging is also `launch_blocked` if any native or custom checkout,
simulation, webhook, mail, cron, provider, DNS, hosting, or deployment path can
produce an external effect; if the active payment gateway is not disabled; if
mail capture contains recipient, subject, or body content instead of digests;
or if the host cannot prove the exact pushed SHA, isolated data boundary, TLS,
access control, and rollback receipt.

## Commands

Run `scripts/revenue-loop-signoff.sh <repo>` for static validation. Set
`FAMTASTIC_REVENUE_SIGNOFF_FULL=1` to add the canonical fresh-runtime synthetic
journey. The journey wrapper creates its own temporary Drupal + SQLite runtime,
records a retained redacted evidence bundle, and deletes that runtime after the
run; it must never be repointed at production. Stripe TEST Checkout, production
smoke, live payments, customer sends, domains, and deployment remain separately
authorized.

## Protected-staging checkpoint — 2026-09-10

The dedicated staging host, database, files, private storage, and secrets were
kept separate from production, and production remained untouched. Hosted work
exposed SSH resets, the 250,000-inode cap, insufficient 128 MB PHP memory,
Apache auth-file traversal, CSP script/style/font blocks, direct SPA-route
fallback, and a missing clean-install `commerce_checkout` dependency. The
current release repair addresses those conditions with SHA-256-verified 512 KiB
chunks, inode headroom and archive-before-delete handling, 512 MB stage-only
PHP, a traversable bcrypt auth-file path, exact observed CSP sources, an SPA
fallback, and explicit Commerce Checkout verification.

The sanitized customer API and authenticated browser run passed login,
Operations Home, and Portal at 390, 768, and 1280 pixels. This signoff remains
`launch_blocked`: the exact current source has not yet completed its final
redeploy, hosted payment/mail/cron/provider safety assertions, and rollback
rehearsal. Do not promote the capability registry from this intermediate
browser result.
