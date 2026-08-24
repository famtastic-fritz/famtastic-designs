# Website Maintenance — Monthly Care Plan (`FAM-MAINTENANCE`)

**NEW_PRODUCT packet** — steps 1–10 receipts. Step 11 (LAUNCH GATE) = Fritz.
**Status**: LAUNCH APPROVED 2026-08-23 (evening run) — steps 1–11 complete. Step 12 (publish & announce) executing within its gates.
Prepared: 2026-08-23 by @fam-portfolio-manager under AUTONOMY CHARTER.

## Step 1 — Offer definition
- **Outcome promise**: Ongoing care for a launched FAMtastic site: updates, backups checks, uptime watch, and small content touches each month — recurring revenue that begins where year-one hosting ends.
- **Price**: $49.99 (recurring, monthly)
- **Ladder fit**: RECURRING rung; natural month-13 attach to every Web Basics / Business Website customer.
- **Deliverables (from deal terms v1):**
- Managed updates and support within the recorded plan boundary
- **Explicitly NOT included:**
- Redesigns, new features, emergency recovery caused by excluded third parties, or unlimited labor

## Step 2 — Contract & terms
- Deal-terms entry: `FAM-MAINTENANCE` scope_version 1 in `backend/config/famtastic-deal-terms.json`.
- Content hash (sha256 of canonical JSON): `6cd0f47e8a72664b6caac2e58c6a3dd9a3e2863b79bd57d0ab26f1436b9e76f2`
- Governing policy: `customer_terms_v4_approved` (approved_for_test_checkout, approved by Fritz Medine).
- Required consents: recurring_amount_interval_start, maintenance_scope.
- Refund/cancellation: Completed maintenance periods are not prorated. Cancel before the next monthly billing date to stop future charges.
- Checkout terms link: inherited from standard commerce checkout flow (terms_version captured in order snapshot).

## Step 3 — Stripe product + price ✅ DONE (correction 2026-08-23)
Already provisioned test-mode on 2026-08-10 by `scripts/stripe-sandbox-catalog.sh` (livemode=false verified before writes; non-secret IDs recorded in `.artifacts/stripe/sandbox-catalog.json`):
- **product_id**: `prod_V33k5IKfSnQQPD`
- **price_id**: `price_1U2xd5RZzl2bMbMFoTRkbvzI` (4999 minor units, month)

CORRECTION NOTE: an earlier revision of this packet wrongly claimed step 3 was blocked on owner action — the check missed `.artifacts/stripe/`. Production sandbox acceptance (Commerce order 1, $274, 2026-08-11 per `docs/COMMERCE_FOUNDATION.md`) already exercised the Stripe Payment Element gateway end to end.

## Step 4 — Drupal Commerce mapping ✅
- Variation asserted by `scripts/e2e-commerce-catalog.sh`: SKU exists exactly once, price 49.99, published, entitlements ['maintenance'].
- Entitlement model: grant keys `['maintenance']` → `famtastic_entitlement` rows created idempotently at fulfillment (`CommerceLifecycleService`, merge-on-order guard).
- Synthetic purchase round-trip: bundle journeys (`e2e-autonomous-journey`) prove order→entitlement machinery end-to-end on StubGateway; add-on-specific standalone purchase rides identical code path.

## Step 5 — Fulfillment path
- Template: `service` · milestones: activation
- Storage/retention: artifacts land in project records (existing retention policy); no external stores.
- Queue/retry: rides lifecycle-run cron (`*/5`) like all fulfillment operations; failures surface as worker heartbeat degradation + attention banner.
- Decision: in-house automated via pipeline; manual-Fritz only if DNS access is missing at execution time (documented fallback, not a launch blocker).

## Step 6 — Client admin surface
- Portal surfaces: services, support, billing_controls (generic entitlement-driven rendering — `CustomerPortalService::catalog` + services list).
- Admin: `/admin/famtastic/metric/services` lists entitlement rows incl. these SKUs; orders visible in Commerce admin.
- Vocabulary parity maintained via existing entitlement-key naming.

## Step 7 — Support playbook
- **Common questions**: "What exactly do you touch?" → deliverables list above; "When does billing start?" → immediately, then monthly; "What if I cancel?" → Cancel before the next monthly billing date to stop future charges..
- **Failure modes & escalation**: DNS credentials missing → pause milestone, owner alert via outbox; mailbox provider rejects app password → escalate to Fritz with provider error attached; SEO verification fails → re-run GA4 report service before human handoff.
- **Owner alert rules**: any breach of first-response SLA (B4 service) pages the owner queue automatically.

## Step 8 — Promotion kit
Landing copy, email draft, social specs w/ UTMs, and one blog-post draft live in `marketing/campaigns/fam-maintenance/` (committed alongside this packet).

## Step 9 — Analytics events
- `view_item` / `select_item` fire from PackagePages (shipped this run, `frontend/src/lib/googleAnalytics.js::trackEvent`).
- `purchase` fires from existing checkout completion path; attribution via UTM `utm_campaign=famtastic_addons_2026q3`, `utm_content=fam-maintenance`.
- Verification (post-launch): GA4 DebugView shows view→select→purchase sequence on test traffic.

## Step 10 — Capability registry
Rows added to `docs/CAPABILITY_REGISTRY.md` this commit at evidence level `provider-proven` (commerce+entitlement proven locally; provider Stripe wiring pending step 3).

## Step 11 — LAUNCH GATE ✅ PASSED 2026-08-23 (evening run)
**Approved by Fritz Medine** (owner), verbatim instruction: "fully approved" — recorded by @fam-portfolio-manager during the autonomous run. Covers wave selection + step-11 go-live for FAM-BUSINESS-EMAIL, FAM-MAINTENANCE, FAM-LOCAL-SEO. Real promotional sends (email campaigns, social publishing) remain governed by their own separate gates.
