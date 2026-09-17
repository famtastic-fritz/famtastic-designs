### Preserve before enhancing.

FAMtastic visual DNA is a layer of identity, not a replacement for successful architecture. Apply expression according to the purpose of the surface. Public experiences may be highly expressive; operational experiences become progressively more restrained.

### FAMtastic is not lime green.

Lime communicates energy, action, state, and signature. FAMtastic identity comes from the complete system: dimensional F/A/M color semantics, obsidian materials, tactile texture, handwritten expression, crown gestures, controlled glow, cinematic depth, and purposeful motion.

# FAMtastic visual design system v1

Owner direction: September 17, 2026. **Owner approved production release.**
Deployment evidence is recorded in `BRAND-DNA-RELEASE-2026-09-17.md`.
This is the canonical visual specification. Root `design.md` retains experience,
information architecture and workflow rules and imports this specification.
`BRAND.md` owns philosophy/voice; portal Design DNA owns operational constraints;
email guidance owns transport/client compatibility. None authorizes redesign,
new claims, payment, communication, deployment or invented success states.

## Identity and provenance

The owner PNG is the sole logo source. F = dimensional red / Fearless;
A = dimensional gold / Applying Mastery; M = dimensional blue / Manifesting
Extraordinary; white handwritten tastic, lime crown, tracked DESIGNS. Preserve
the original SHA and 3:1 proportions; no AI redraw, filters or alternate masters.
See `../brand/famtastic-designs-logo.md` and `crown-derivation.json`.
Use BrandLogo for React; byte-identical theme-local copy for Drupal.

The crown is extracted from the PNG's green pixels, not from the earlier AI
concepts. `scripts/derive-brand-crown.cjs` records bounds, chroma matte, optical
small-size compensation and hashes. `famtastic-crown-master.png` retains source
color; `famtastic-crown-flat.png` maps its same alpha silhouette to #7cfc00.
No reliable reviewed vector master exists. `favicon.svg` is explicitly a 32px raster
image in an SVG container, not a claimed vector trace. HTML advertises native
PNG/ICO instead of this optional container for widest small-icon compatibility.

## Materials and intensity

| Material | Definition | Use and limit |
| --- | --- | --- |
| Obsidian | #070907, tiny low-contrast CSS grain | Foundation, not flat pure black |
| Black glass | rgba(16,19,16,.88), #252b25 border, blur when supported | Existing panel geometry; solid fallback |
| Carbon grain | Very faint repeated dark tonal pattern | Portal/admin shell; not data-cell backgrounds |
| Brushed metal | Directional graphite lines | Dividers/proof frames; not long-copy surfaces |
| Stardust | Sparse static pinpoints | One hero/reveal region; no new particle loop |
| FAM brush | Red/gold/blue clipped strokes at 2–5% | Authorship/story/transition; never arbitrary rainbow controls |
| Gold cinematic | Warm low-opacity light | Opt-in premium/campaign moments, not routine billing |
| Lime energy | #7cfc00 | Action/state/focus/energy; not general decoration |

Machine-readable contract: `famtastic-site-dna.v1.json`. Runtime tokens and optional
primitives: `frontend/public/brand/famtastic-dna.css`; backend copy is generated
by `scripts/sync-brand-assets.cjs`. Test checks copies match. Never edit the copy.

FAM 0 = utility (tables/logs/forms). FAM 1 = branded (ordinary portal).
FAM 2 = expressive (public sections/welcome). FAM 3 = hero/campaign/reveal.
Expression decreases as operational density increases. This 0–3 scale is NOT
the existing customer 0–10 creative-intensity preference or a Build DNA replacement.
Public = 2, selected hero = 3; client = 1, welcome = 2; admin = 0/1.
Materials/intensity are opt-in vocabulary, not instructions to apply all effects.

## Glow and states

0 none; 1 ambient `0 0 16px rgba(124,252,0,.08)`;
2 interactive `0 0 20px rgba(124,252,0,.20)`;
3 hero `0 0 24px rgba(124,252,0,.35), 0 0 64px rgba(124,252,0,.10)`.
At most ONE dominant hero glow per viewport. Portal retains its stricter existing
single-glow constraint: only active keyboard focus may glow in operational UI;
other selected states use fill/border/text. No simultaneous glowing cards.
Focus always also has a visible outline; state always also has text/icon/ARIA.
Red destructive controls remain red and conventional; never add crowns to them.

## Typography, brushes, buttons and panels

Interface: Inter/system-ui, all functional copy, prices, labels, tables and legal.
Display: existing Space Grotesk/bold sans; no new font requests.
Signature: system handwritten stack (Segoe Print, Bradley Hand, cursive), italic
fallback; six words maximum, emotional/editorial fragments only. Not a replacement
for the original logo signature or permission to restyle required information.

