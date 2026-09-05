# Adobe suite connection map

Adobe is more than Firefly in the FAMtastic flow:

## Confirmed local desktop connections

- **Photoshop 2026 MCP:** live in Codex and Claude Code; 43 tools discovered, disposable document mutation passed, and retained before/after output proof exists.
- **Premiere Pro 2026 MCP:** live in Codex, Claude Code, and Claude Desktop; CEP bridge and Codex plugin installed; read-only verification reached Premiere Pro 26.3.2 build 2. A disposable edit/export proof remains the production gate.

The services below are a capability map, not a statement that each API is connected:

- **Creative Cloud Libraries API:** synchronize approved logos, colors, imagery,
  and reusable brand elements into the production system.
- **Adobe Express Embed SDK/API:** launch an editor from the command center,
  start from FAMtastic templates, resize/crop/trim, and return exported asset
  data to the campaign record. New integrations require Adobe approval.
- **Photoshop API:** apply PSD templates, replace smart-object content, create
  renditions, remove backgrounds, and automate repetitive image finishing.
- **Acrobat PDF Services:** generate proposals, campaign reports, lead magnets,
  customer Growth Reviews, compress/secure PDFs, and extract structured content.
  Adobe documents a free tier for PDF and Document Generation transactions.
- **Frame.io V4 API:** upload video versions, collect time-coded review feedback,
  manage approvals, and trigger downstream workflows with webhooks. It requires
  a compatible provisioned Frame.io V4 account.
- **Firefly Services:** generate approved image ingredients through a supported
  server API when Adobe grants the required credentials/license.

The marketing command center remains the workflow source of truth. Adobe tools
are providers for brand assets, creation, finishing, document generation, and
media review; Postiz handles distribution and GA4/Drupal handle measurement and
customer lifecycle state.

The proof gallery at `marketing/adobe-pipeline/proofs/index.html` maps real
sample artifacts to the corresponding stages in the command-center mockup.

## Verification, 2026-09-04

Re-verified live. The desktop half of this map was accurate and was being
ignored anyway — see below.

- **Photoshop 2026 MCP: now production-proven, not just discovered.** A complete
  Design-DNA-compliant 9:16 story frame was composed and exported entirely
  through the bridge: `marketing/creative/adobe-proofs/cost-is-not-the-reason-story-1080x1920.png` (**copy superseded 2026-09-04 — it says the bundle is "maintained", which the catalog contradicts; kept only as the first end-to-end proof, never reuse its copy**),
  with the reusable template now living as parametrised code at `marketing/creative/templates/famtastic-social-frame.jsx`.
- **Premiere Pro 2026 MCP: still unproven.** `mcp__premiere-pro__ping` timed out
  because the application was closed. The bridge requires the app to be running;
  the disposable edit/export proof named above remains outstanding.
- **Eighteen Adobe applications are installed**, only Photoshop was running, and
  only Photoshop and Premiere have bridges. After Effects, Audition, Media
  Encoder, Illustrator, InDesign, Animate, Character Animator, Lightroom and
  Acrobat are all paid for and entirely outside agent reach.
- **Adobe Fonts has never been activated.** `~/Library/Application Support/Adobe/CoreSync/plugins/livetype/.r`
  is empty. Neither brand face — Inter or Space Grotesk — is installed on this
  machine, so Adobe-produced artwork currently substitutes Helvetica Neue
  Condensed and Avenir Next.

### Why the map being right was not enough

On 2026-09-04 a campaign video was assembled with an `ffmpeg` build lacking
`drawtext`, producing unbranded output, while Photoshop ran idle with a live
bridge. This document already said Photoshop was live. The failure was upstream:
`marketing/providers.json` — the file agents are required to read *first* —
carried seven Adobe rows and marked every one of them pending, because it
described only the Adobe **cloud APIs**. Agents correctly read "Adobe pending"
and reached for ffmpeg.

`providers.json` now carries `adobe_photoshop_desktop_mcp`,
`adobe_premiere_desktop_mcp`, `adobe_desktop_suite_unbridged` and
`adobe_fonts_typekit`, and `adobe_photoshop_api` is annotated to redirect to the
desktop path. Operating guidance lives in
`docs/playbook/RECIPES/ADOBE_CREATIVE_PRODUCTION.md`.

**Rule:** a `*_pending` status on an Adobe cloud API says nothing about the
desktop application of the same name. The desktop apps need no entitlement — the
subscription is the entitlement.
