# FAMtastic client delivery, brand, and fulfillment standard

**Version:** 0.1 review draft  
**Date:** October 2, 2026  
**Scope:** FAMtastic websites, applications, Connect Cards, client portals, creator credits, digital deliverables, commerce, and owner handoff  
**Status:** internal operational standard; not customer terms and not attorney advice

This standard joins requirements that were previously spread across offer records, terms drafts, creator-credit documents, brand guidance, project playbooks, and client-specific handoffs. It does not silently change an accepted customer agreement. Contract language, privacy disclosures, refund rules, intellectual-property provisions, accessibility duties, tax treatment, and jurisdiction-specific requirements still need qualified review before production use.

## 1. Four contracts every client project must carry

Every project must have four separately reviewable records:

1. **Service-delivery contract:** what FAMtastic will produce, how many rounds are included, what is excluded, who supplies each input, and how the client accepts the work.
2. **Ownership and permissions contract:** who owns supplied material, bespoke work, reusable FAMtastic systems, third-party materials, customer data, domains, provider accounts, and public case-study rights.
3. **Commerce and fulfillment contract:** who is merchant of record, what is sold, how payment is verified, how the item or service is fulfilled, who supports it, and how refunds/exceptions reconcile.
4. **Brand and attribution contract:** how the customer brand, FAMtastic identity, creator credit, Connect Card, and approved logos/assets may be used.

Payment, deployment, acceptance, and fulfillment are separate states. Never use one as proof of another.

## 2. Required delivery record

At proposal or invoice creation, preserve an immutable scope snapshot containing:

- customer, project, offer/SKU, scope version, price, credits, taxes, third-party costs, and renewal terms;
- promised deliverables, included services, exclusions, client inputs, revision allowance, and change-order rule;
- milestone owners, target dates or dependencies, acceptance criteria, and evidence location;
- exact staging release or proof under review;
- enabled and disabled providers, environments, and truthful capability limits;
- support response target, maintenance boundary, warranty/correction period, and exit route;
- terms version, checksum, acceptance identity, timestamp, and applicable consent evidence.

Use these delivery states:

| State | Meaning |
|---|---|
| Prepared | A reviewable artifact exists locally or in a controlled proof space |
| Presented | The client can access the exact artifact and release under review |
| Accepted | The authorized client approved that exact artifact and stated scope |
| Paid | Verified financial-system evidence matches the invoice/order, amount, currency, and merchant |
| Delivered | The accepted artifacts, access, training, export, and receipt were supplied |
| Launched | The approved production destination passed release verification |
| Fulfilled | The purchased client service or end-customer order reached its defined completion state |

The final delivery receipt identifies the accepted release, artifacts, credentials-delivery method, owner access, enabled providers, known exclusions, backup/export location, and rollback or recovery route.

## 3. Ownership, copyright, and asset permissions

### Baseline

- The client retains ownership of material, identity, business records, domain, and provider accounts they own or control.
- FAMtastic must not use client material outside the recorded project purpose without an additional permission basis.
- FAMtastic retains its pre-existing components, recipes, methods, automation, templates, know-how, and infrastructure unless a signed agreement says otherwise.
- Open-source, font, stock, model, and other third-party terms pass through and must be listed in an asset/license manifest.
- Customer records must be exportable in a documented format, with private credentials and other tenants' data excluded.

### Written project decision required

Each agreement must state whether bespoke design, copy, code, generated media, and other commissioned work are assigned or licensed, when that change takes effect, and which rights are excluded. It must also define source delivery, reuse, modification, derivative work, portfolio/case-study use, creator-credit treatment, termination, hosting migration, data export, backup retention, and support after handoff.

Do not assume that paying an invoice automatically transfers copyright. Do not label independent-contractor work “work made for hire” without a reviewed written basis. The U.S. Copyright Office states that copyright initially vests in the author, with work-made-for-hire and written transfer rules applying in defined circumstances. Website-content guidance specifically warns that contractors can own their contributions absent an applicable agreement.

### Asset manifest

For every public asset, store: source, original owner, acquired date, license or permission, permitted surfaces, permitted edits, attribution, expiration, model/release status where relevant, approval state, source hash, public derivative hashes, and removal/renewal action.

Client approval of a visual direction does not prove ownership of the underlying photo, font, music, logo, book cover, testimonial, or generated media.

## 4. Customer logo and identity use

- Use the customer's approved master, lockup, colors, typography, and naming rules.
- Never redraw, crop, recolor, animate, or generate a replacement logo without recorded approval.
- Do not use a customer logo as proof of endorsement, partnership, certification, or case-study permission.
- Separate website-use permission from advertising, portfolio, social, press, template, and training uses.
- Check names and new marks for confusion risk before treating trademark availability as cleared. FAMtastic design or branding packages do not include legal trademark clearance unless expressly listed.
- Use `TM` or `SM` only as a business decision; use `®` only for the registered goods/services and approved registration context.

