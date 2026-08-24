# Brutal Review — Full Revenue Engine Audit
**Reviewer**: @fam-brutal-reviewer · **Date**: 2026-08-24 · **Trigger**: owner suspicion that agents bend design to fit Drupal instead of shaping Drupal around the plan

## Verdict
Not yet an autonomous revenue engine: an honestly-gated pipeline prototype with zero stranger revenue, a broken $499 tier in production, drafts where publishing should be, and validators that skip the money step.

## Critical gaps
1. Prod Commerce holds 14 of 16 advertised SKUs — FAM-BUSINESS-499 + FAM-HOST-BUSINESS-1999 missing → every multi-page business recommendation dies at checkout (`product_unavailable`) AFTER intake+proofs. Fix: seed variations + CI assertion catalog==DB.
2. No stranger has ever paid. e2e-autonomous-journey asserts checkout URL string then calls fulfill() directly — payment step untested by automation. Fix: gateway-mode test charge through /web/checkout/{id}.

## High
3. Publish gate has no executor: approvals exist, no bounded batch publisher consumes them; Postiz bound to localhost laptop.
4. Attribution dies at capture: UTMs never persisted on prospect capture; no post→lead→order join.
5. Renewals never charge autonomously: protection job sends reminder email only.

## Medium/Low
6. Split-brain payments: legacy famtastic_order+StripeGateway stack still routed beside Commerce.
7. Catalog JSON read from disk per request; hardcoded personal emails/URLs in code paths.
8. Double fulfillment on sponsored orders (idempotency-masked).
9. "300+ leads waiting" not in system (prod: 31 prospects).

## Drupal-fit verdict
Partially confirmed, sharper diagnosis: not node-cramming — **two-source-of-truth drift + parallel-stack accumulation**. Catalog truth in repo JSON vs sellability in DB broke the $499 tier. Counter-evidence where Drupal WAS shaped to plan: fail-closed mailer, HMAC inbound, proof-selection-before-checkout, checksummed deal snapshots.

## What is real
Lead capture w/ flood control; outbox accounting (sent=219 dead=0); L0 drafts fail closed; proof pipeline gates; server-authoritative checkout validation; entitlements w/ immutable snapshots; campaign send gates; honest rehearsal labeling.
