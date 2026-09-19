# Compact social footer — local review receipt

September 19, 2026. Branch `famtastic/social-footer-v1`, based on current fetched
`origin/main` at `ceee698a189d7fefe0661ba4a345907573fa253b`. Not pushed, merged or deployed.
Other checkout/campaign work is untouched. Owner visual review remains pending.

## Result

The actual `SiteFooter` uses one refactored `SocialSignal`, reusable `SocialBadge`,
scoped `social-footer.css`, and one profile source in `frontend/src/lib/socialProfiles.js`.
The canonical logo/crown, live CMS Services (six-item limit), Packages (seven-item
limit), company/contact links, legal links, dynamic year and signature remain.
Existing page finales/CTAs are unchanged; no extra CTA or footer was introduced.

Enabled: Facebook, Instagram, YouTube, X, TikTok, Email. Hidden: LinkedIn, Pinterest,
Discord, RSS. YouTube uses the newer owner-confirmed Brand Account; X's personal
channel is visibly labeled. See SOURCES.md for exact provenance and rights limits.

## Measured social module height

Same actual footer component, identical labeled sample CMS props; CUA in-app Chromium.
Height excludes surrounding footer navigation. No fixed-height clipping.

| Viewport | Before | After |
| --- | ---: | ---: |
| 1440 | 368.00px | 157.45px |
| 1024 | 348.14px | 149.45px |
| 768 | 325.11px | 149.45px |
| 390 | 411.11px | 246.00px |
| 320 | 437.14px | 254.30px |

Desktop reduction: 57.2%. At 390px four cells occupy the first row; at 320px three.
All five widths had document scroll width <= viewport width and social targets
at least 44px in both dimensions. Platform labels are HTML, never image text.

## Footprint

Node 22.23.2, same npm lockfile/dependencies, production build. Byte sums across
built JS/CSS; gzip uses Python gzip level 9 consistently for before/after.

| Artifact | Before raw / gzip | After raw / gzip | Delta raw / gzip |
| --- | ---: | ---: | ---: |
| JS | 864,120 / 247,493 B | 866,667 / 248,425 B | +2,547 / +932 B |
| CSS | 199,370 / 37,732 B | 199,998 / 37,871 B | +628 / +139 B |
| Eight platform files | 0 | 43,923 / 42,753 B | +43,923 / +42,753 B |

Email/RSS vectors are in JS. Only active marks are requested by the footer; dormant
assets are available for future use and the local gallery. Reference boards total
4,062,615 bytes under docs and are absent from dist. No dependency or font additions.

## Executed verification

- `node --test frontend/scripts/test-social-footer.mjs frontend/scripts/test-google-analytics-redaction.mjs`: 7/7 pass. Actual React SSR covers CMS limits/fallbacks, exact URLs, safe external attributes, hidden/unsafe entries, labels, canonical logo, dynamic year and preview semantics. Event tests exercise missing and throwing analytics.
- `node scripts/test-creator-credit.mjs`: 5/5 pass.
- `npm --prefix frontend run test:public-flow`: pass.
- `node scripts/sync-brand-assets.cjs --check`: pass, canonical shared assets unchanged.
- `npm --prefix frontend run build`: pass, including creator-credit inventory of 218 HTML outputs. Existing >500KB main-chunk warning remains. No lint script exists.
- `git diff --check`: pass.
- CUA actual homepage at `/`: one footer and one social module at desktop/mobile; page CTA and existing final creator credit remain. Local Drupal is unavailable, so real CMS retrieval was not proven. Labeled fixture props plus actual empty-data rendering cover the footer's data contract without invented live data.
- CUA real keyboard Tab focus: 2px lime outline, platform mark transform `none`.
- CUA real pointer click: only the interacted Facebook edge glows; other five edges have no shadow. One custom event plus one GA event, native destination opens immediately.
- CUA Enter with throwing analytics: Instagram destination still opens; one event of each kind. Middle-click with missing analytics: YouTube destination opens and exactly one custom event is recorded.
- Reduced-motion rule simulation: exact source media-rule body applied in local harness, all twelve active housing/edge transitions `0s`; outline remains. Source media query verified. OS preference emulation was not available in CUA.
- 200% CSS zoom reflow: document width 1425px in 1440px viewport, no overflow. This is CSS zoom, not browser-UI zoom.
- Pressed treatment exists and appears in labeled static gallery references; no held-pointer-down screenshot. No physical touch-device or Safari/Firefox testing and no full WCAG audit claimed.
- No hover handler, network call, timer or success-state logic exists in the component. Real link semantics retain browser modifier/context-menu behavior; no navigation interception.

## Local review artifacts

Ignored screenshots/logs are retained at repository `.artifacts/social-footer/`:
`before-{1440,1024,768,390,320}.png`, `after-{1440,1024,768,390,320}.png`,
`focus-desktop.png`, `hover-desktop.png`, `badge-focus-closeup.png`,
`reduced-motion-focus.png`, `zoom-200.png`, `gallery.png`,
`actual-home-footer-1440.png`, `actual-home-footer-390.png`.
JSON measurements and build/test logs are alongside them. Portable numeric QA and
hashes are in `docs/evidence/social-footer/`; refs are under this directory.

## Exact preview

```
cd /Users/famtastic-fritz/Development/FAMtastic/worktrees/social-footer-v1
fnm exec --using=22 npm --prefix frontend run dev -- --host 127.0.0.1 --port 5196
```

The server is already running. Open http://127.0.0.1:5196/ for the actual app,
http://127.0.0.1:5196/.social-footer-review/index.html for the integrated footer
with labeled CMS fixture data, or append `?gallery=1` for the all-platform gallery.
On another machine, use Node 22, `npm --prefix frontend ci`, then the npm command.

The gallery has no production router entry, is not linked from the app and is absent
from the built site. Preview-only unknown accounts never become public links.
