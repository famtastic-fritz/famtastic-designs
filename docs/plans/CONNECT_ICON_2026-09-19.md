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
Publication uses the existing clean-main GitHub PR and canonical frontend
preflight/apply process. Live receipt will be recorded after actual verification.

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
