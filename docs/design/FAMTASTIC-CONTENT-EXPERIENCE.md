# FAMtastic Content Experience v1.1

Status: **owner-approved direction, expanded local review**, September 17, 2026.
The first Web Basics proof at5d98e4d1 was approved with “Approved!”, authorizing the
planned family propagation. The earlier visual DNA release is live; this page-system
extension is not. Branch: `codex/content-experience-web-basics`. The 17 existing
routes and six recipes are explicit; no new /solutions URL or CMS schema was added.

## Share identity, not identical layouts

Content pages may share primitives, but must not collapse into one template.
Packages feel commercial; solutions engineered; About editorial/personal;
Contact inviting; Work cinematic; Admin operational. Blogs have a separate
direction and are excluded from this pass. Customer project worlds are not agency
content canvases. Preserve before enhancing still governs every change.

## The implemented foundation

Source: `frontend/src/components/content-experience/`. Version/route enrollment,
recipe IDs and material allowlists live in `recipes.js`; it is the machine-readable
authority, not a duplicate CMS. Primitives receive data, never fetch or change
customer state. Existing Drupal nodes, adapters, SEO and analytics remain in place.

| Primitive | Contract and purpose |
| --- | --- |
| FAMPage | Stable page ID + recipe ID; opts into the scoped CSS and intensity |
| FAMPageHero | Native H1, eyebrow, lede, existing actions, optional breadcrumb/offer slot |
| FAMSection / FAMSectionHeader | Stable section/heading IDs, H2, optional short script fragment |
| FAMSurface | Explicit glass, obsidian, metal, brush, spotlight or technical material |
| FAMStatement | Editorial interruption; H2 plus existing supporting content |
| FAMFeatureGrid | Ordered source items with IDs/text; no inferred capabilities or fake icons |
| FAMInsightCard | Existing published guide ID/title/summary/series/link; H3, not fake proof |
| FAMCTA | Safe internal or HTTPS navigation; primary, secondary or editorial variant |
| FAMFinale | Closing H2, context, same approved next action; no invented success |
| FAMCrown | Reuses the existing original-derived crown; no independent drawing |
| FAMBrush | CSS decoration only; identity, lime or white; never a substitute logo |

This is a React presentation layer, not a new page builder, arbitrary component
reordering UI or a Site Studio integration. Stable page/section IDs are declared
in the composition. Feature IDs bind the source node and its ordered field slot;
Drupal's legacy feature strings do not have independent entity IDs. Reordering
those strings requires explicit binding review, not a claim of automatic migration.

## Recipes and enrollment

| Family | Intensity | Character / ordered composition | Status |
| --- | --- | --- | --- |
| Package detail | 2 | Offer → features → fit → optional add-ons → Web Basics pricing only → education → finale | Seven existing packages, local |
| Packages hub | 2 | Introduction → scope comparison → finale | Local |
| Solution detail | 2 | Brief/index → challenge → system → source process → reviewed proof if present → deliverables → FAQ → education → existing finder → finale | Six existing services, local |
| Services hub | 2 | Introduction → indexed capability rows → finale | Local; existing Solutions destination |
| About | 3 | Story → F/A/M definition → philosophy → existing business story → finale | Local; no invented founder biography |
| Contact | 2 | Invitation → existing project-fit intake → existing conversation form / real next-step state | Local; synthetic transport QA only |
| Intake / Get Started | 1–2 | Guided action, minimal distraction | Existing, unchanged |
| Login / account threshold | 2 | Brand entry, conventional authentication | Existing, unchanged |
| Portal / admin | 1 / 0–1 | Operational contracts govern | Excluded |
| Legal / privacy / terms | 0–1 | Readability over expression | Excluded |
| Home / Work | 3 | Existing homepage / project-world architecture | Excluded |
| Blog | Separate | Await the owner's separate direction | Excluded |

No recipe name enrolls routes automatically. Use the exact allowlist in
`CONTENT_ROUTES` (`CONTENT_PREVIEW_ROUTES` exports its keys); never infer styling
or research destinations from a dollar amount or title. The owner's approved
Packages → Solutions → Services → About → Contact propagation keeps a separate
recipe/proof per family. Proposed
404, auth, help and success/empty-state treatments are not implemented here.

## Visual rules that future pages must follow

1. Use existing canonical tokens and assets. No new logo, font, dependency,
   stock-photo filler or independently drawn crown. No pasted screenshot as UI.
2. Keep all new rules below `.fam-page`. Do not style bare H1/H2/button/card
   selectors globally or alter customer showcase/portal/admin/blog surfaces.
3. Use material contrast to establish rhythm: an open hero, compact grid,
   editorial strip, focused value panel, open educational list, closing moment.
   Do not put every section in the same gray rounded card.
4. Selective cursive: at most one short phrase per H1/H2, six words maximum,
   via `SignatureHeading`. Prices, navigation, controls, forms, legal terms and
   operational messages stay sans. Script is an accent, not a full-page font.
5. Typography hierarchy: one H1, section H2s, item H3s. Body text remains real,
   selectable HTML. Responsive sizes wrap; no clipping or small-screen truncation.
6. One dominant glow per viewport. This implementation gives no resting CTA a
   dominant glow; hover/focus may glow, always with a visible keyboard outline.
7. One prominent crown per region. Never label a state successful/approved just
   to justify a crown. Keep warnings/destructive actions conventional.
