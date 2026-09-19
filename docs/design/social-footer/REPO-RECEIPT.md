# Repository inspection receipt

Read-only inspection date: 2026-09-19. These are default-branch file snapshots, not a checkout of the user's local branch and not evidence of production deployment.

| File | Git blob SHA at inspection | Observed integration fact |
| --- | --- | --- |
| `frontend/src/components/Layout.jsx` | `9c33ff24e9720c1077df903e77e0518a2f99ad09` | Renders SiteFooter with Drupal-derived services and packages. |
| `frontend/src/components/v1/index.js` | `f43315921d981242725fd0b9cf3ebdcc896de4d8` | Exports SiteFooter and SocialSignal. |
| `frontend/src/components/v1/SiteFooter.jsx` | `cd5a750ff5e9edfabfa2365fcda1247a994eafa3` | Imports SocialSignal and BrandLogo; preserves live link columns, year, contact and legal. |
| `frontend/src/components/v1/SocialSignal.jsx` | `08a287e28231783fbe2e7cfed6e26b41b53990ca` | Contains two profiles, orbit/LIVE markup and existing click events. |
| `docs/design/FAMTASTIC-DESIGN-SYSTEM.md` | `76e3a249e30e7caf0ff6d9ef6da2ef83254c5fff` | Canonical assets, scoped styling, constrained motion, no fake states, existing email consumer. |
| `BRAND.md` (positioning/profile section) | `84829b4f7244d9db6c462c6c0a6b6e65d7ef7a68` | Instagram/TikTok/X/YouTube channels and canonical logo policy. |
| `frontend/src/components/Header.jsx` | `f6bad36b48804ae0e702acc8d822d46e87f9edc7` | Existing menu/auth behavior must not be altered by this footer task. |

## Source locations

- https://github.com/famtastic-fritz/famtastic-designs/blob/main/frontend/src/components/Layout.jsx
- https://github.com/famtastic-fritz/famtastic-designs/blob/main/frontend/src/components/v1/SiteFooter.jsx
- https://github.com/famtastic-fritz/famtastic-designs/blob/main/frontend/src/components/v1/SocialSignal.jsx
- https://github.com/famtastic-fritz/famtastic-designs/blob/main/docs/design/FAMTASTIC-DESIGN-SYSTEM.md
- https://github.com/famtastic-fritz/famtastic-designs/blob/main/BRAND.md

## Known event contract

`famtastic:social-click` CustomEvent detail: `{ platform, destination }`.
`social_profile_click` gtag fields: `{ social_platform, social_destination }`.
Preserve both integrations without double counting. Reinspect local consent/analytics utilities before editing.

## Limits

No repository mutation, branch creation, commit, deployment, full code checkout or running application test was performed for this handoff. Asset URLs and profile destinations were read from repo configuration/documentation; external accounts were not independently logged into or verified. Generated images are references, not approved platform marks or deployed code.
