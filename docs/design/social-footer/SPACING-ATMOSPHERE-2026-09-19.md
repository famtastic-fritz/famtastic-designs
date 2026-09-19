# Mobile spacing and quiet footer atmosphere

Owner supplied three phone screenshots showing overly tall Company navigation
and requested subtle cursive background activity plus a characterized Fritz.

## Scope and status

Owner authorized production release with “surprise me ... approved to push live”.
Release candidate verified locally; live receipt will follow. Existing public SiteFooter only. All CMS limits,
destinations, official social marks, logo bytes, analytics and legal copy remain.
Company changes from one long list to two columns at<=599px; email spans both.
44px targets remain, inter-row gaps reduce to.15rem, section spacing reduces,
mobile brand logo is240px. No layout clipping or removal of real links.

Separate aria-hidden/noninteractive background uses the already self-hosted
heading script for two short existing brand phrases. CSS-only24s opacity cycle
at1.8–6.5%; pause/resume control; reduced-motion renders static3.5% with no toggle.
Platform marks remain static. No new font, asset, tracker or animation dependency.

Owner cancelled the character. CSS-only red/gold/blue light pools now sit behind
the content (maximum color alpha 7.5%, 32s opacity cycle). Pause includes the
pseudo-element; reduced-motion is static. No portrait or extra image is used.

## Checks

- Six focused social/React rendering/atmosphere contracts pass under Node22.
- CUA actual SiteFooter review with sample CMS data at390px: Company210.375px;
  all six links44px; no horizontal overflow; correct signature font loaded.
- Pause button changed both computed animation states from running to paused.
-320px screenshot confirmed wrapping and visible keyboard focus. No production
  delivery or complete real-CMS journey is claimed from this local fixture.
- Node22 production build and canonical brand-asset check passed. Existing large
  bundle warning remains; no performance score is claimed. Desktop CUA also checked.
- Local preview: `npm --prefix frontend run dev -- --host 127.0.0.1 --port 4296`;
  open `/.social-footer-review/` (fixture CMS data, not live data).

Final candidate: six tests, Node22 build, exact brand check and diff check passed.
390px CUA screenshot: no horizontal overflow; six Company targets remain44px;
pause changes the ambient pseudo-element's computed animation state to paused.
Existing large-bundle warning remains. No backend/mail/customer-site changes.