## 5. FAMtastic primary identity versus creator credit

These are different placement contracts.

### Primary FAMtastic identity

On FAMtastic-owned pages, portals, collateral, and approved branded communications, follow the canonical brand guide and its allowed compositions. A deliberately designed page background may support the full logo where the brand guide calls for it.

### Creator credit on customer work

On customer sites, proofs, Connect pages, cards, and delivered artifacts, use the creator-credit contract:

- exact approved alpha-transparent `famtastic-designs-logo-v1.png`;
- 2172 × 724 RGBA PNG, SHA-256 `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`;
- directly on the continuous parent/footer surface;
- no opaque, black, dark, colored, or contrasting plate;
- no border, rounded box, shadow, filter, recolor, crop, stretch, or redraw;
- 160–220px rendered image width unless an artifact-specific approved contract says otherwise;
- at least 44px link target with the accessible name `Created by FAMtastic Designs`;
- `https://famtasticdesigns.com/?utm_source=<public-site-slug>&utm_medium=creator_credit&utm_campaign=created_by_famtastic`;
- no email, customer name, request ID, token, private identifier, extra script, pixel, or cookie in attribution;
- verified at desktop and 390px with visible keyboard focus and fixed-control clearance.

The continuous surface may itself be light or dark. The prohibition concerns a separate wrapper or plate behind the credit. Older instructions specifying an “obsidian backing” are historical and superseded for new creator-credit presentation.

Any removal or exception must be tied to an explicit contract term or owner-approved artifact exception. Record the affected artifact and release; never infer an exception from project price or tier.

## 6. Connect Card standard

A FAMtastic Connect Card is a compact, shareable business identity object, not a resized marketing page.

- One centered card, normally no wider than 500px.
- One primary action and no more than three short supporting routes.
- Only approved public identity, contact, offer, and destination fields.
- A single approved canonical production URL controls QR, share, install, and vCard output.
- No production QR or vCard is final while the canonical production URL is unresolved.
- No private account, payment, order, reader, admin, bearer-link, or unpublished data.
- The customer card keeps the customer's own visual system; another client's composition is not a template.
- The FAMtastic creator credit sits outside the customer card on the continuous page surface.
- Asset provenance, package/version lock, and visual/browser proof accompany the deliverable.

Test desktop, 390px, a narrow phone, keyboard use, reduced motion, QR decoding, vCard fields/escaping, share fallback, canonical URLs, and creator-credit presentation. A static card does not claim CRM, analytics, scheduling, payment, lead capture, or fulfillment unless those separate systems are actually enabled and proven.

## 7. Service fulfillment

FAMtastic service fulfillment follows the accepted scope rather than the payment event alone:

1. verify payment or approved zero-dollar/grant authority;
2. create one idempotent entitlement and project record;
3. collect required client inputs and rights;
4. produce the agreed proof or build;
5. present the exact version for consolidated review;
6. record included revisions and change orders separately;
7. obtain exact-version acceptance;
8. install or deliver to the agreed destination;
9. complete owner access, training, export, backup, and recovery proof;
10. issue the delivery receipt and continue only the accepted support/renewal service.

Automation stops at missing access, missing rights, failed provider proof, failed QA, client revision, or required approval. A status screen must name the blocker, responsible party, next action, and evidence needed to continue.

## 8. End-customer commerce and delivery

Every sellable item or service must define:

- merchant of record and payout owner;
- product/edition/SKU, format, quantity, inventory authority, price, currency, territory, tax, and availability;
- payment provider, signed-event verification, idempotency, decline/abandonment handling, and reconciliation;
- digital file/access delivery or physical inventory/printing, packing, shipping, tracking, delivery target, damage/loss, returns, and support;
- refund, cancellation, dispute, privacy, and customer-contact policies;
- confirmation/receipt content and reply-capable support route;
- retention, accounting, export, audit, and exception handling.

Use exact financial integers, never floating-point approximations. Never delete completed orders or payment records. Keep sandbox/test/live states unmistakable. A checkout page, success redirect, screenshot, manually toggled status, or email is not payment proof.

Recommended order states are: `created`, `payment_pending`, `paid`, `fulfillment_pending`, `fulfilled`, `shipped`, `delivered`, `refunded`, `cancelled`, and `exception`. Not every offer uses every state, but no state may claim more than its evidence.

Before live commerce, prove success, decline, authentication challenge when applicable, abandonment, duplicate/replayed event, mismatched amount/currency, refund, cancellation, confirmation, delivery, reconciliation, support, and owner visibility.

## 9. Reviews, testimonials, and promotional claims

