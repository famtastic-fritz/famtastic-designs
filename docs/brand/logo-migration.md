# Logo migration inventory and first website slice

Status: first website slice is **production-deployed and browser-verified** at
`145b0d4a673b9181214c52210a06fe6f8128b130`, September 17, 2026 14:35:12 UTC.
Both apex and www passed all six viewport checks. Approved email/hosted PNG already live at
`20448c71`; exact send and customer footer evidence are in
`docs/design/EMAIL_BRAND_RELEASE_2026-09-17.md`.

| Surface/source | Decision |
| --- | --- |
| Public SiteNavbar, desktop/mobile | Replace CSS text wordmark with shared original PNG component; preserve nav/auth/actions. Larger header and matching mobile-menu offset. |
| Public SiteFooter | Replace text wordmark with same component/home link; preserve footer navigation/content. |
| frontend/index.html Organization logo | Point to canonical hosted PNG. |
| frontend/scripts/generate-seo-shells.mjs | Use shared brand constant and intrinsic dimensions for all generated route metadata. |
| New staging-ready email | Already live; existing PHP renderer, original PNG, exact approved copy. |
| Other transactional emails | Unchanged; need template-specific versioning/review, not mass replacement. |
| PortalNav and legacy ClientPortalPage | Original PNG in sidebar, mobile menu bar and token-preserving project link. Owner approved deployment; release receipt follows below. |
| Drupal admin shell Twig/theme | Original byte-identical theme-local PNG in admin shell and login/recovery. Library version bumped for CSS cache invalidation. Release receipt follows below. |
| BlogPostPage, WatchFilmPage, blogArt.js, filmLibrary.js | 28/32px legacy compact mark retained; full horizontal master would be unreadable/distorted here. |
| FiftyFiveCentWebsitePage | Existing 52px compact campaign mark retained for a separate composition pass. Pricing/content unchanged. |
| SocialSignal | Existing FAM graphic is a dedicated social-composition element, not blindly replaced. |
| /brand/famtastic-mark.svg | Retain file for known compact/historical callers; no longer the primary Organization logo. |
| og-image.jpg / existing social previews | Retain composed cover, not replace with a raw wide logo. Future reviewed social composition. |
| favicon/app-icon | Owner shortlisted FAM+crown, red F+crown, crown-only concepts. Local 16/32px comparison only; no live choice approved. |

No customer identity is replaced with agency branding. No prices, claims, hero
copy, links, forms, account permissions or commerce behavior are changed.
Existing `--v1-*` and portal tokens remain authoritative; no competing token system.

## Local preview

From repository root:

```sh
VITE_DRUPAL_PROXY_TARGET=https://famtasticdesigns.com/web npm --prefix frontend run dev -- --host 127.0.0.1 --port 4187
```

Open http://127.0.0.1:4187/. Public content comes from read-only Drupal requests;
do not submit forms or log into production for layout testing. Use pinned Node 22.
Tests/screenshots: `node scripts/test-website-logo.cjs`. Build: `npm --prefix frontend run build`.
The script tests public presentation only, not authenticated portal/admin behavior.

## First-pass validation — September 17

- Original asset SHA preserved, all logo dimensions remain 3:1.
- Public browser QA: 320/390/768/960/1100/1440px; no horizontal overflow,
  home links intact, desktop navigation visible at the new breakpoint, mobile
  menus open/close below the taller header, footer logo loads, no uncaught errors.
- Node 22 production build passes; 170 generated Organization schema entries
  reference the canonical PNG. Existing large-JavaScript-chunk warning remains.
- Existing portal Design DNA validator: 34 passed, 0 failed. This is a source
  contract check, not authenticated portal visual proof.
- Screenshots: `.local-email-preview/website-desktop.png`, `website-mobile.png`,
  `website-footer-1440.png`, `website-footer-390.png`. These are local artifacts.
- The original preview-only milestone is superseded by the production release
  above. Backend remains unchanged; no email resend or customer-site change.

## Production receipt

Normal frontend deployment script built the exact main SHA with Node 22.23.2,
verified 216 route shells and root .htaccess. Backup:
`/home/xrdj7j99xhzt/backups/famtastic-frontend-20260917T143225Z-145b0d4a673b9181214c52210a06fe6f8128b130.tgz`.
Release marker read back over SSH. Both public hosts passed 320/390/768/960/1100/1440
layout, logo, home-link and mobile-menu checks; additional browser checks found
populated React roots, rendered headings, HTTP 200 JavaScript/CSS with correct
MIME types and no console errors. Live desktop screenshot visually inspected.
Evidence: `.local-email-preview/live-apex-*` and `live-www-*` (local, ignored).
Repeat with `LOGO_TEST_URL=https://famtasticdesigns.com/ LOGO_TEST_LABEL=live-apex node scripts/test-website-logo.cjs`;
use the www URL and `live-www` label for the second host.
