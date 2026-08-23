# RECIPE: Lead → Launch → Retain

**Outcome**: A stranger becomes a paying customer with a launched website, then a retained, growing account — without Fritz touching the middle.
**Trigger**: Lead captured (public intake, `/start`, campaign landing page, or imported prospect).
**Owner**: CEO (fam-ceo)
**Last verified**: 2026-08-22 (seeded from `docs/AUTONOMOUS_LEAD_TO_LAUNCH_PLAN.md`; statuses are claims until evidence attached)

## Steps

| # | Step | Owner | Definition of done | Evidence required | Status |
|---|------|-------|--------------------|-------------------|--------|
| 1 | Lead capture & qualification | CMO (vac.) / public forms | Prospect record created with provenance; dedup + suppression checked | Validator run on synthetic lead | 🔄 |
| 2 | First contact & nurture email | CMO (vac.) | Acknowledgment sent; owner alerted; consent + unsubscribe honored | Send log + list-unsub header check | ⚠️ **GATED: no real outreach without Fritz approval** |
| 3 | Offer presentation ($199 / 55¢) | System | Correct pricing shown per account scope; package selection is authoritative | Pricing surface test vs Stripe/Commerce catalog | ✅ DONE 2026-08-23 — parity audit: 17 surfaces, **0 P0 / 1 P1 (fixed: ContactPage copy now matches tier ladder)** / 3 P2 logged as backlog. Package-selection authority confirmed server-side (no client amounts; SKU whitelist + recommendation match + terms gates reject arbitrary POSTs 422). Receipt: `RUNBOOKS/AUDIT-pricing-parity-2026-08-23.md`. |
| 4 | Checkout (Stripe hosted) | System | Order paid, webhook verified idempotently, entitlement granted | Webhook receipt + order record | ✅ test-proven |
| 5 | Intake brief completed | Customer + portal | All required intake fields saved to website request | Acceptance script on intake payload | ✅ locally proven |
| 6 | Proof generation (3 directions) | Site Studio dispatch OR in-house build | Three proofs stored and retrievable, account-scoped | Storage/retrieval validator | 🔄 in-house path only |
| 7 | **Proof-ready notification to customer** | Mail pipeline | Customer receives proof-ready email; delivery confirmed; failure retries + owner alert | Delivery log visible in `/admin/famtastic` for synthetic event | ✅ RESOLVED 2026-08-23 — production transport verified healthy (A1 receipts: watchdog acceptances, outbox sent=184/dead_letter=0); visibility shipped via notifications metric + attention banner (`277bb5e8`). Original 2026-08-22 incident premise was stale — actual defect was cron (see A2). Historical cause of the one lost email remains unexplained; flagged for SITE_LEARNINGS if it recurs. |
| 8 | Customer picks template / selects proof | Portal | Selection recorded; state machine advances; **owner notified instantly**; client gets confirmation of what changed | Event log + two notifications for synthetic pick | ✅ DONE 2026-08-23 — `decideWebsiteRequestProof` enqueues owner alert + Concierge customer ack on select and revision through existing outbox merge. Synthetic receipts: `.artifacts/mail-visibility/` (4/4 delivered, memory transport). Receipt: `scripts/e2e-mail-visibility.sh`. |
| 9 | Revision requests & re-proof loop | Portal ↔ Studio | Revision tracked as new proof version; prior version preserved | Version history in storage | 🔄 partially built |
| 10 | Launch approval gate | **Fritz** | Owner approves exact deploy artifact + DNS plan; rollback ready | Approval note + artifact SHA | GATE |
| 11 | Build & deploy customer site | Deploy pipeline | Site live on custom domain w/ SSL; isolated deployment record | Post-deploy checks green | 🔄 locally proven |
| 12 | Go-live confirmation to customer | Mail pipeline | Customer told site is live; hosting terms restated | Send log | ⚠️ inherits SO-1 mail fixes |
| 13 | Retention: add-on offers, check-ins, referrals | CMO (vac.) + portal | Add-on catalog surfaced post-launch; referral credits work | Entitlement + referral validator | ⚠️ not started |

## Failure paths

| Step | If it fails | Fallback |
|------|-------------|----------|
| 2,7,12 | Email send fails or unconfirmed | Retry ×3 with backoff → owner alert in admin + fallback channel; never silently drop |
| 4 | Webhook missed/duplicated | Idempotent replay from Stripe event log; reconcile job |
| 6 | Studio dispatch outage | Queue persists; salvage census path (see monorepo doctrine); in-house build fallback with owner flag |
| 11 | Deploy fails post-approval | Rollback to previous artifact automatically; owner paged |

## Approval gates
Steps 2, 10 (+ any real billing/DNS/production action). CEO prepares change + rollback; Fritz approves.

## Change log
- 2026-08-22 — Seeded from AUTONOMOUS_LEAD_TO_LAUNCH_PLAN slices; marked known holes (proof email disconnect, missing selection notifications) discovered in production use.
- 2026-08-23 — Step 1 partially advanced: per-lead owner workspace + needs-first-response queue shipped (`efbef789`, e2e PASS ×2 idempotent). Ack email now points customers at guided intake (`db13b6e8`). Step remains 🔄: dedup/suppression validator still outstanding.