Owner-approved heading extension (September 17): public H1/H2 may opt into one
short cursive phrase, maximum six words, using `SignatureHeading`. Self-hosted
Kaushan Script supplies consistent brush/cursive lettering; `font-display: swap`
and system handwritten fallback keep content available. Existing footer signature
keeps its system stack. Do not globally restyle heading tags or change CMS words.
Portal script is limited to optional welcome/celebration; admin operational
headings, prices, forms and system messages stay sans. This font addition supersedes
the initial review's no-new-font implementation note, not email's no-webfont rule.
Source/license: `frontend/public/brand/fonts/README.md` and `OFL.txt`.

Brush grammar: lime = action underline/selection; white = short editorial accent;
F/A/M = sparse brand story. CSS clipped primitives are decoration, never fake logo
paths. Links remain distinguishable without motion. Primary existing buttons keep
lime/dark text and geometry, receive restrained depth. Secondary gets black glass
and lime outline. Existing card layout/radius is preserved; depth is edge lighting
and low-contrast material, not giant shadows or constant glow.

## Crown primitive

`FAMCrown` intents: signature, featured, success, identity. Intensities: subtle,
normal, hero. All render the same extracted asset. Decorative by default; optional
accessible label only when necessary. One prominent crown per visual region;
do not duplicate the full logo crown in the same header region.
Authorship/featured/success/achievement are valid meanings. Success/approved use
must be backed by actual existing state; never manufacture a completed project
to showcase an icon. Never use for warning/delete/settings/generic navigation.
Admin success markers may decorate only verified major completion states, not
routine status messages or their semantics. None is added to fabricated live data.

## Surface application

Owned content-page recipes and reusable primitives are governed by
`FAMTASTIC-CONTENT-EXPERIENCE.md`. Shared identity must not become identical layouts.
Web Basics and the explicit 17-route family propagation were owner-approved on
September 17, then released at `71620bf1`. See
`CONTENT-EXPERIENCE-RELEASE-2026-09-17.md`. Shared primitives do not permit enrollment
outside the exact route registry. Contact's success crown means a server-confirmed
saved request; partial notification failure remains visible and is not send proof.

- Public: opt-in agency Layout wrapper; existing hero, panels, buttons, footer.
  Preserve all copy, sections, routes, $199 terms and individual project worlds.
  FAMtastic is the gallery; client work is the art. No universal `button` restyle.
- Client: scoped `.portal-app` material and keyboard focus, next-decision authorship
  crown. Preserve project/files/proof/billing/messages/approval behavior.
- Admin: only shell/login/welcome materials and selected navigation; no table/log
  decoration, no playful danger controls. Existing native forms/access checks stay.
- Email: existing OutreachMailer/StagingReviewEmail is a consumer, not a new system.
  Inline #070907/#7cfc00/#f7f7f4 and single CTA glow already align. Preserve dynamic
  escaped content, plain-text alternative, template versions and existing delivery.
  Crown already appears inside canonical logo; no extra remote asset or duplicate
  crown is required. No EmailShell, replacement renderer or notification migration.

## Favicon family

Social-profile use is separate: owner prefers full logo where readable and allows
original-pixel FAM + the canonical crown for small avatars. See
`../brand/SOCIAL-PROFILE-KIT.md`. This exception does not change the crown-only
website favicon or permit regeneration of the master artwork.

Canonical lime crown on #070907; no letters. ICO (16/32/48), SVG raster container,
16/32/48 PNG, 180 Apple, 192/512 Android. At 16/32: flat, no grain/glow; optical
weight compensation only. At 48: flat; at 180+: restrained glow. Manifest declares
application identity/theme/background without adding a service worker, install
prompt, offline behavior or implied PWA capability. Drupal theme metadata uses
theme-local copies; the September 17 baseline activation is recorded in the release receipt.

## Motion, accessibility, performance

Only interaction-triggered underline/glow transitions; no new continuous motion.
Reduced-motion disables these transitions and decorative movements. Existing
particle/motion behavior is not expanded. Dense admin never animates routine data.
44px important touch targets, readable contrast, semantic controls, keyboard focus,
unchanged content hierarchy. Texture cannot carry meaning or obscure text.
CSS textures avoid heavy images; no new fonts/dependencies/video. Report actual
asset/build deltas, not a Lighthouse/performance guarantee. Browser screenshots
do not establish Gmail/Outlook compatibility or real customer workflow success.

## QA and release boundary

Audit: `BRAND-DNA-AUDIT-2026-09-17.md`. Handoff records changed files, source refs,
before/after images, fixture limitations, icon tests and exact checks. The original
baseline was subsequently released; see `BRAND-DNA-RELEASE-2026-09-17.md`.
The separately authorized content-experience release is recorded in
`CONTENT-EXPERIENCE-RELEASE-2026-09-17.md`. Future changes remain review-gated:
no deployment or customer send follows merely from implementing a local page.
