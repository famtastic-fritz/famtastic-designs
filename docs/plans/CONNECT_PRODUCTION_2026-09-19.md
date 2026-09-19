# FAMtastic Connect production migration

## Purpose
Move the owner's finished digital card onto the existing agency website.

## Goal

Publish the preserved card at https://famtasticdesigns.com/connect/ with a working
QR screen, production QR downloads, safe installed launch and verified assets.

## Tasks
- [x] Inspect current instructions, architecture, Git state and deployment gates.
- [x] Download the HTML and all 16 named assets; inspect types and source behavior.
- [x] Integrate static /connect/, launch safeguards and production QR codes.
- [x] Verify build, mobile interactions, install fallback, QR decoding and assets.
- [x] Merge current reviewed source to main and run canonical preflight/apply.
- [x] Verify production, record receipts and mirror the completed summary.

## Status
Production verified. Publication explicitly authorized by the current task.

## Started
2026-09-19

## Ended
2026-09-19T23:56:54.645225+00:00

## Execution
- Repository: famtastic-fritz/famtastic-designs.
- Branch: codex/connect-production.
- Worktree: /Users/famtastic-fritz/Development/worktrees/fd-connect-production.
- Baseline: b4e5c1a2; source checkout's unrelated changes remain untouched.
- Existing production ca42d7db is an ancestor; intervening main changes are docs only.
- Landing: normal GitHub PR integration, then exact clean main SHA through
  scripts/deploy-frontend-godaddy.sh (preflight, then --apply).
- Public source: frontend/public/connect; Vite copies it into dist/connect.

## Research
Source: https://famtastic-connect.nineoo.chatgpt.site/ and /#qr.
Source hashes/types: docs/evidence/connect-production/source-downloads.json.
The current source uses a relative manifest identity/start/scope and no service
worker. The fetched HTML includes a host-injected Cloudflare challenge script,
which is not card functionality and must not be copied to the new host.
The black screen with a green F resembles the supplied app icon/splash, whereas
the authored intro displays the full logo. This is a hypothesis, not a diagnosis:
the affected Android device, installed package and launch logs are unavailable.

Primary implementation references:
- https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/start_url
- https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/id
- https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Trigger_install_prompt
- https://developer.chrome.com/blog/update-install-criteria

## Review
Preserve original design, animation, movie, contact bytes and required destinations.
Set explicit /connect/ manifest URLs. Skip the intro in installed mode and avoid
making the underlying card inert; add a CSS timeout independent of JavaScript.
Retain native prompt handling and manual instructions. No service worker or
offline promise is introduced. Existing installs and downloaded old QR images
remain tied to the old origin; migration cannot update those artifacts.

## Skills
No generation skill needed: reuse the supplied finished implementation and media.

## Proof
Local proof passed:
- Node 22.23.2 build, npm audit (zero vulnerabilities), 16 Node contracts,
  shared-brand check and git diff --check.
- CUA at 320×568 and 390×844: card and QR fit without horizontal overflow;
  commercial plays unmuted (H.264/AAC, 30.041667 seconds), closes and pauses;
  native contact download fires; required links remain unchanged; QR image tap,
  Open this card, direct #qr and install manual fallback work. No console errors.
- A separate local interruption fixture exposes the intro with both scripts
  absent. After the CSS deadline, visibility:hidden and pointer-events:none
  allow the Website link to navigate; the underlying main never becomes inert.
- Installed/reduced-motion launch and native prompt accept/cancel/error behavior
  are deterministic simulations, not real Android installation tests.
- PNG and independently rasterized SVG decode exactly to the production URL.
  Recipe: qrcode 8.2, version 4/M, 4-module border, 24px modules; white-background
  SvgPathFillImage. Decode: zxing-cpp 2.3.0; SVG raster: CairoSVG 2.8.2.
- Every local asset matches generated build bytes. Vite preview omits the VCF
  MIME header; the scoped Apache config explicitly sets text/vcard in production.

Production receipts: `docs/evidence/connect-production/release.json`, HTTP manifests,
live QR decodes and browser-results.json. Android installation requires device-owner
confirmation and remains distinct from browser/manifest/launch verification.

Release packaging check caught the repository-wide `*.mp4` ignore rule before
merge. Added an exact exception for this supplied commercial and a regression
that requires every requested source file to be tracked for server builds.

## Production release and remaining limitation

PR 34 merged as `3f5c847482aa3e727ef9e38ed709e8e36c8c9d97`. The canonical script
published that exact SHA at **2026-09-19T23:51:17Z**, using Node 22.23.2. All
219 route shells and the root .htaccess were verified. Backup path is in release.json.

The first private checkout exhausted the account quota before promotion. Two
obsolete clean 1GB release checkouts were inspected (including ignored files),
confirmed recoverable from Git and removed with `git worktree remove` without
force. This reclaimed 2,149,820 KiB. Production, recent releases, source history,
all backups and parent release records were retained; the same-commit retry passed.

Live acceptance: all 17 requested files on apex and www have exact build bytes
and correct MIME; slashless /connect redirects 301, including preservation of #qr;
missing assets return 404; video range is 206 with exact bytes; VCF is text/vcard
with an attachment filename. Both downloaded QR formats decode to the exact
production URL. CUA completed the unmuted 30.041667-second commercial, restarted
it and proved closing pauses playback, fired a contact download, exercised the
QR image and Open this card button, checked manual installation, and navigated
the offer/contact destinations. The contact anchor exists. Apex/www main sites
render populated roots and headings without console errors; their JS/CSS MIME
checks pass. Source, media, contact details and existing asset directories remain.

GitHub Actions jobs did not start because of an account billing lock. Branch
protection and ruleset inspection found no required check/review gate; no admin
bypass was used. The relevant local checks passed before normal PR integration.

**Android:** No physical Android installation or installed-package launch was
performed. Installed/reduced-motion behavior and native prompt transitions were
simulated, and the real manifest launch URL was browser-verified. The reported
old green-F black screen is not diagnosed or claimed fixed. Add the new card
from the production URL and confirm installation on the device. An old-origin
installed icon and previously downloaded QR images keep their old destination.
No service worker, offline support, email send or account change was introduced.

## Post-evaluation

Keep immutable asset receipt checks and tracked-file checks in subpath migrations:
a global media ignore can pass local playback yet omit the server release. Keep
per-account quota distinct from filesystem free space and remove only verified
reproducible old checkouts when recovering release capacity. Future improvement:
bounded private-release retention and a quota-aware preflight; not changed here.

## Records and mirror

Release evidence and current capability/learning/changelog surfaces are updated.
The Connect regression suite is included in the existing frontend CI job for
when GitHub's billing lock is resolved. No runtime code changed after release.
A dated copy of this report is written to the mounted FAMtastic Drive folder as
`2026-09-19-FAMtastic-Connect-Live.md`, with exact local readback verification.
