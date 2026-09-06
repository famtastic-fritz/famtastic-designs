# Payment eligibility and provider-E2E scaffold

## Title

Commerce payment eligibility repair and provider evidence scaffold

## Purpose

Make every catalog price tell the truth about whether and how it may enter
Commerce, without turning a catalog listing into an unsupported purchase path.

## Goal

Expose one canonical, server-enforced payment contract for all 12 one-time and
four recurring products; reject unsupported checkout attempts honestly; and
define the fresh-runtime Stripe-sandbox evidence needed before any provider
claim is upgraded.

## Tasks

- [x] Add a payment mode, customer-safe message, and eligibility requirements
  to every published catalog product.
- [x] Enforce those modes at the account-owned Commerce checkout boundary.
- [x] Align the purchase and portal surfaces with the server contract.
- [x] Add a no-provider-call matrix and exact $1 private-offer fixture contract.
- [x] Run focused PHP, matrix, shell-guard, frontend build, and diff checks.

## Status

Completed locally; provider execution remains intentionally blocked.

## Started

2026-09-05

## Ended

2026-09-05

## Execution

Current `main` checkout; no feature branch or separate worktree was needed for
this contained repair.

## Research

The catalog, Customer Portal Commerce route, customer purchase UI, portal
service UI, grant service, product terms, and existing Stripe sandbox scripts
were inspected. The recurring implementation is not proven as a generic
checkout path, so all four recurring catalog entries are deliberately marked
as separate-authorization-only until their dedicated provider lifecycle passes.

## Review

No Stripe API call, test card, customer communication, production action, or
deployment occurred. The $1 private offer is a fresh-runtime-only fixture
contract: it is not an active grant, coupon, or customer record.

## Skills

`famtastic-verified-revenue-loop` and `stripe-best-practices` guided the
account scope, proof gate, immutable offer contract, Checkout evidence, and
provider-boundary requirements.

## Proof

- `backend/vendor/bin/phpunit -c backend/web/core/phpunit.xml.dist backend/web/modules/custom/famtastic_pipeline/tests/src/Unit` — 96 tests / 539 assertions passed (57 existing PHPUnit deprecations).
- `node scripts/validate-stripe-provider-e2e-matrix.mjs` — catalog-aligned 12 one-time / 4 recurring matrix passed with no Stripe call.
- `bash scripts/stripe-provider-e2e.sh` — scaffold-only guard passed; provider execution stayed disabled.
- `npm --prefix frontend run build` — production build passed (existing chunk-size warning remains).
- `git diff --check` — passed.
