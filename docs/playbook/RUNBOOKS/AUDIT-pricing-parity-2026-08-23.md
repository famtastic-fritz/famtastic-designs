# AUDIT — Pricing Parity (LEAD_TO_LAUNCH Step 3)

- Date: 2026-08-23
- Mode: READ-ONLY audit. No code changes, no commits.
- Recipe DoD: "Offer presentation ($199 / 55¢) — Correct pricing shown per account scope; package selection is authoritative — Evidence: Pricing surface test vs Stripe/Commerce catalog."
- Canonical truth: `backend/config/famtastic-products.json` (schema `famtastic.product-pipeline.v1`) and `backend/config/famtastic-deal-terms.json` (`customer_terms_v4_approved`, approved 2026-08-10).
- Contract basis: `docs/AGENT_OPERATING_CONTRACT.md` §"Intake to purchase decision" (FAM-FOOT-199 one-page / FAM-BUSINESS-499 ≤5 pages / >5 pages + ecommerce/custom → staff scope review).

## Verdict

**Surfaces audited 17 / mismatches 1 P1 (+0 P0, 3 P2, 2 P3).**

No P0: no surface displays a chargeable price that is absent from the canonical catalog.
One P1 terms mismatch: ContactPage describes the $199 offer with non-canonical plan terms.

## Canonical reference (from the two JSON files)

| SKU | Canonical price | Notes |
|---|---|---|
| FAM-FOOT-199 | $199.00 one-time | 12 mo basic hosting incl.; renewal FAM-HOST-999 |
| FAM-BUSINESS-499 | $499.00 one-time | ≤5 pages, 2 revision rounds; renewal FAM-HOST-BUSINESS-1999 |
| FAM-HOST-999 | $9.99/mo after year 1 | separate authorization required |
| FAM-HOST-BUSINESS-1999 | $19.99/mo after year 1 | separate authorization required |
| FAM-REVISION-75 | $75.00 | one consolidated revision round |
| FAM-PAGE-EXTRA / FAM-SCHEDULING / FAM-ECOMMERCE-DISCOVERY | $149.00 each | one-time |
| FAM-COPY | $199.00 | add-on (distinct from FAM-FOOT-199) |
| FAM-BRAND | $249.00 | one-time |
| FAM-LEAD-AUTOMATION / FAM-LOCAL-SEO | $299.00 each | one-time |
| FAM-AI-AGENT | $499.00 | add-on (distinct from FAM-BUSINESS-499) |
| FAM-ANALYTICS | $29.99/mo | recurring at activation |
| FAM-MAINTENANCE | $49.99/mo | recurring at activation |
| FAM-BUSINESS-EMAIL | $99.00 | one-time |

55¢/day = derived marketing framing of $199 ÷ 365; every use found is qualified ("about … when averaged across one year", "one-time purchase, not daily billing"). Consistent.

## Findings table

