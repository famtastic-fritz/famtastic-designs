# Connect QR discovery

Purpose: Make in-person sharing easy to find on the existing digital card.

Goal

Add an always-available SHOW MY QR action, a short pointer cue, and an accessible scan sheet; publish the tested change without editing QR artwork, which Fritz assigned to ChatWeb.

Tasks
- [x] Re-anchor current main, live page, existing card behavior, and release contract.
- [x] Implement the visible action, bounded cue, repeat-visit behavior, and scan sheet.
- [x] Run existing contracts, interaction tests, production build, and responsive browser review.
- [ ] Commit, push, merge, deploy through the canonical script, and verify both hostnames.
- [ ] Record evidence, lessons, post-evaluation, and Drive mirror.

Status: release_ready
Started: 2026-09-21
Ended: pending
Execution: `codex/connect-qr-discovery`, `/Users/famtastic-fritz/Development/worktrees/fd-connect-qr-discovery`, based on `19ad376a`; integrate to main before the owner-authorized production release. A sparse checkout avoids duplicating unrelated marketing media on the nearly full local disk.
Research: Existing `/connect/` is a static card under `frontend/public/connect/`; it already has a native QR dialog and footer icon. The intro lasts 2.3 seconds, so the 1.25-second cue delay begins once the card is revealed. Native dialog semantics follow https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog and reduced motion follows https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion .
Review: Preserve all card destinations, approved logo/icons, video, vCard, install flow, and QR file bytes. No QR generation/repair or scan claim belongs to this task. Browser proof distinguishes local and live.
Skills: No specialist skill required for this scoped maintenance change; existing repository and browser workflows reused.

Proof

Local proof: 23 Node contracts, 12 Chromium browser cases, public-flow checks, audit, build, and diff check pass. Screenshots, QR preservation hashes, and the detailed review are in `docs/evidence/connect-qr-discovery/`. Production release pending.

## Reusable implementation prompt

Update only the existing FAMtastic `/connect` experience. Keep its approved design, links, video, contact download, Home Screen support, and QR artwork intact; ChatWeb owns QR correction. Add a prominent SHOW MY QR button near the top and retain the footer QR shortcut. About 1.25 seconds after the card becomes visible, show “Need to share this? Tap here” with a lime pointer directed at the button. Bounce it three times, then settle; remember that the hint was seen and keep repeat visits and reduced-motion visits static. The action must work immediately. Open the existing native dialog as a large responsive scan sheet with SCAN TO CONNECT, “Point another phone at this QR code.”, “Scan to connect with Fritz”, an obvious Close button, and Share Link with a truthful copy fallback. Preserve Escape, backdrop dismissal, focus containment/restoration, and #qr/back behavior. Add interaction tests and responsive screenshots, verify QR artwork bytes are untouched, run the existing tests/build, and publish through the repository's authorized release path.
