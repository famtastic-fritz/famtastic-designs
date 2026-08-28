# Booked & Branded research proof v1

## Decision

Booked & Branded is not a campaign asking beauty and grooming operators to
abandon a marketplace overnight. It is an owner-controlled brand and repeat-
client layer that can coexist with the operator's current booking provider,
then expand as the business proves which upgrades create value.

The first local proof contains four original one-page recipes, eleven reusable
component definitions, sixteen cited component decisions, four premium parent
media compositions, and twelve reference-led companion compositions. It is a
fictional product demonstration. It did not import a prospect, contact a real
business, activate a provider, process a payment, publish, or deploy.

## Competitive baseline

The argument begins by respecting what marketplace products do well.

| Platform | Officially documented strength used in the proof | Opportunity for FAMtastic |
|---|---|---|
| [Booksy](https://biz.booksy.com/features) | Booking, client management, reminders, reviews, marketplace discovery, QR, website widget, mobile operations, and payments | Give the owner a memorable domain, expressive brand system, complete website foundation, and a clearer owned/repeat-client front door while keeping Booksy available |
| [Booksy Boost](https://biz.booksy.com/features/boost) | Optional marketplace visibility with a one-time first-visit commission and no subsequent commission on that client | Help the owner distinguish discovery from repeat relationships without claiming the marketplace has no value |
| [Square Appointments](https://squareup.com/us/en/appointments/pricing) | Scheduling and business operations with booking-site, button, widget, and QR connection options | Offer a provider-neutral branded layer; do not require FAMtastic to become the payment processor |
| [Fresha](https://www.fresha.com/pricing) | Marketplace, scheduling, client operations, and published independent/team pricing | Differentiate through owner identity, compositional design, portable content, and an upgrade path rather than a second generic listing |
| [GlossGenius](https://glossgenius.com/pricing) | Booking website plus increasingly deep operational tiers | Preserve the low-friction starter while giving FAMtastic components room to grow into deeper services later |
| [Vagaro](https://www.vagaro.com/pro/pricing) | Booking, calendar, marketplace, and business-management tooling | Compete on brand ownership, explanation, editorial proof, and continuity rather than pretending the starter duplicates every operational feature |

Prices and product terms change. The frozen source manifest records what was
observed on August 27, 2026, but live commercial copy must recheck the official
vendor page before publication.

## Design research boundary

The decision ledger uses research as a constraint and source of testable
hypotheses—not as a promise that one color, shape, animation, or CTA will cause
conversion.

- [Tuch et al.](https://research.google/pubs/the-role-of-visual-complexity-and-prototypicality-regarding-first-impression-of-websites-working-towards-understanding-aesthetic-judgments/) informs the balance between recognizable website structure and controlled visual complexity.
- [Bar and Neta](https://doi.org/10.1111/j.1467-9280.2006.01759.x) supports using curvature as one preference signal, not a universal personality rule.
- [Reber, Schwarz, and Winkielman](https://psy2.ucsd.edu/~pwinkiel/reber-schwarz-winkielman-beauty-PSPR-2004.pdf) informs processing-fluency decisions around hierarchy and legibility.
- [Shaikh and Chaparro](https://journals.sagepub.com/doi/10.1177/154193120504900514) informs controlled reading width.
- [Hernandez and Resnick](https://journals.sagepub.com/doi/abs/10.1177/1541931213571232) informs visible primary actions and eye-tracking as a future test, not a guaranteed outcome.
- [WCAG 2.2 target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html), [animation from interactions](https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html), and [pause, stop, hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide.html) govern touch and motion safety.

Color-emotion literature is too contextual and contradictory to justify
claims such as “red converts” or “blue creates trust.” Palettes in this proof
belong to each recipe's researched art direction and still require real-user
testing.

## Four new recipes

| Recipe | Fictional operator | Emotional job | Dominant grammar |
|---|---|---|---|
| `crown-ledger-v1` | Port St. Lucie precision barber | Confidence and calibration | Cropped ledger, oversized grotesk, blackened metal, parchment, hard measurement marks |
| `coil-ritual-v1` | Fort Pierce texture-care studio | Care and preparation | Curved frames, editorial serif, clay/linen warmth, botanical shadow, ritual pacing |
| `barrio-signal-v1` | West Palm Beach bilingual barber | Recognition and neighborhood energy | Poster scale, ribbons, local grid, clipped corners, coral/teal signal color |
| `salt-glass-v1` | Miami color studio | Clarity and transformation | Prismatic arches, airy editorial serif, translucent panes, pearl/sea-glass light |

These are new page recipes, not copies of Kimi output. The user-supplied Kimi
transcript was hashed and used only for clean-room pattern extraction:
optional bilingual copy, dotted service leaders, condensed display type with
utility text, and environment/process/result/operator media storytelling. No
Kimi HTML, CSS, prompts, image bytes, or proprietary implementation was copied.
The live Kimi URL returned a connection reset during this run, so the evidence
makes no visual-parity claim.

## Component and decision contract

Every rendered template exposes:

- `data-page-template-id`, `data-page-template-version`, and a recipe signature;
- stable section, component, component-variant, field, and media-slot IDs; and
- decision IDs that resolve to a human- and machine-readable reason, source
  trail, and confidence label.

The eleven components are navigation, research hero, market wedge, services,
reference-led gallery, portable booking bridge, phone owner console, contact /
location foundation, Shay close, component-decision ledger, and footer. They
are stored in
`frontend/public/showcase/booked-and-branded-pilot/component-system.json`.

The lab does not claim every decision causes conversion. Its stronger claim is
that another builder can see exactly why a component exists, what it may swap,
and which evidence or judgment influenced it.

## Media execution

For each of the four premium parent compositions, three separate companion
images were generated using the parent as a visual reference. The companions
fill distinct component roles: environment, process, and result/detail. Native
HTML remains responsible for all readable text and interface controls.

The receipt retains the exact prompts, source PNGs, optimized WebP files,
parent/reference hashes, output hashes, and conversion settings. The built-in
image provider reported neither model identity nor per-generation cost, so the
receipt says `provider_did_not_report`; it does not falsely label the companion
images “cheaper.” The factual result is 16 provider generations and 32 retained
encodings.

## NotebookLM handoff

The NotebookLM MCP authenticated successfully, but no notebook is registered.
NotebookLM requires an owner-supplied shared notebook URL before this packet can
be imported and queried. The import-ready source list and research questions
are frozen in
`frontend/public/showcase/booked-and-branded-pilot/research/source-manifest.json`.
No source was represented as NotebookLM-verified during this run.

## Site Studio translation

Site Studio may consume the four page recipes, eleven component contracts,
typed bindings, media bindings, and cited decision IDs as immutable build
context. It may create new instances and variants only through an explicit
recipe/change request, preserve the selected visual system, and return real
artifact and Build DNA evidence.

This local proof does not show that Site Studio imported the packet, that
Drupal registered it, or that a live business received it. Those remain
separate evidence gates.

## Canonical evidence

- Lab: `/showcase/booked-and-branded-pilot/research-proof-lab/`
- Research data: `frontend/public/showcase/booked-and-branded-pilot/research/`
- Component registry: `frontend/public/showcase/booked-and-branded-pilot/component-system.json#research_proof_lab`
- Media receipt: `frontend/public/showcase/booked-and-branded-pilot/research-proof-lab/media-generation-receipt.json`
- Build DNA: `frontend/public/showcase/booked-and-branded-pilot/build-dna.json`
- Browser evidence: `docs/evidence/booked-branded-research-proof/browser-qa.json`
- Renderer: `scripts/build-booked-branded-research-proof.mjs`
- Contract test: `frontend/scripts/test-booked-branded-research-proof.mjs`
- Browser test: `frontend/scripts/e2e-booked-branded-research-proof.mjs`

## Reproduction

```bash
node scripts/finalize-booked-branded-research-media.mjs
node scripts/build-booked-branded-pilot.mjs
node frontend/scripts/test-booked-branded-component-system.mjs
node frontend/scripts/test-booked-branded-research-proof.mjs
node frontend/scripts/e2e-booked-branded-research-proof.mjs
```

The browser command expects the local frontend on port `4180`. Production
deployment, real outreach, booking-provider connection, customer creation,
payments, and publication remain explicit approval gates.