| # | Severity | Surface | file:line | Exact text found | Canonical comparison | Result |
|---|---|---|---|---|---|---|
| 1 | PASS | SEO meta | frontend/src/seo.js:26–28 | `$199 Website \| About 55 Cents a Day…` / `…for $199—about 55 cents a day across one year—…` | $199 ✓, derivation disclosed | Match |
| 2 | PASS | Home CTA | frontend/src/pages/HomePage.jsx:146–148 | `A professional website for about 55 cents a day.` / `Explore the $199 website` | ✓ | Match |
| 3 | PASS | Packages hub CTA | frontend/src/pages/PackagesHubPage.jsx:38,66–68 | `See the $199 website offer` / `Start at $199.` | ✓ | Match |
| 4 | PASS | Campaign landing page | frontend/src/pages/FiftyFiveCentWebsitePage.jsx:9,31,34,36,39,57,83,86 | `$199 Web Basics offer`; `renews at $9.99 per month unless canceled`; `$199 is a one-time purchase, not daily billing` | All three numbers match canonical; renewal + domain-renewal separation disclosed | Match |
| 5 | PASS | Blog series chrome | frontend/src/pages/BlogPostPage.jsx:16,19 | `$199 divided across 365 days is approximately 55 cents per day; checkout still charges one $199 payment.` | Explicit one-time disclosure | Match |
| 6 | **P1** | Contact page "What happens next" | frontend/src/pages/ContactPage.jsx:63 | `Every engagement starts with a $199 verified discovery build.` | Price $199 matches FAM-FOOT-199, but **plan terms are wrong**: canonical promise is "one custom, focused single-page or landing-page website," not a "discovery build"; and "every engagement" contradicts FAM-BUSINESS-499 and the staff-scope-review tier (>5 pages/ecommerce → review, contract lines 28–31). Same page line 62 promises "scoped, fixed-price plan" for all replies. | **MISMATCH (wrong plan terms)** |
| 7 | P2 | Solution Finder estimates | frontend/src/components/SolutionFinder.jsx:126,404–408,430–463,477–481,895 | `{ value: '199', label: '$199 starter' }`; estimate ladder `$199 / $499 / $1,999 / $3,999 / $6,999`; app/portal ranges up to `$15,000` rendered as `Your custom estimate` | Website branch [199, 499] matches canonical. Higher ranges ($1,999+) are NOT purchasable SKUs — acceptable only as staff-scope-review estimates, but only the `custom` branch sets `review: true` (line 465); the website 5–10-page branch ($1,999–$3,999) renders without a staff-review caveat even though the contract routes it to staff scope review. Not chargeable through this form (lead capture only), so not P0/P1. | Advisory mismatch (labeling gap) |
| 8 | P2 | Proof Hub package cards | frontend/src/pages/ProofHub.jsx:32–57 (`price: '$199'`, `price: '$499'`) and card features | `Essential Launch … $199`; `Business Launch … $499` | Amounts match canonical SKUs. Feature copy drifts from deal terms: Essential omits hosting/domain inclusion shown in consent text; Business says "Analytics dashboard" vs canonical entitlement `analytics_connection`. Consent block itself (lines 419–421: `$199 bundle includes 12 months … $9.99/month`) is correct. Hardcoded, not catalog-driven. | Copy drift, amounts correct |
| 9 | P2 | Package detail pages | frontend/src/pages/PackagePage.jsx:23,69–72,105 | Prices render from Drupal `package_page` nodes via `getNodesRaw('package_page')` (`{plan.price}`, `{addon.price}`) | CMS-sourced; static audit cannot verify runtime node values equal the canonical JSON. No hardcoded amounts found in code. | Verification gap — needs runtime cross-check |
| 10 | PASS | Checkout display | frontend/src/pages/PurchasePage.jsx:29–34,48,50,52,57 | Prices from `getCustomerCatalog()`; private offer shows `offered_amount_minor` vs preserved `list_amount_minor` | Catalog-driven = server-authoritative by construction | Match |
| 11 | PASS (P3 note) | Checkout renewal consent | frontend/src/pages/PurchasePage.jsx:45,54 | `const renewalPrice = baseSku === 'FAM-BUSINESS-499' ? '$19.99' : '$9.99';` | Matches FAM-HOST-BUSINESS-1999 / FAM-HOST-999 today, but hardcoded — will silently drift if catalog changes | Match now / drift risk |
| 12 | PASS (P3 note) | Prospect consent copy | frontend/src/pages/ProspectLandingPage.jsx:241–243 (offer amount at 225/232 is server data via `formatPrice(offer.amount…)`) | `The $199 bundle includes 12 months of basic hosting… Hosting at $9.99/month…` | Numbers match canonical; hardcoded in consent string | Match now / drift risk |
| 13 | PASS | Proof status page | frontend/src/pages/ProofStatusPage.jsx:171,182,226,236 | `formatPrice(7500, 'usd')` (revision); subscription/hosting amounts from API payload | $75 = FAM-REVISION-75 ✓; recurring amounts server-supplied | Match |
| 14 | PASS | Portal module promos | frontend/src/pages/CustomerPortalDashboard.jsx:11,105,111 | `${item.price}` rendered from customer catalog for promoted SKUs | Catalog-driven | Match |
| 15 | PASS | Backend terms/e-mail bodies | backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.install:356,363,698,712 | `one-time $199 purchase…`; `$9.99 per month after the included year` | Terms drafts v2/v4 match deal-terms JSON (promise, renewal, cancellation, refund) | Match |
| 16 | PASS | Backend staff surfaces | src/Form/LaunchApprovalForm.php:46–47; src/Form/WebsiteRequestOfferForm.php:33–36,45–52; famtastic_pipeline.module:74; info.yml:3 | `I approve the $199 Web Basics Bundle promise.`; `$prices = ['FAM-FOOT-199' => 19900, 'FAM-BUSINESS-499' => 49900]`; `pay ($199 test)` | Staff price map equals canonical list prices; private offered price is staff-entered with list price snapshotted (contract §5–6 compliant) | Match |
| 17 | PASS | Marketing campaigns | marketing/campaigns/55-cents-17-day/manifest.json:16,45,74,103 | `"promise": "What 55 cents a day means"` | Framing only; no dollar figures or conflicting claims in campaign content | Match |

