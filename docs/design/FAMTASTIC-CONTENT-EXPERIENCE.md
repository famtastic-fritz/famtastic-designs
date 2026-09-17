# FAMtastic Content Experience v1

Status: **local owner-review checkpoint**, September 17, 2026. The earlier visual
DNA release is live; this page-system extension is not. First proof route only:
`/packages/199-quick-start`. Branch: `codex/content-experience-web-basics`.

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
| Package detail | 2 | Offer → features → fit → optional add-ons → pricing → education → finale | Web Basics only |
| Packages hub | 2 | Introduction → comparison → education → finale | Planned |
| Solution detail | 2 | Problem → system → outcome → process → related → finale | Planned |
| Services hub | 2 | Introduction → capabilities → related → finale | Planned |
| About | 3 | Story → philosophy → F/A/M definition → founder → process → finale | Planned |
| Contact | 2 | Invitation → existing conversation form → real next-step state | Planned |
| Intake / Get Started | 1–2 | Guided action, minimal distraction | Existing, unchanged |
| Login / account threshold | 2 | Brand entry, conventional authentication | Existing, unchanged |
| Portal / admin | 1 / 0–1 | Operational contracts govern | Excluded |
| Legal / privacy / terms | 0–1 | Readability over expression | Excluded |
| Home / Work | 3 | Existing homepage / project-world architecture | Excluded |
| Blog | Separate | Await the owner's separate direction | Excluded |

No recipe name enrolls routes automatically. Use the exact allowlist in
`CONTENT_PREVIEW_ROUTES`; never infer styling from a dollar amount or title.
After owner review, propagate deliberately through Packages → Solutions →
Services → About → Contact, with a separate recipe/proof per family. Proposed
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
  owner-review framing, not new deliverables or outcome guarantees.

## Acceptance and local review

Use the existing Node22/Vite development server with the read-only public Drupal
proxy, then open `http://127.0.0.1:4187/packages/199-quick-start`.
Do not submit forms through a production proxy.

```sh
VITE_DRUPAL_PROXY_TARGET=https://famtasticdesigns.com/web npm --prefix frontend run dev -- --host 127.0.0.1 --port 4187
node scripts/test-content-experience.cjs
npm --prefix frontend run build
node scripts/sync-brand-assets.cjs --check
```

The browser test blocks non-GET/HEAD requests and checks320/390/768/1024/1440,
existing content, guide data, safe links, native headings, keyboard/reduced motion,
font fallback, HTML escaping, missing package and exact route opt-in. It produces
local screenshots plus a checked-in sanitized result; no real request/payment/send.
Review gallery is generated by `node scripts/render-content-experience-review.cjs`
and served by the existing8765 static-preview server at `/content-experience/`.

Build DNA: `docs/evidence/content-experience-web-basics/build-dna.json`. Initial
record timing is disclosed; no hidden model/cost/timing is invented. No Drupal
projection or Site Studio packet is needed for this agency-only local checkpoint.

Dev-only pagination correction: absolute Drupal `/web/jsonapi` next links now
continue through Vite's same-origin `/jsonapi` proxy when using its default base.
Production still uses absolute links. No blog content, design or publication changed.

Review completion is not production authorization. Preserve the deployed brand
release until the owner approves this proof and its eventual release separately.
