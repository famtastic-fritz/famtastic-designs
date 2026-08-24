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

## Remediation: Brutal Review 2026-08-24 (`docs/audits/BRUTAL-REVIEW-2026-08-24.md`)
| # | Finding (severity) | Fix step | Owner | Status |
|---|---|---|---|---|
| R1 | Prod Commerce missing 2 of 16 advertised SKUs — $499 tier unsellable after intake+proofs (CRITICAL) | Seed variations via `backend/scripts/setup-commerce.php` on prod + permanent catalog-drift guard in deploy script | fam-ceo → backend deploy is a GATE | Guard code fixed + locally verified 2026-08-24 (quote bug found in first draft, repaired, stub-run receipts clean; see heartbeat 2026-08-24T15:20Z). ⚠️ **Prod seeding BLOCKED on next approved backend deploy.** |
| R2 | No stranger has ever paid; e2e asserts checkout URL then calls fulfill() directly (CRITICAL) | Gateway-mode test charge through real `/web/checkout/{id}` in Stripe TEST mode **OR** owner-executed live purchase | fam-ceo → Fritz decides/executes | ◐ PATH CHOSEN 2026-08-24 (Fritz): owner-executed real-money proof via `FOUNDER-DOLLAR 2026Q3` promotion (`$198 off` ⇒ $1 Web Basics/Business orders) + `FAMFOUNDER` coupon, usage-limited 5/2, expires 2026-10-31. Prep committed: `backend/scripts/setup-founder-promotion.php` (idempotent, headless-total-verified pattern) + SITE_LEARNINGS gotchas (fbd3a995, 6de6d3f0). ⚠️ **Script run on prod = backend config action (GATE); the $1 checkout itself = Fritz executes personally.** Neither executed yet. TEST-mode ruling no longer required unless Fritz also wants sandbox coverage. |
| R3 | Publish approvals have no executor; Postiz bound to laptop (HIGH) | Bounded batch publisher consuming approval records | unassigned (dispatch hold) | ⚠️ not started |
| R4 | UTMs never persisted at prospect capture; no post→lead→order join (HIGH) | Persist UTM snapshot on prospect create + attribution join table | unassigned (dispatch hold) | ⚠️ not started |
| R5–R9 | Renewals email-only; split-brain payments; per-request JSON reads + hardcoded personal URLs; double fulfillment; lead-count myth (MED/LOW) | Backlog — schedule after R1–R4 | unassigned | ⚠️ backlog |

## Change log
- 2026-08-22 — Seeded from AUTONOMOUS_LEAD_TO_LAUNCH_PLAN slices; marked known holes (proof email disconnect, missing selection notifications) discovered in production use.
- 2026-08-23 — Step 1 partially advanced: per-lead owner workspace + needs-first-response queue shipped (`efbef789`, e2e PASS ×2 idempotent). Ack email now points customers at guided intake (`db13b6e8`). Step remains 🔄: dedup/suppression validator still outstanding.
- 2026-08-24 — Added Brutal Review remediation table (R1–R9). R1 drift-guard code fixed locally same day; prod seeding gated on backend deploy approval. R2 needs Fritz ruling on TEST-mode charges vs billing hard limit.
- 2026-08-24 (heartbeat) — R2 updated: Fritz chose the owner-executed $1 live-purchase path (FOUNDER-DOLLAR promotion kit, commits fbd3a995/6de6d3f0); ledgered here because the kit previously existed only in code + SITE_LEARNINGS with no recipe home. Gates unchanged: prod script run and the checkout itself both require Fritz.
- 2026-08-24 (admin-cx) — Step 4/5 flow redesign shipped (owner screenshot complaint): the portal projects intake now uses progressive disclosure — step 1 asks only request name + build type + goal, and a draft save reveals the full 60-field interview grouped into six labeled fieldsets with a sticky save bar. Every input name unchanged; backend contract untouched. New validator `scripts/e2e-portal-links.sh` (+ `frontend/e2e/portal-links.crawl.mjs`) crawls all reachable portal sections plus `/portal/:token`, asserts render/fake-affordance/synthetic-string/overflow/notice rules, seeds and cleans its own local test customer. Finding logged: eight dashboard sections (activity, performance, support, learn, faq, grow, referrals, settings) have no reachable affordance — nav change is an owner decision. Evidence: `.artifacts/admin-cx/2026-08-24/`.
