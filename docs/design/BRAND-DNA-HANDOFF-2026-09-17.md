# FAMtastic visual DNA — owner review handoff

## Follow-up: cursive headings

Owner-approved local extension adds three homepage phrases: H1 “Engineering Studio”
(fallback copy: “Work You Need Done”), services H2 “real business outcomes.” and
final CTA H2 “As Hard As You Do”. Text, native heading levels and page sections
remain unchanged. Explicit SignatureHeading usage is not a global heading rule.
Self-hosted Kaushan Script Latin WOFF2 adds 34,748 bytes on pages that use it;
OFL license/provenance are bundled. This supersedes the initial pass's no-new-font
note below; email, logo lettering and operational headings remain untouched.
Root design.md and visual specification record the constraints. Local captures:
`cursive-390.png`, `cursive-1440.png` and `-fallback` versions in the review folder.
The existing before/after gallery's after images are refreshed. No deployment.
Checks: eight loaded/blocked-font viewport cases pass; existing six-width public
logo/navigation checks pass; production build passes with the existing chunk-size
warning. Final JS836.42kB/gzip243.84kB; CSS176.16kB/gzip34.01kB, plus the 34,748-byte font.

## Status / branch

**Ready for owner visual review, not release certification.** No merge, deployment,
main push, notification, payment or customer-state mutation occurred in this pass.
Branch: `famtastic/brand-dna-site-enhancement-v1`.
Baseline: `1566d531652fcf2ac0bde0cbc9120ba6f2db9c84`.
Initial implementation commit: `06b55b0a`, subject
`feat(brand): apply FAMtastic visual DNA across owned surfaces`.
The later cursive-heading extension is a separate local review commit.
Local repository: `/Users/famtastic-fritz/Development/worktrees/fd-client-selected-build-flow`.
This is the substantive agency worktree, not the unrelated Documents checkout.

## What changed / visual DNA added

- Canonical `FAMTASTIC-DESIGN-SYSTEM.md`: preserve before enhancing and FAMtastic
  is not lime green at the top. Root `design.md` still governs experience/workflow;
  BRAND.md retains philosophy. Agent entrypoints link the same visual authority.
  CLAUDE.md already delegates via its AGENTS.md symlink; no duplicate rulebook.
- New `famtastic-site-dna.v1.json` records surface intensity, materials, semantic
  glow, typography, crown, brushes and exclusions. It does not replace Build DNA
  or reinterpret the customer's existing 0–10 creative preference.
- Shared CSS: obsidian, black glass, carbon grain, metal, stardust, F/A/M brush,
  gold cinematic, lime and glow 0–3. Most expressive primitives are opt-in rather
  than all applied everywhere. No new remote fonts, dependencies or video.
- Public: current hero gets faint identity brush/grain and small authorship crown;
  existing panels/buttons receive depth; footer's existing four-word phrase gets
  signature typography. Existing layouts, pages and hierarchy stay intact.
- Client: shell grain, panel glass, focus outline/glow and next-decision authorship
  crown. Existing persistent button glow is restrained outside keyboard focus.
- Admin: shell/login carbon, navigation grain, welcome depth and selected/focus
  indicators. No table/log decoration, invented success or route/permission change.
  Theme library version becomes 1.2.0; default logo points at approved PNG.
- Email: inspected existing OutreachMailer/StagingReviewEmail. Logo, colors, CTA
  and dynamic content already align; **renderer and template code unchanged**.
  No new shell, transport, fixture family or notification migration.

## What did not change

No page content/offer rewrite, workflow redesign, route replacement, database
migration, permissions, payment authorization or customer project styling.
$199 first year / roughly 55¢ per day comparison / $9.99 monthly hosting after
year one plus separately priced domain renewal remain existing terms; 55¢ is
not a daily charge. No source change to `frontend/src/pages` or customer showcase
files. No new PWA/offline behavior; manifest uses browser display mode.
Earlier live logo/email releases remain live history, not evidence this pass shipped.

## Logo / favicon migration

The original 2172×724 PNG is byte-identical at:

- `frontend/public/brand/famtastic-designs-logo-v1.png`
- `docs/design/assets/famtastic-designs-logo-v1.png`
- `backend/web/themes/custom/famtastic_admin/famtastic-designs-logo-v1.png`

SHA-256: `ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
Crown source/color extraction: `frontend/public/brand/famtastic-crown-master.png`.
Shared flat silhouette: `frontend/public/brand/famtastic-crown-flat.png`.
Generated Drupal copies are in `backend/web/themes/custom/famtastic_admin/brand/`.
Provenance: `crown-derivation.json`; extraction tool: `scripts/derive-brand-crown.cjs`.
No AI call, redraw, vector trace or alteration of the original logo.

Family in frontend/public (and theme-local copies): favicon.ico, favicon.svg,
favicon-16x16.png, favicon-32x32.png, favicon-48x48.png, apple-touch-icon.png,
android-chrome-192x192.png, android-chrome-512x512.png.
The supplied raster has a translucent textured crown. Flat derivatives retain
substantial connected source pixels, remove detached glow and compensate stroke
weight at 16/32px. This optical derivative needs owner visual review.
SVG is an explicitly documented 32px embedded-raster container, **not a vector
master**. HTML uses PNG/ICO. Large icons use subtle glow; small icons do not.
Agency Drupal attachments replace existing icon links only for famtastic_admin
and famtastic_customer and preserve canonical links/theme cache contexts.

Legacy `famtastic-mark.svg`, old theme logo.svg and historical campaign artwork
are retained rather than silently overwriting editorial/client identities.
Earlier AI favicon concepts are not canonical inputs. Inventory is in
`BRAND-DNA-AUDIT-2026-09-17.md` and `../brand/logo-migration.md`.

## Before/after and local preview

Open `http://127.0.0.1:8765/brand-dna/index.html`.
Working public app: `http://127.0.0.1:4187/`.
Icon size sheet: `http://127.0.0.1:8765/brand-dna/favicon-qa.html`.
Existing email preview: `http://127.0.0.1:8765/`.