8. F/A/M color strokes are low-opacity authorship decoration, not status or price
   encoding. Text must remain readable without textures, fonts, images or motion.
9. Navigation CTAs stay links; submissions stay native form buttons and existing
   handlers. No CTA may bypass saved-request, client approval or payment gates.
10. Motion is interaction-only and disabled for reduced-motion. No scroll-jacking,
    perpetual animation, new particles or animation-delayed required content.
11. At least44px targets, 320px no-overflow, visible focus, sufficient contrast,
    selectable prices and nearby non-script disclosures are release requirements.

## Web Basics content and commercial invariants

- Source: live published package node60449381-e8c0-4783-8c48-d2ac6ed4c492,
  existing RelatedEducation selection and canonical FAM-FOOT-199/FAM-HOST-999 terms.
- Preserve all supplied deliverables, best-for text, timeline and optional add-ons.
  Do not manufacture testimonials, included mailboxes, rank guarantees or speed promises.
- Display current CMS price. Cost-per-day is mathematically derived over365 days,
  clearly “about” and “not daily billing”; do not imply a recurring $199/year offer.
- Hosting after the included first year is optional $9.99/month, separately
  authorized. Domain renewal is separate at disclosed registrar price; preserve
  the existing-domain distinction. Tests detect drift against canonical renewal data.
- Research CTA stays `/start?option=web-basics`; comparison links remain
  `/packages` and `/website-options`. No direct `/buy` link is introduced.
- Payment follows final acceptance of the completed staging artifact; a design
  selection, local preview or research action is not payment authorization.
- Editorial additions (“Give your business a home”, “A future”) are proposed
  framing approved with the first proof, not new deliverables or outcome guarantees.

## Family-specific invariants

- Package detail uses the specialized Web Basics composition only at
  `/packages/199-quick-start`. Other enrolled packages render their exact CMS
  price/scope, including recurring labels, without borrowing $199 renewal terms.
  Research destination is exact-slug based: a $1,999 price must not match “199”.
- Package comparison retains CMS sort order, badges, best-for, timeline, first
  seven features and existing destinations/select-item analytics. Capability rows
  retain service order, headline/description, first four source features and links.
- Solution details retain every existing challenge, solution bullet, process step,
  deliverable, FAQ, published education link and branch-selected SolutionFinder.
  Quotes still require reviewed proof fields; no legacy seed testimonial revival.
  The index is real anchor navigation, not a decorative or simulated workflow.
- About's F/A/M letter markers are editorial type, not a new logo. The three exact
  approved meanings accompany the existing Drupal business story. Never fabricate
  a founder portrait, biography, team, timeline, metrics or process to fill a recipe.
- Contact keeps project-fit before the contact form, existing IDs, labels,
  validation, request payload, transport and mail fallback. The opt-in crown needs
  `ok === true`, positive integer `request_id`, and `received` or `partial_success`.
  Partial notification failure keeps the server explanation visible. An incomplete
  receipt or failed transport shows no crown; a mail draft is not saved/send proof.
- Preserve source copy during a visual pass. Existing CMS speed/performance promises
  and the contact one-business-day copy versus backend three-day default need a
  separate factual review; they are not newly verified claims or new guarantees.

## Acceptance and local review

Use the existing Node22/Vite development server with the read-only public Drupal
proxy, then open `http://127.0.0.1:4187/packages/199-quick-start`.
Do not submit forms through a production proxy.

```sh
VITE_DRUPAL_PROXY_TARGET=https://famtasticdesigns.com/web npm --prefix frontend run dev -- --host 127.0.0.1 --port 4187
node scripts/test-content-experience.cjs
node scripts/test-content-families.cjs
node scripts/capture-content-families.cjs after
node scripts/render-content-families-review.cjs
npm --prefix frontend run build
node scripts/sync-brand-assets.cjs --check
```

The Web Basics browser regression blocks non-GET/HEAD requests and checks320/390/768/1024/1440,
existing content, guide data, safe links, native headings, keyboard/reduced motion,
font fallback, HTML escaping, missing package and exact route opt-in. It produces
local screenshots plus a checked-in sanitized result; no real request/payment/send.
The family suite checks all17 routes at320/390/768/1440, source-content fidelity,
FAQs, actual keyboard entry, six-family font failure and synthetic contact receipts.
Only its explicitly mocked contact POSTs are exercised; other writes are blocked.
Expanded before/after gallery: `http://127.0.0.1:8765/content-rollout/`. Capture
`before` only from the parent proof revision, never overwrite it with new source.

Current Build DNA: `docs/evidence/content-experience-rollout/build-dna.json`.
Parent Web Basics Build DNA is frozen at5d98e4d1; validate its source hashes at that
revision, not against the changed descendant. Do not rerun its historical renderer
to replace the approved receipt. Both disclose actual timing/model/cost limits.
No Drupal projection or Site Studio packet is needed for this agency-only checkpoint.

Dev-only pagination correction: absolute Drupal `/web/jsonapi` next links now
continue through Vite's same-origin `/jsonapi` proxy when using its default base.
Production still uses absolute links. No blog content, design or publication changed.

Review completion is not production authorization. Preserve the deployed brand
release until explicit release approval. See `CONTENT-EXPERIENCE-ROLLOUT-2026-09-17.md`.
