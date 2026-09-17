# Web Basics content-experience checkpoint

Status: local tested, owner visual review pending. Branch:
`codex/content-experience-web-basics`, based on7bf7d9d5. No main push or deployment.

## See it

- Interactive: http://127.0.0.1:4187/packages/199-quick-start
- Desktop/mobile before/after: http://127.0.0.1:8765/content-experience/
- Source rules: `FAMTASTIC-CONTENT-EXPERIENCE.md`, root `design.md`, canonical
  `FAMTASTIC-DESIGN-SYSTEM.md` and AGENTS.md.

The creative-direction pass changed visual pacing, not the offer: open typographic
hero/metal price plate, glass numbered deliverables, editorial fit strip, explicit
cost comparison/renewal terms, existing guide cards and branded closing section.
No image generation, new logo, font dependency or continuous motion was added.

## Changed source

New scoped primitives/composition/CSS/recipe registry live under
`frontend/src/components/content-experience/`. PackagePage selects only the exact
Web Basics route; RelatedEducation has an explicit presentation prop, defaulting
to the existing style everywhere else. Canonical source/metadata/analytics and
research destinations remain in their existing components.

Small supporting fix: `utils/jsonApiPagination.js` keeps dev-mode absolute next
links on the configured JSON:API proxy. The previous local guide list failed CORS
on page2 and discarded all posts. Production URL behavior is unchanged and tested.
Baseline screenshots predate that correction; gallery labels this limitation.

## Checks actually run

- `node scripts/test-content-experience.cjs`: five viewport widths, six preserved
  features, four published guides, correct destinations/disclosures, no page
  overflow/exceptions,44px targets, real keyboard focus/reduced motion, blocked
  script-font fallback, escaped hostile strings, missing package and seven excluded
  route smoke checks. All non-GET/HEAD traffic blocked; none was attempted.
- `node scripts/test-website-logo.cjs`: six viewport/logo/navigation checks pass.
- `node scripts/test-cursive-headings.cjs`: existing homepage loaded/blocked-font
  checks at four widths pass.
- `node scripts/sync-brand-assets.cjs --check`: unchanged shared assets pass.
- `npm --prefix frontend run build` onNode22.23.2: pass. JS846.78kB/gzip246.35;
  CSS188.73kB/gzip36.43. Versus prior release: +10.36kB JS/+2.51kB gzip and
  +12.57kB CSS/+2.42kB gzip. Existing>500kB bundle warning remains.
- Build DNA validator: two stages,12 portable artifact hashes pass. Initialization
  during construction is explicitly disclosed; timing/cost are not fabricated.

No real intake, customer login, email, payment or production acceptance is claimed.
This is not a full accessibility audit or independent visual approval. Author
inspected rendered desktop/mobile; owner review is the remaining checkpoint.

## Next gate

Owner feedback on this page first. Then propagate the approved primitives with
distinct family recipes: Packages → Solutions → Services → About → Contact.
Blog, Work/project worlds, portal/admin and live site remain outside this pass.
