# Connect app icon correction — September 19, 2026

The owner rejected the imported generic green F app icon. Replace it with the
existing canonical original-derived crown on obsidian, matching the website's
compact identity. This is a correction to the explicitly authorized Connect
production migration, not a new logo or unrelated website redesign.

## Implementation and provenance

The 192/512 Android, 180 Apple and 32 favicon PNGs are exact copies of the
existing website brand icons. Both SVGs embed canonical raster artwork; neither
claims to be a vector master. The maskable SVG places the unchanged flat crown
in a centered 280 × 283.3466 box on a 512 × 512 opaque #070907 canvas, then
CairoSVG 2.8.2 exports the PNG. Every non-background pixel lies within radius
169.06px, comfortably inside the 204.8px mask-safe circle.

All icon URLs use `?v=crown-1`; the manifest location, id, start_url and scope
remain unchanged. Existing file paths also serve the replacement crown. The
install preview, Apple metadata and browser favicon use the same family.
Media, QR codes, contact data and launch behavior are preserved. The original
source download receipt remains historical evidence; its imported icons are
explicitly superseded by this owner-requested correction.

Hashes, exact copy sources and the mask-safe measurement:
`../evidence/connect-icon-2026-09-19/provenance.json`.

## Verification and release

Local Node 22 build, all 16 existing Connect/creator-credit contracts, shared
brand parity and whitespace checks pass. At 390 × 844, CUA displays the crown
in the manual install panel with readable instructions and no console errors.
PR 36 merged as `728b00a4e21eb544126194fad10f069c3f79c1c6`. The canonical
preflight/apply process deployed that exact SHA at 2026-09-20T01:27:03Z.
Both domains serve all 17 requested files with exact build bytes and correct
MIME; all five versioned PNG URLs per host also match. Live CUA confirms the
crown install preview at 390 × 844 and 320 × 568 without overflow, usable QR
and card return, and populated apex/www main sites with no console errors.
Main-site JS/CSS MIME checks pass. Receipt: `../evidence/connect-icon-2026-09-19/release.json`.

The initial dependency install hit the account quota before promotion. A clean
obsolete private checkout at `31a92fe4` was verified against Git (including
ignored files), confirmed non-live and ancestor of main, then removed with
non-force `git worktree remove`, reclaiming 1,078,180 KiB. Git history, parent
release records, live/recent builds and backups remain. Same-SHA retry passed.
GitHub Actions could not start because of the account billing lock; no required
branch gate was bypassed. Local checks above passed.

## Installed-device limitation

Actual Android installation and the reported old-origin launch failure remain
untested. A new icon does not establish a black-screen root cause or fix.
Existing old-origin installs cannot migrate across origins. Existing production
installs may retain their cached icon until the browser applies an update;
removing the old install and adding the current production card is the direct
way to request a fresh installation. The device owner confirms installation.

References: [MDN maskable icon guidance](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Define_app_icons),
[Chrome manifest update behavior](https://web.dev/articles/manifest-updates).

## Skill

Applied repository FAMtastic Creative Studio guidance and canonical brand rules.
Reused the existing icon family; no new paid art generation or logo redraw.