Evidence lives in ignored `.local-email-preview/brand-dna/`:

- `before-` / `after-home-desktop.png`, `home-mobile.png`, `work.png`, `offer.png`:
  actual Vite public pages, with public Drupal content fetched read-only. Captured
  headings/routes/overflow/errors match before and after. Async fallback copy can
  briefly appear before CMS data settles; capture script waits for settled data.
- Before/after `portal-dashboard`, `portal-proof`, `portal-success`,
  `admin-dashboard`, `admin-dense`, `admin-success`: **labelled source-CSS fixtures**,
  baseline/current styling on identical illustrative markup. Not authenticated
  accounts, proof approvals or real launches. Admin fixtures use public read-only
  Claro CSS because local Drupal core/vendor is absent. They are representative
  presentation evidence, not pixel-identical production route snapshots.
- `favicon-qa.png`: 512/192/180/48/32/16 actual CSS sizes and light surroundings.
- `browser-tab.png`: real native Chrome tab with lime crown visible. Automation
  badges can mask claimed-tab icons, so native unclaimed-tab proof was used.
- `before.json`, `after.json`, `fixture-results.json`: local checks.

To reproduce after installing existing lockfile dependencies, use Node 22 and:

```sh
# Terminal 1, from frontend (read-only public CMS proxy; do not submit live forms):
VITE_DRUPAL_PROXY_TARGET=https://famtasticdesigns.com/web npm run dev -- --host 127.0.0.1 --port 4187
# Terminal 2, from repository root:
python3 -m http.server 8765 --bind 127.0.0.1 --directory .local-email-preview
# Terminal 3, from repository root:
node scripts/sync-brand-assets.cjs --check
node scripts/capture-brand-dna.cjs after
node scripts/review-brand-dna.cjs
php scripts/email-preview/render.php
php scripts/email-preview/test.php
```

Baseline public captures were taken before editing; do not overwrite `before`
with current code. A new baseline capture requires the recorded baseline checkout
in an isolated worktree. Scripts intentionally leave customer mail and DB untouched.

## QA results and limitations

- Node22 production build + SEO shells: PASS. Existing >500kB chunk warning remains.
- Public original-logo hash, 320/390/768/960/1100/1440 responsive widths, navigation,
  drawer/menu placement, footer and page errors: PASS (`test-website-logo.cjs`).
- Actual portal-nav component fixtures at 320/390/768/1280; close/Escape, aspect
  ratio and overflow: PASS (`test-portal-logo.cjs`).
- Portal Design DNA: 34/34; admin theme contract: PASS.
- Existing mocked portal inbox Playwright: 11 passed, 1 intentional mobile skip.
  These intercept customer APIs; no real message or read receipt was sent.
- Static customer-room alignment: 8/8 passed on public static server port4188.
  Initial run against Vite returned SPA fallback instead of directory indexes
  and failed 8 cases; corrected test environment, **no customer files changed**.
- `validate-portal-fulfillment-truth.mjs`: FAIL, pre-existing text expectation at
  line29 (`/still required/` versus `Domain purchase or connection remains an
  operator step`). Both test and source match baseline. Not changed in this pass.
- 12 CSS fixture captures: no horizontal overflow. Six PNG dimensions/HTTP/copies,
  ICO directory, manifest and HTML icon links: PASS.
- PHP metadata stub-contract test and module syntax: PASS. Not Drupal integration.
- Email: 35 pure presentation assertions, four widths, images disabled, escaping,
  safe CTA, plain text and previous-template byte comparisons: PASS. Browser
  preview also checked existing staging footnote at three widths; no customer edit.
- Core contrast pairs >=4.5:1, keyboard outline, reduced-motion transition, >=44px
  primary CTA and decorative crown semantics: PASS. Not a complete WCAG audit.
- No standalone lint/typecheck scripts are configured. Full backend PHPUnit and
  authenticated Drupal metadata/route tests were not run without backend/vendor.
  Full production customer lifecycle and Gmail/Outlook/Apple Mail testing not run.
- `git diff --check`: PASS. No claim that the entire repository test suite is green.

## Performance

Final minified JS 835.91kB (gzip243.70kB), CSS175.77kB (gzip33.83kB).
Prior recorded build was JS835.25kB/CSS172.98kB: approximately +0.66kB JS and
+2.79kB CSS, plus shared tokens2477B (gzip1002B) and crown6045B.
ICO4839B; optional SVG2118B. Largest app icon85271B is not a page background.
No new package, font request, heavy texture image or animation loop. Existing
logo weight and large bundle warning remain. This is asset accounting, not a
Lighthouse or field-performance guarantee.

## Recommended next step / approval

Fritz: review crown optical weight and the restrained material layer in the gallery.
After visual approval, run a separately authorized Drupal staging integration
check (including duplicate favicon absence), authenticated portal/admin acceptance
and release checklist. This branch deliberately does not deploy or merge.

Complete changed-file inventory: `BRAND-DNA-CHANGED-FILES-2026-09-17.md`.
