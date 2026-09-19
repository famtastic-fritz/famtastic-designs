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
- [ ] Merge current reviewed source to main and run canonical preflight/apply.
- [ ] Verify production, record receipts and mirror the completed summary.

## Status
In progress. Production publication explicitly authorized by the current task.

## Started
2026-09-19

## Ended
Pending

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
- Node 22.23.2 build, npm audit (zero vulnerabilities), 15 Node contracts,
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

Live receipts pending. Android installation requires device-owner confirmation
and remains distinct from browser/manifest/launch verification.
