# RECIPE: New Product X — idea → sellable → promoted → supported

**Outcome**: Product X is purchasable in Stripe + Drupal Commerce, contractually defined, fulfillable through a defined build path, visible to customers, promoted with assets, and supportable — or it is not for sale.
**Trigger**: "We should sell X."
**Owner**: CEO (fam-ceo), with CMO + Customer Service as step owners
**Rule**: A product missing ANY unchecked section below does not appear on the frontend. No orphan buttons selling vapor.

## Steps

| # | Step | Owner | Definition of done | Evidence required | Status |
|---|------|-------|--------------------|-------------------|--------|
| 1 | **Offer definition** | Fritz + CEO | Name, outcome promise, price ($199 / 55¢ ladder fit or new tier), what's explicitly NOT included | Written one-pager in `docs/products/X.md` | ☐ |
| 2 | **Contract & terms** | Fritz | Terms version covering delivery window, revisions, ownership, refunds; linked at checkout | Terms version hash recorded; checkout shows link | ☐ |
| 3 | **Stripe product + price** | CEO | Product/price created (test mode first), tax category correct | Stripe test-mode price ID in config | ☐ |
| 4 | **Drupal Commerce mapping** | CEO | Commerce product + variation wired to Stripe price; entitlement model defined (what access it grants) | Order→entitlement validator on synthetic purchase | ☐ |
| 5 | **Build/fulfillment path** | CEO + Studio owner | Decision recorded: Site Studio dispatch vs in-house vs manual-Fritz; storage location + retention for artifacts; queue/retry rules if automated | Path documented in `docs/products/X.md` §Fulfillment | ☐ |
| 6 | **Client admin surface** | Unifier + CEO | Customer can see, manage, and use X in their portal; admin can see + operate X in `/admin/famtastic`; same vocabulary as lead-to-launch | Screenshots from both surfaces + route list | ☐ |
| 7 | **Support playbook** | Cust. Service (vac.) | Common questions, failure modes, escalation path documented; owner alert rules set | Playbook section committed | ☐ |
| 8 | **Promotion kit** | CMO (vac.) | Landing page (or section), ≥1 blog post w/ real content, email announcement draft, social variants per channel w/ safe areas, UTMs defined | Assets in `marketing/campaigns/x/` + manifest | ☐ |
| 9 | **Analytics events** | CEO | GA4/view events fire for view, click, purchase of X; attributed to campaign | Event visible in debug view | ☐ |
| 10 | **Capability registry update** | CEO | `docs/CAPABILITY_REGISTRY.md` row added at honest evidence level | Registry diff | ☐ |
| 11 | **LAUNCH GATE** | **Fritz** | Fritz reviews steps 1–10 receipts and approves go-live | Approval note w/ date | GATE |
| 12 | **Publish & announce** | CMO | Frontend live, catalog synced, first campaign wave scheduled (approval-gated sends) | Production smoke check + schedule | ☐ |

## Failure paths

| Step | If it fails | Fallback |
|------|-------------|----------|
| 3–4 | Payment wiring mismatch | Do not proceed past step 4 until synthetic purchase round-trips; no manual workarounds |
| 5 | Fulfillment undefined | Product cannot launch — return to Fritz for scope cut or manual-fulfillment decision |
| 8 | Promotion assets thin | Delay launch, don't ship invisible products |

## Change log
- 2026-08-22 — Created. Encodes the owner's rule: Stripe presence alone ≠ a product.
- 2026-08-23 — Wave-1 trio (FAM-BUSINESS-EMAIL, FAM-MAINTENANCE, FAM-LOCAL-SEO): steps 1–10 COMPLETE. Step 3 correction: test price IDs already existed via stripe-sandbox-catalog.sh (.artifacts/stripe/sandbox-catalog.json); an earlier 'blocked' claim missed that artifact. Step 11 GATE PASSED 2026-08-23 — Fritz approved verbatim ("fully approved"). Step 12 executing: catalog already live in Commerce; frontend analytics deploy authorized; storefront content staging awaits prod config export (Fritz command prepared); announcement sends stay behind their own gates.
