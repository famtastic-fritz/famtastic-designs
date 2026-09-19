# Social footer sources — September 19, 2026

Local implementation, visual review pending. No platform endorsement is claimed.
The handoff boards are composition references, not platform masters. Actual downloaded
platform assets are used without shape, color, rotation, shadow or filter changes.
`assets.json` records original member hashes, processing and final hashes. Runtime
files are same-origin; no logo request goes to a platform. Email/RSS are original
conventional utility symbols, not third-party trademarks.

| Platform | Official source reviewed / acquisition | Implementation notes |
| --- | --- | --- |
| Facebook | https://www.meta.com/brand/resources/facebook/logo/ — Facebook Brand Asset Pack, primary PNG | Complete blue circle and white f. Guideline specifies 16px minimum and quarter-width clear space. Effects belong to separate housing. |
| Instagram | https://www.meta.com/brand/resources/instagram/instagram-brand/ — IG_brand_asset_pack_2023.zip, Gradient Glyph PNG | Use official gradient glyph. Original gradient SVG contains a ~10MB embedded raster; use the pack's PNG at 128px instead. |
| YouTube | https://brand.youtube/youtube-icon/ — https://www.gstatic.com/marketing-cms/78/29/3e68a1414bb28d0b7e47b44c3c91/youtube-icon.zip | Official red digital PNG. Trimmed transparent export padding only, then proportional resize. Native triangle/shape untouched; surrounding face supplies clear space. |
| X | https://about.x.com/en/who-we-are/brand-toolkit — https://about.x.com/content/dam/about-twitter/x/brand-toolkit/x-logo.zip | Official white PNG. No modified geometry. |
| TikTok | https://developers.tiktok.com/docs/en/getting-started-design-guidelines — https://sf16-va.tiktokcdn.com/obj/eden-va2/uvzhqeh7nuhd/tt4d/logo-pack.zip | Official black square icon, unchanged except proportional resize. Developer guidance states prior written permission is required; this task does not establish that permission. Owner release review must resolve applicable use rights. Old /about/brand-guidelines endpoint returned not-found. |
| LinkedIn | https://brand.linkedin.com/downloads — https://content.linkedin.com/content/dam/me/business/en-us/amp/xbu/linkedin-revised-brand-guidelines/logos/in-logo.zip | Official LI-In-Bug PNG. Hidden publicly; minimum-size/usage review required when an approved account is supplied. |
| Pinterest | https://business.pinterest.com/en-in/brand-guidelines/ — linked official primary_red_logo.jpg from images.ctfassets.net | Preserve complete badge including official white backing in preview. Public use remains disabled. Guideline requires profile context/CTA; never infer an account. |
| Discord | https://discord.com/branding — https://cdn.prod.website-files.com/6257adef93867e50d84d30e2/66e3d7f4ef6498ac018f2c55_Symbol.svg | Byte-identical official symbol SVG. Hidden publicly, visible Discord label in local gallery. |

These are trademark assets subject to their respective owner guidelines, not an
open-source icon library license. The manifest documents source, not platform approval.
No mark is traced from a generated board. Tiny per-icon crowns are omitted to protect
clear space; the preserved canonical footer logo already supplies the crown signature.

## Destination reconciliation

- Facebook and Instagram are exact existing `SocialSignal.jsx` destinations.
- X remains Fritz Medine's personal channel, labeled both visually and accessibly.
- TikTok is the documented `@famtasticdesigns8`; Email remains contact mailto.
- Current `origin/main` still documented temporary YouTube `@nineoo1`, but the existing
  local `feat/youtube-brand-channel-activation` at `ee9edf9e` has the owner-confirmed
  `https://youtube.com/@FAMtastic-Designs` successor (Brand Account). The scope-specific
  BRAND.md reconciliation carries that fact without cherry-picking campaign work or
  overwriting September 17 canonical-logo guidance. No current publishing claim.
- LinkedIn, Pinterest, Discord and RSS have no destination and stay hidden.

## References and review boundaries

Owner-approved PNG boards: `references/footer-approved.png` and
`references/social-icons-approved.png`, retained with docs, excluded from runtime.
Source package: `FAMtastic-Social-Footer-Codex-Handoff.zip`; extracted copy remains in
`/Users/famtastic-fritz/Documents/ChatGPT/FAMtasticDesigns/FAMtastic-Social-Footer-Codex-Handoff/`.
Third-party mockup testimonials, invented accounts, newsletter, dated copyright,
fake follow success, sticky dock and extra CTA were explicitly excluded by the owner.
