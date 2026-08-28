# Booked & Branded component system v1

This is an implementation and local proof of
`docs/architecture/FAMTASTIC_PAGE_COMPONENT_DOCTRINE_V1.md`. When the two
documents differ, the general doctrine governs and this file records the niche
recipe and its evidence.

## Decision

Treat every Booked & Branded site as an ordered page recipe, not a fixed HTML
page. A page recipe contains section-component instances. Each component is a
versioned mini-template made from typed fields, media slots, repeaters, actions,
visibility rules, and optional motion.

The first implementation remains one page. Component identity lets the same
site later become multiple pages without rebuilding or visually downgrading the
selected direction: move the existing component instance into a different page
recipe and retain its component ID, content, media bindings, and Build DNA.

## Canonical local contract

- Registry: `frontend/public/showcase/booked-and-branded-pilot/component-system.json`
- Renderer: `scripts/booked-branded-components.mjs`
- Build entry point: `scripts/build-booked-branded-pilot.mjs`
- Customer-facing local proof: `/showcase/booked-and-branded-pilot/component-lab/`
- Machine proof: `frontend/scripts/test-booked-branded-component-system.mjs`
- Evidence: `docs/evidence/booked-branded-component-system/image-only-proof.json`
- Research proof: `docs/research/BOOKED_BRANDED_RESEARCH_PROOF_V1.md`
- Research lab: `/showcase/booked-and-branded-pilot/research-proof-lab/`

The existing 12 proof pages now use the same component renderer. Their stable
identity anchors are:

- page: `data-page-template-id`
- section instance: `data-section-id`
- component definition: `data-component-id`
- editable field: `data-field-id`
- media or nested-content slot: `data-slot-id`

These IDs come from structured source. They are never inferred from CSS
position, DOM order, heading copy, or image filename.

## Current one-page recipe

| Order | Instance | Component | Purpose |
|---|---|---|---|
| Header | `primary-navigation` | `navigation.site-nav.v1` | Brand, anchors, primary action |
| 1 | `brand-hero` | `hero.split-brand.v1` | Positioning, hero media, status |
| 2 | `creative-system` | `proof.creative-dna.v1` | Proof-only type, shape, message, motif evidence |
| 3 | `services` | `services.card-grid.v1` | Service, duration, price, action |
| 4 | `owner-desk` | `operations.booking-desk.v1` | Phone workflow and request state |
| 5 | `payment-qr` | `payments.owner-qr.v1` | Owner-controlled payment QR and disclosure |
| 6 | `reviews` | `trust.review-grid.v1` | Consent-based moderated testimonials |
| 7 | `booking-request` | `booking.request-form.v1` | Starter request-to-book form |
| Footer | `site-footer` | `navigation.site-footer.v1` | Business summary and proof-room return |

The next foundation components are story/about, gallery, protected contact,
location/map/hours, and policies/FAQ. They are planned in the registry but are
not represented as implemented or live.

## Controlled image-only proof

The proof freezes the Velvet Coil Soft Power page recipe, copy, typography,
palette, services, controls, links, actions, ordering, and component variants.
It produces four pages using four existing assets:

1. Velvet Coil direction A
2. Velvet Coil direction B
3. Velvet Coil direction C
4. Velvet Coil Architecture premium hero

Only `hero.split-brand.v1` slot `hero-media.src` may change. The test replaces
that one URL with a sentinel and requires the complete normalized HTML of all
four pages to have one identical SHA-256 value. It also checks all nine stable
component signatures and the existence of each declared media asset.

This proves component reuse and media-slot replacement. It does not claim that
the four pages are four new art directions, that component variants have been
interchanged, that Site Studio has imported the recipe, or that any production
or customer surface changed.

## Research-proof expansion

The Research Proof Lab adds four original page recipes and eleven section
components. Unlike the image-only experiment, these recipes intentionally vary
layout grammar, type composition, color system, component variant, content
rhythm, and media story. Every rendered section carries stable component and
decision IDs; the decision ledger records the reason, sources, confidence, and
limits behind each choice.

Each recipe has one premium parent composition and three separately generated,
parent-referenced companion images for environment, process, and result/detail.
The media receipt retains prompts and hashes without inventing the provider's
model or cost. The browser contract proves all five lab/template routes at
desktop and mobile with no broken media, missing local anchors, page errors, or
horizontal overflow.

## Site Studio translation

| FAMtastic concept | Site Studio concept |
|---|---|
| Page template | Page recipe |
| Section component | Component definition |
| Section on a page | Component instance |
| Copy/value | Typed field binding |
| Image/video position | Media slot binding |
| Services/reviews/requests | Repeater binding |
| Button/link/form submit | Action binding |
| Hide/show | Instance visibility state |
| Move section | Recipe order update |
| One-page to multi-page upgrade | Move an instance between page recipes |

FAMtastic keeps proof delivery, Build DNA, customer history, business truth,
approval, and communication authority. Site Studio may consume the selected
recipe and component bindings as build context, journal its real build stages,
and return the artifact hashes and Build DNA continuation. It must not silently
change the selected visual system, activate a booking provider, send a message,
publish, or create a competing customer record.

## Next proof sequence

1. Add the five missing website-foundation components without changing the
   current image-only experiment.
2. Create two variants for one component at a time, beginning with hero and
   services. Preserve field contracts while changing composition.
3. Prove hide/show and reorder operations against an allowlisted recipe.
4. Split one long page into a Home + Services + Contact recipe while retaining
   component IDs and visual continuity.
5. Export the selected recipe and bindings into a Site Studio build packet;
   record the real import and return evidence before upgrading capability.
