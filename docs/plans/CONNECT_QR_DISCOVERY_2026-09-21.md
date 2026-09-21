# Connect QR discovery

Purpose: Make in-person sharing easy to find on the existing digital card.

Goal

Add an always-available SHOW MY QR action, a short pointer cue, and an accessible scan sheet; publish the tested change without editing QR artwork, which Fritz assigned to ChatWeb.

Tasks
- [x] Re-anchor current main, live page, existing card behavior, and release contract.
- [x] Implement the visible action, bounded cue, repeat-visit behavior, and scan sheet.
- [x] Run existing contracts, interaction tests, production build, and responsive browser review.
- [x] Commit, push, merge, deploy through the canonical script, and verify both hostnames.
- [x] Record evidence, lessons, post-evaluation, and Drive mirror.

Status: completed
Started: 2026-09-21
Ended: 2026-09-21
Execution: `codex/connect-qr-discovery`, `/Users/famtastic-fritz/Development/worktrees/fd-connect-qr-discovery`, based on `19ad376a`; integrate to main before the owner-authorized production release. A sparse checkout avoids duplicating unrelated marketing media on the nearly full local disk.
Research: Existing `/connect/` is a static card under `frontend/public/connect/`; it already has a native QR dialog and footer icon. The intro lasts 2.3 seconds, so the 1.25-second cue delay begins once the card is revealed. Native dialog semantics follow https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog and reduced motion follows https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion .
Review: Preserve all card destinations, approved logo/icons, video, vCard, install flow, and QR file bytes. No QR generation/repair or scan claim belongs to this task. Browser proof distinguishes local and live.
Skills: No specialist skill required for this scoped maintenance change; existing repository and browser workflows reused.

Proof

Local proof: 23 Node contracts, 12 Chromium browser cases, public-flow checks, audit, build, and diff check pass. Screenshots, QR preservation hashes, and the detailed review are in `docs/evidence/connect-qr-discovery/`. Production verified at `40ca506b` on both hostnames; see `docs/evidence/connect-qr-discovery/release.json`.

## Reusable implementation prompt

Update only the existing FAMtastic `/connect` experience. Keep its approved design, links, video, contact download, Home Screen support, and QR artwork intact; ChatWeb owns QR correction. Add a prominent SHOW MY QR button near the top and retain the footer QR shortcut. About 1.25 seconds after the card becomes visible, show “Need to share this? Tap here” with a lime pointer directed at the button. Bounce it three times, then settle; remember that the hint was seen and keep repeat visits and reduced-motion visits static. The action must work immediately. Open the existing native dialog as a large responsive scan sheet with SCAN TO CONNECT, “Point another phone at this QR code.”, “Scan to connect with Fritz”, an obvious Close button, and Share Link with a truthful copy fallback. Preserve Escape, backdrop dismissal, focus containment/restoration, and #qr/back behavior. Add interaction tests and responsive screenshots, verify QR artwork bytes are untouched, run the existing tests/build, and publish through the repository's authorized release path.


## Production release

PR 40 merged and the canonical release script deployed `40ca506b693e75b02e004326158ca772b1a25886` with Node 22.23.2. Both domains serve the exact HTML, JS, CSS, manifest, install code, and unchanged QR bytes. Slash redirects and main-site JS/CSS MIME checks pass. Live acceptance covers all six scenarios on desktop and mobile for each hostname; a stalled www desktop browser session was stopped and its complete six-case project passed on rerun. Both main sites render real content and headings with zero captured console/page errors.

Local verification: 23 Node contracts, 12 browser cases, public-flow checks, npm audit (zero vulnerabilities), production build, Build DNA and diff checks. GitHub Actions jobs did not start because the account is billing-locked; local results are not represented as hosted CI passes. No required branch protection/rules existed and no admin override was used.

The first full server checkout hit hosting quota before touching production. Git removed its incomplete worktree. Prepared only the exact frontend build inputs in a clean sparse private worktree (225652 KiB), then reran the unchanged release script successfully. No old releases or backups were deleted. The backup path and exact release marker are in `release.json`.

Changed product files: `frontend/public/connect/index.html`, `app.js`, `style.css`. Added/extended tests: `frontend/e2e/connect-qr.spec.js`, `scripts/test-connect.mjs`. Existing QR SVG/PNG, logo/icons, video, contact and install script stayed unchanged. The prompt above remains reusable; no further agent implementation is needed for these behaviors.

## Post-evaluation and mirror

The observed discoverability problem is addressed by a visible action and an arrow that points directly to it. Native dialog semantics alone did not satisfy reverse-Tab wrapping in Chrome; the browser test caught and verified the correction. Treat a storage quota failure separately from `df` or cPanel's nominal unlimited-disk label. Opportunity: a reviewed frontend-only release checkout option could avoid unrelated marketing copies; release tooling was not changed here.

Ecosystem plan audit returned clean, with zero active-plan drift. A dated status/report mirror is saved to the mounted FAMtastic Drive folder as `2026-09-21-Connect-QR-Discovery-Live.md` and verified by local readback; cloud sync delivery is not independently confirmed. QR artwork correction remains with ChatWeb, and physical scans/OS sharing/installed apps are not claimed.
