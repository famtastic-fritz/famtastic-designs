# Canonical FAMtastic Designs logo — September 17, 2026

Owner-supplied primary master: `frontend/public/brand/famtastic-designs-logo-v1.png`.
Dimensions 2172 × 724 (3:1), RGBA PNG; the identical provenance copy is in
`docs/design/assets/`. SHA-256 `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
The hosted asset and approved email are live; website placements have a separate
source/preview status in [logo-migration.md](logo-migration.md).

## Identity and usage

- F: dimensional red, Fearless; A: yellow/gold, Applying Mastery; M: blue,
  Manifesting Extraordinary. These are logo semantics, not permission to turn
  the interface into arbitrary red/yellow/blue controls.
- “tastic”: original white handwritten signature. Crown: original lime crown.
  DESIGNS: original tracked uppercase lettering. Preserve the full descriptor.
- Digital/action accent remains `#7cfc00`; current dark surface tokens stay intact.
- Use on dark backgrounds. On a light surface, place the complete logo on a dark
  backing with clear space; do not invert/recolor the white signature.
- Retain the natural 3:1 aspect ratio, `height:auto`, no crop/stretch/filter/shadow
  over the master. Keep surrounding UI at least 12px away (about 1/6 logo height
  for primary desktop placement). Never place text on top of it.
- Header desktop 228px; normal mobile 174px; narrow 320px screens 150px.
  Footer up to 280px. The descriptor is decorative at the smallest sizes; the
  accessible link name remains “FAMtastic Designs — home.” Prefer larger usage
  when descriptor reading is necessary.
- No approved compact/favicon, light variant, crown-only mark or vector master
  was supplied. Do not invent one or squeeze this full lockup into a 28px square.
  Existing compact contexts are explicitly retained pending reviewed artwork.

Never regenerate or reinterpret the FAMtastic Designs logo with an image model
when a canonical logo asset is available. Generated artwork should reserve
appropriate logo-safe space. Production code or deterministic compositing must
place the canonical logo. No arbitrary F/A/M recoloring, changed signature,
removed crown, invented SVG, distortion or generated replacements.

## Implementation

React: `frontend/src/components/BrandLogo.jsx` and `frontend/src/lib/brand.js`.
Use the component rather than page-specific logo copies. No fake variants API.
`decorative` is only for a link/container with an explicit equivalent accessible
name. Metadata uses the same asset URL; email uses the existing PHP mailer.

See [email brand system](../design/email-brand-system.md) and its versioned
registry before changing any notification. Do not migrate old notifications
merely because the primary website logo changed.
