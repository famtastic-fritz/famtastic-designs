# Logo migration inventory and first website slice

Status: first website slice is **local preview, not deployed** on
`codex/website-logo-migration`. Approved email/hosted PNG already live at
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
| PortalNav and legacy ClientPortalPage | Text wordmarks identified; retain for next authenticated-layout pass. Do not claim portal migration complete. |
| Drupal admin shell Twig/theme | Retain existing text pending authenticated admin-layout proof. |
| BlogPostPage, WatchFilmPage, blogArt.js, filmLibrary.js | 28/32px legacy compact mark retained; full horizontal master would be unreadable/distorted here. |
| FiftyFiveCentWebsitePage | Existing 52px compact campaign mark retained for a separate composition pass. Pricing/content unchanged. |
| SocialSignal | Existing FAM graphic is a dedicated social-composition element, not blindly replaced. |
| /brand/famtastic-mark.svg | Retain file for known compact/historical callers; no longer the primary Organization logo. |
| og-image.jpg / existing social previews | Retain composed cover, not replace with a raw wide logo. Future reviewed social composition. |
| favicon/app-icon | No approved compact master supplied; no invented or distorted replacement. |

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
- Website changes are not deployed; live release remains `20448c71` plus its
  subsequent documentation-only source commit. Feature branch is for review.