- Reviews and testimonials must reflect real, honest experiences and preserve the approved meaning.
- Record source, date, permission, excerpt, and any material connection or incentive.
- Disclose sponsorship, compensation, employment, family, or other material relationships clearly and near the claim.
- Never purchase or condition an incentive on a positive review.
- Do not suppress honest negative feedback through contract language that conflicts with applicable review protections.
- Do not present a staging reaction, private comment, mock quote, or synthetic fixture as a public endorsement.
- Hide empty review and press modules rather than fill them with invented social proof.

## 10. Legal, privacy, accessibility, and policy gate

Before production, the project owner reviews the applicable:

- service agreement, scope, ownership/license, portfolio permission, creator-credit, support, termination, refund, renewal, and third-party-cost terms;
- privacy notice, data inventory, retention/deletion, subprocessors, cookies/analytics, marketing consent, breach/recovery, and customer-rights process;
- commerce, tax, shipping, download, cancellation, dispute, and support policies;
- accessibility target and tested user journeys;
- regulated-industry or jurisdiction-specific obligations.

The existing FAMtastic legal pages and customer terms remain provisional until the business records attorney review or an explicit business decision to proceed. This internal standard does not make them attorney-approved.

## 11. Evidence and audit levels

Report each capability at one of these levels:

1. **Policy defined** — a rule exists.
2. **Source conformant** — code or documents implement the rule locally.
3. **Locally verified** — automated and browser checks passed in an isolated environment.
4. **Staged** — the exact release is deployed to a controlled staging destination.
5. **Production verified** — the exact production release passed live checks.
6. **Lifecycle proven** — a real or controlled provider-backed journey completed and reconciled.

Never convert a fleet inventory, source grep, or one client proof into a claim that every FAMtastic site is compliant. Each artifact needs its own release evidence.

## 12. Current agency gaps and decisions

The October 2, 2026 review found:

- **Strong:** SKU-level scope snapshots, price/renewal/refund disclosures, payment evidence boundaries, audit-minded fulfillment, client ownership of supplied content/domain, creator-credit asset provenance, and the compact Connect Card contract.
- **Needs correction:** older creator-credit documentation says `obsidian backing`; that presentation instruction is superseded by the transparent continuous-surface rule.
- **Needs one owner decision:** the standard customer agreement must choose the assignment/license model for bespoke work and define source export, portfolio use, creator-credit removal, maintenance, termination, backup retention, and migration assistance.
- **Needs qualified legal review:** current customer terms, privacy/cookie text, ownership language, refunds/renewals, accessibility, tax, AI/generated media, and interstate sales.
- **Needs per-project proof:** fleet-wide logo/footer/card conformity cannot be claimed from shared source rules. Audit each delivered artifact at desktop and phone and record its exact release.

### October 2 source scan

- The Reckoning's active Reader Site, Command Center, and Connect Card source already use the transparent continuous-surface credit and exact approved logo hash.
- Current compact-card contracts were located for The Reckoning, Tighten Up Your Locs, J.J.B.&A Transport, and Travel Addicts Courier Express. Their source/document status and production proof vary; locating a contract is not live conformance evidence.
- Canonical source documents in J.J.B.&A Transport and Travel Addicts Courier Express still copy the September 18 phrase `Compact obsidian backing is confined to the link`. Those repositories need their own versioned documentation correction and verification. Historical review submissions should remain immutable and carry a superseded notice rather than being silently rewritten.
- Several parallel FAMtastic worktrees still contain the former rounded `#070907` shared helper. Any branch that lands after this correction must resolve against the v2 source and rerun the creator-credit tests; this review does not rewrite unrelated or in-progress worktrees.

## 13. Review provenance

This draft applies the following Agency Agents reference profiles from pinned library commit `765be42358100bf89d2faa567668a94c602f9a26`:

- `support/support-legal-compliance-checker`
- `design/design-brand-guardian`
- `project-management/project-management-project-shepherd`
- `engineering/engineering-drupal-shopping-cart`

The profiles provided audit lenses, not legal advice or new authority. Primary official sources used for the legal checklist were the U.S. Copyright Office's copyright ownership, work-made-for-hire, and website-content guidance; USPTO trademark basics and likelihood-of-confusion guidance; FTC endorsement, testimonial, and consumer-review guidance; and the Florida Deceptive and Unfair Trade Practices Act index. Recheck the current sources when converting this draft into binding terms.

Official source links:

- https://www.copyright.gov/title17/92chap2.html
- https://copyright.gov/circs/circ30.pdf
- https://copyright.gov/circs/circ66.pdf
- https://www.uspto.gov/trademarks/basics/what-trademark
- https://www.uspto.gov/trademarks/search/likelihood-confusion
- https://www.ftc.gov/business-guidance/advertising-marketing/endorsements-influencers-reviews
- https://www.ftc.gov/business-guidance/resources/consumer-reviews-testimonials-rule-questions-answers
- https://www.flsenate.gov/Laws/Statutes/2025/Chapter501/Part_II
