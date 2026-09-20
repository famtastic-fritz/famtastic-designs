# Connect FAM with crown icon — September 19, 2026

The owner selected “the fam with crown” after reviewing the existing social
profile kit from the September 17 logo session. This supersedes the crown-only
Connect icon correction, while preserving that release's historical receipt.
The website's compact crown identity remains governed by its existing rules;
this selection applies to the Connect app and its install preview.

## Artwork and implementation

Use the unchanged approved `masters/fam-crown-1080.png` from
`FAMtastic-Social-Profile-Kit-2026-09-17`. The original creation session is
`deploy-pipline` (`01a0ad44-696a-7ff2-85ca-6e0f2d71e38a`); its logo work was
also organized into `FAMtastic — Design, Logo & Email Templates`.
The master is retained at `docs/design/assets/famtastic-connect-fam-crown-1080.png`.
SHA-256: `7d474e928778ef678061e48132fbe1b7c344b680caa16a78e71663b9428b12bf`.

Red F, gold A, blue M and the original lime crown are preserved. An SVG
container embeds the exact 1080 PNG on a 512 canvas; CairoSVG 2.8.2 exports
512/192 Android, 180 Apple and 32 favicon PNGs. Public SVGs embed the resulting
512 PNG; they are raster containers, not vector masters. The maskable version
places it at x/y 66 in a 380px square on opaque #070907. Every non-background
pixel is within radius 194.49px, inside the 204.8px safe circle.

All consumers use `?v=fam-crown-1`; legacy file paths also serve the selected
artwork. Manifest location, id, start URL and scope remain `/connect/`.
The card design, links, commercial, contact details, QR and launch guards are
unchanged. File hashes and measurements:
`../evidence/connect-fam-crown-2026-09-19/provenance.json`.

## Validation and release status

Local Node 22 production build, all 16 existing Connect/creator-credit
contracts and shared brand byte parity pass. CUA at 390 × 844 and 320 × 568 shows the FAM
with crown icon in the install panel, without overflow or console errors.
PR 38 merged as `957355021d094c6aaf95e7f6a8f26814699895f9`. The canonical
clean-main preflight/apply process deployed that exact SHA with Node 22.23.2
at 2026-09-20T01:52:28Z. It verified 219 route shells and retained a runtime
backup. Both domains return the exact build bytes and correct MIME for all
17 requested card files and all five versioned PNG icon URLs.

Live CUA confirms the new install-preview artwork at 390 × 844 and 320 × 568,
without horizontal overflow or console errors. The commercial plays to
30.041667 seconds and pauses on closing. The direct `#qr` screen, tapping the
QR and “Open this card” work; both downloaded QR formats decode to the exact
production card address. Slash redirect, missing-asset 404, vCard attachment,
and main-site JS/CSS types pass. Both main-site roots render their heading
and populated application without console errors.

Receipts: `../evidence/connect-fam-crown-2026-09-19/release.json`,
`browser.json`, the two HTTP inventories, versioned-icon inventories and
`qr-routing-mime.json` in that same directory.

Before deployment, a verified clean obsolete private source checkout at
`5499d202` was removed with non-force `git worktree remove` to recover
1,078,424 KiB of known account-quota pressure. Expected HEAD, no tracked,
untracked or ignored changes, ancestor-of-main and non-live guards passed;
Git history, parent release records, runtime, backups and recent builds
remain. Receipt: `quota-cleanup.txt`.

GitHub Actions did not start because of the account billing lock. There are
no required branch checks or reviews; no admin bypass was used. Local checks
and the canonical server build passed.

## Device limitation

No physical Android installation was performed. Changing the artwork does
not diagnose the original reported black-screen launch. Existing installed
icons may stay cached until their browser refreshes them; a fresh installation
from the production card requests the current artwork. The device owner must
confirm installation. Old-origin installed apps and previously downloaded
QR images do not migrate to the new origin.

## Workflow

Applied the repository Creative Studio guidance and canonical brand rules,
with the owner's explicit Connect-specific FAM with crown selection. No
new logo generation, paid provider, notification or unrelated website change.
