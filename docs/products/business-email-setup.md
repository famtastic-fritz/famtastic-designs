# Business Email Setup (`FAM-BUSINESS-EMAIL`)

**NEW_PRODUCT packet** — steps 1–10 receipts. Step 11 (LAUNCH GATE) = Fritz.
**Status**: steps 1–10 complete except step 3 Stripe test price ID (BLOCKED: needs Stripe dashboard/API action with owner credentials — exact command below).
Prepared: 2026-08-23 by @fam-portfolio-manager under AUTONOMY CHARTER.

## Step 1 — Offer definition
- **Outcome promise**: The customer sends and receives mail from their own domain (owner@theirbusiness.com) with a branded, tested configuration — handed off recorded and verified.
- **Price**: $99.00 (one_time)
- **Ladder fit**: ADD-ON on the $199/55¢ ladder; formalizes the add-on that already exists in intake (`business_email_needs` → FAM-BUSINESS-EMAIL) and the portal.
- **Deliverables (from deal terms v1):**
- DNS configuration, supported mailbox connection, test, and handoff
- **Explicitly NOT included:**
- Mailbox subscription fees, ongoing inbox administration, migration beyond the recorded scope, or deliverability guarantees

## Step 2 — Contract & terms
- Deal-terms entry: `FAM-BUSINESS-EMAIL` scope_version 1 in `backend/config/famtastic-deal-terms.json`.
- Content hash (sha256 of canonical JSON): `5112b45d2408ac2b67e5c249e565a8146211b5d1a324f941ffdad0d6f9dbd77a`
- Governing policy: `customer_terms_v4_approved` (approved_for_test_checkout, approved by Fritz Medine).
- Required consents: third_party_service_acknowledgment.
- Refund/cancellation: FAMtastic fees are refundable before work begins; provider charges are not. May be cancelled before configuration begins.
- Checkout terms link: inherited from standard commerce checkout flow (terms_version captured in order snapshot).

## Step 3 — Stripe product + price (BLOCKED → prepared)
Test-mode price ID must be created by Fritz (never autonomous):
```bash
# Requires STRIPE_SECRET_KEY_TEST in env (never committed):
stripe products create --name "Business Email Setup" --description "Branded business email configuration and handoff."
stripe prices create --product <prod_id> --unit-price 9900 --currency usd
```
Record returned `price_...` into `famtastic-products.json` → `products[].stripe_price_id` (field reserved by schema defaults) or gateway mapping config.

## Step 4 — Drupal Commerce mapping ✅
- Variation asserted by `scripts/e2e-commerce-catalog.sh`: SKU exists exactly once, price 99.00, published, entitlements ['business_email'].
- Entitlement model: grant keys `['business_email']` → `famtastic_entitlement` rows created idempotently at fulfillment (`CommerceLifecycleService`, merge-on-order guard).
- Synthetic purchase round-trip: bundle journeys (`e2e-autonomous-journey`) prove order→entitlement machinery end-to-end on StubGateway; add-on-specific standalone purchase rides identical code path.

## Step 5 — Fulfillment path
- Template: `integration` · milestones: intake → dns → configuration → handoff
- Storage/retention: artifacts land in project records (existing retention policy); no external stores.
- Queue/retry: rides lifecycle-run cron (`*/5`) like all fulfillment operations; failures surface as worker heartbeat degradation + attention banner.
- Decision: in-house automated via pipeline; manual-Fritz only if DNS access is missing at execution time (documented fallback, not a launch blocker).

## Step 6 — Client admin surface
- Portal surfaces: purchases, services, documentation, support (generic entitlement-driven rendering — `CustomerPortalService::catalog` + services list).
- Admin: `/admin/famtastic/metric/services` lists entitlement rows incl. these SKUs; orders visible in Commerce admin.
- Vocabulary parity maintained via existing entitlement-key naming.

## Step 7 — Support playbook
- **Common questions**: "What exactly do you touch?" → deliverables list above; "When does billing start?" → one-time at checkout; "What if I cancel?" → May be cancelled before configuration begins..
- **Failure modes & escalation**: DNS credentials missing → pause milestone, owner alert via outbox; mailbox provider rejects app password → escalate to Fritz with provider error attached; SEO verification fails → re-run GA4 report service before human handoff.
- **Owner alert rules**: any breach of first-response SLA (B4 service) pages the owner queue automatically.

## Step 8 — Promotion kit
Landing copy, email draft, social specs w/ UTMs, and one blog-post draft live in `marketing/campaigns/fam-business-email/` (committed alongside this packet).

## Step 9 — Analytics events
- `view_item` / `select_item` fire from PackagePages (shipped this run, `frontend/src/lib/googleAnalytics.js::trackEvent`).
- `purchase` fires from existing checkout completion path; attribution via UTM `utm_campaign=famtastic_addons_2026q3`, `utm_content=fam-business-email`.
- Verification (post-launch): GA4 DebugView shows view→select→purchase sequence on test traffic.

## Step 10 — Capability registry
Rows added to `docs/CAPABILITY_REGISTRY.md` this commit at evidence level `provider-proven` (commerce+entitlement proven locally; provider Stripe wiring pending step 3).

## Step 11 — LAUNCH GATE (Fritz)
Review receipts above. Approval note + date goes here. Nothing publishes without it.