Also verified negative: `src/Service/OutreachMailer.php` contains no price strings (no e-mail template states an amount outside the terms texts above).

## Task 4 — "Package selection is authoritative": CONFIRMED

The browser cannot post a price. Chain of evidence:

1. Client payload contains **no amount field** — PurchasePage.jsx:39 sends only `{organization, website_request, skus[], domain_choice, recurring_authorized, accept_terms, terms_version, marketing_opt_in, grant_code}`.
2. Server endpoint `commerceCheckout()` — backend/web/modules/custom/famtastic_pipeline/src/Controller/CustomerPortalController.php:196:
   - Authentication + verification gate: lines 197–199.
   - Organization ownership (`hash_equals` against customer's orgs): lines 201–207; website-request ownership + status: 211–218.
   - Recommendation/private-offer binding and SKU whitelist to `['FAM-FOOT-199','FAM-BUSINESS-499']`: lines 220–227; proof-selection gate: 231–233; one-order-per-request: 228–230.
   - Every submitted SKU must exist and be **published** in canonical definitions: lines 238–241 (`if (empty($definitions[$sku]['published'])) return … product_unavailable`).
   - Checkout must include the recommended package: lines 242–244; max one website bundle per cart: 245–246; domain choice + month-13 renewal authorization enforced: 247–254; exact canonical `terms_version` required: 255–258.
   - **Amounts recomputed server-side from Drupal Commerce variations keyed by SKU**: lines 264–276 (`$amount = (int) round((float) $variation->getPrice()->getNumber() * 100)` at 271). A private-offer amount is read only from the DB row scoped to this customer + request + expiry: 221–224 and 272–274; order item unit price overridden only there or by server-computed grant discount: 300–306.
   - Grant codes quoted server-side against those server amounts with account/request/package scoping: 277–291.
3. Stripe hand-off uses the persisted order amount, or a pre-created **package-specific** Price ID so "a $199 price can never silently replace a $499 server-authoritative order amount" — src/Service/StripeGateway.php:62 and 77–84.

Arbitrary price/SKU POST result: unknown SKU → 422 `product_unavailable`; unpublished/unmatched package → 422; wrong bundle count → 422 `invalid_cart`; missing consents/terms → 422. No client-controlled amount exists anywhere in the path.

## Recommended fix order

1. **P1 — ContactPage.jsx:63**: replace "Every engagement starts with a $199 verified discovery build." with canonical Web Basics Bundle promise (single-page/landing-page website, $199, one-time) and stop implying $199 applies to all engagements; keep the fixed-price-plan sentence scoped to in-scope work.
2. **P2 — SolutionFinder.jsx**: set `review: true` (or an explicit "scoped by our team — not a self-serve package" note) for any estimate above the canonical ladder (website 5+ pages, app, portal branches), aligning with contract's staff-scope-review routing.
3. **P2 — ProofHub.jsx PACKAGES**: source card prices/features from the customer catalog (or annotate the constants as legacy proof-campaign copy) so feature lists mirror `famtastic-deal-terms.json`.
4. **P2 — PackagePage runtime parity**: add a scripted check comparing published Drupal `package_page` price fields against `famtastic-products.json` before deploy preflight.
5. **P3 — de-hardcode renewal strings**: PurchasePage.jsx:45 and ProspectLandingPage.jsx:243 should derive `$9.99/$19.99` from catalog renewal SKUs (`FAM-HOST-999`, `FAM-HOST-BUSINESS-1999`) to prevent silent drift.
