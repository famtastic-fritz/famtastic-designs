# Codex implementation prompt — FAMtastic social icons + compact footer

You are implementing this inside the existing FAMtastic Designs repository.
Repository: `famtastic-fritz/famtastic-designs`
Known local location: `~/famtastic/sites/site-famtastic-designs` — verify; do not create a second clone merely because your workspace differs.

## Mission and authority

Implement the owner-approved custom social-icon direction in the existing React footer. Replace the oversized orbit/social presentation with a compact, premium, responsive FAMtastic social strip. This is a footer enhancement, NOT a website redesign.

Read this handoff's `design.md`, `REPO-RECEIPT.md`, `social-profiles.reference.json`, and both images in `references/`. The images define visual direction, NOT literal business copy, approved third-party logo modifications, or production-ready individual icon files.

Do the implementation, not another proposal or picture. Produce working React/CSS, actual small-size assets, tests, screenshots and a local preview command. No deployment, merge, push, customer messaging, or new subscriptions.

## 1. Inspect and preserve the workspace

Check repository identity, branch, worktree status and existing agent instructions. Respect uncommitted work; do not reset, stash, switch away from, or overwrite another agent's changes. Use a dedicated branch `famtastic/social-footer-v1` from the appropriate current base when safe; otherwise report the exact conflict and continue nonconflicting inspection.

Read current local versions of:
- `AGENTS.md` and applicable nested instructions.
- `BRAND.md`, root `design.md`.
- `docs/design/FAMTASTIC-DESIGN-SYSTEM.md` and the Site DNA JSON it references.
- `frontend/src/components/Layout.jsx`.
- `frontend/src/components/v1/SiteFooter.jsx`.
- `frontend/src/components/v1/SocialSignal.jsx`.
- `frontend/src/components/v1/index.js`.
- Existing `BrandLogo`, `FAMCrown`, button/CTA, signature-heading and analytics utilities.
- The actual footer/social styles, route definitions, frontend package scripts and tests.

The inspected default-branch footer imports SocialSignal. Its Services and Packages columns receive Drupal-derived props. SocialSignal currently holds Instagram/Facebook URLs and click tracking. Treat these as verified starting points, not permission to overwrite a newer local implementation.

## 2. Use the exact integration seam

Replace the orbit experience at its existing footer mount; do not append a second social section.

Prefer refactoring `SocialSignal.jsx` behind its current public export, with a small reusable `SocialBadge` and one shared profile configuration. Reuse equivalent components/configuration if already present. No parallel FooterV2, second design-token system, or duplicate analytics helper.

Preserve:
- `SiteFooter({ services, packages })`, dynamic list limits, links and CMS-derived titles.
- BrandLogo and its current canonical image.
- Contact address, legal links, live year and existing footer signature.
- Routes, auth, forms, API requests, business/pricing copy and existing CTA destinations.
- Existing `famtastic:social-click` and `social_profile_click` behavior without double counting.

Remove obsolete orbit markup, its misleading LIVE status and unused orbit-specific CSS only after finding all consumers. Do not remove shared styles used elsewhere.

## 3. Match the approved visual direction — do not flatten it

The icon family is a dimensional FAMtastic badge, NOT a stock monochrome SVG in a generic circle.

Build the decorative housing from controlled layers:
1. Smoked graphite / metallic rim with a soft rounded-square silhouette.
2. Deep obsidian enamel/glass face.
3. Restrained red, gold and blue brush fragments around the rim, not over the symbol.
4. A thin lime edge accent and an interaction-only light sweep.
5. A crisp, recognizable, unmodified official platform symbol in the center.
6. An optional tiny canonical crown detail on the OUTER housing, only if clear-space and readability permit.

Custom styling belongs to the FAMtastic housing. Do not redraw, extrude, distort, rotate, recolor or add effects to third-party platform marks. Check current official asset-use rules and record sources. If a crown would invade a mark's clear space or suggest endorsement, move it to the group heading. Preserve the approved badge character through the metallic rim, brush accents and enamel depth.

Use the existing canonical lime crown. Do not replace it with the generated gold emoji-like crown or replace the master logo with one from the mockup. No image-model calls or generative redesigns.

At real footer sizes, simplify microdetail rather than shrinking the whole presentation board. Never ship the board as a background with image-map/hotspot links. Use separate assets/layers plus semantic HTML labels.

## 4. Compact footer layout

Desktop: canonical logo/brand block on the left; compact social strip at the top of the right content area; existing live footer link columns below it; current legal row across the bottom. Adapt this within the existing footer, without changing the information inventory.

Social-strip heading: `Stay connected`.
Optional short signature: `Follow the vision.`
Optional supporting line: `Ideas. Systems. Real impact.`
Use no more than one of the optional lines if space is tight.

Start at 64px badge artwork in approximately 80px-wide labeled cells, 12–16px gaps. At >=1200px viewport, the social module alone should normally stay within 120–160px height for the configured set. Do not impose fixed clipping heights or hide overflow to pass this target. Keep labels visible and readable.

Tablet/mobile: wrap naturally. Use 48–56px badges and targets of at least 44x44px. At 390px, aim for four items per row; at 320px, three is acceptable. No carousel, horizontal overflow, sticky dock, floating rail, or huge illustration replacing the orbit.

The primary `Start a Project` CTA must remain visually stronger than idle social links. Reuse an existing closing CTA rather than duplicating it. A compact CTA band is allowed only where the existing page does not already end with the equivalent action. No new campaign hero, mountain scene, testimonials, signup backend, or extra newsletter field.

## 5. Platform configuration and destination truth

Use ONE shared source of profile data. Migrate current values carefully rather than hardcoding links into JSX.

Support renderers for Facebook, Instagram, YouTube, X, TikTok, LinkedIn, Pinterest, Discord, Email and RSS. The gallery may preview every supported icon. Public footer links must have an owner-approved/configured destination.

This handoff includes reference destinations found in the repository:
- Preserve the exact Facebook Page URL already in SocialSignal.
- Preserve Instagram's existing URL.
- Use the documented YouTube `@nineoo1`, TikTok `@famtasticdesigns8` and X `@FritzMedine` only after confirming the local BRAND/config has not superseded them.
- Label X and YouTube honestly as the documented owner channels; do not invent FAMtastic-branded accounts.
- Preserve `mailto:hello@famtasticdesigns.com` as contact, not newsletter subscription.
- LinkedIn, Pinterest, Discord and RSS remain hidden publicly until real destinations are configured.

No guessed URLs, `href="#"`, dead disabled icons in production, invented handles, fake feeds, or a Blog link disguised as RSS. Existing Blogs navigation stays untouched. Do not redesign blog pages.

## 6. Interaction and accessibility

Idle: static dimensional badge, no persistent pulsing/orbits.
Hover/focus: one brief 180–280ms chrome/edge reveal; optional single <=450ms housing light sweep. Keep the platform symbol stable. Give keyboard users equivalent feedback and a real visible outline.
Press: brief restrained pressed treatment; navigate immediately. Do not delay navigation for animation.
After click: no checkmark, Followed, Connected, or Success claim. Opening a profile cannot establish a follow/subscription.

Only the interacted badge gets the stronger local highlight; never make the whole row glow. Social effects remain below the page CTA's prominence. Honor reduced motion by disabling nonessential movement/sweeps while preserving static focus and contrast. No animation dependencies, WebGL, canvas, video/GIF loops, cursor tracking or continuous timers.

Use genuine links, visible platform labels, descriptive accessible names, appropriate new-tab disclosure, and `rel="noopener noreferrer"` for external new-tab links. Email uses mailto; internal navigation uses the existing router. Decorative layers are aria-hidden and pointer-events:none. Touch navigation must work on the first tap; hover cannot be required to discover a link.

Make the new styles component-scoped. Do not globally restyle links, buttons, svg elements, headings or cards.

## 7. Analytics and safety

Preserve existing events once per activation and their platform/destination fields. Reuse the existing consent behavior. A missing/throwing analytics function must never prevent navigation. Do not add trackers, PII, session IDs, query-string scraping or fake follow conversions.

Preserve normal browser behavior: Enter, Cmd/Ctrl-click, middle click and context menus. Keep interaction logic small; do not replace links with onClick-only divs.

## 8. Documentation and Site DNA

Integrate this handoff's design.md as:
`docs/design/FAMTASTIC-SOCIAL-FOOTER.md`

Reference it from the existing official Design System/root design.md where appropriate; do not overwrite those documents. Update the existing Site DNA structure rather than creating a competing root schema.

Document the badge visual recipe, sizes, motion, visibility rules, source/licensing manifest, approved crown treatment, analytics, integration paths and how another agent adds a platform without creating a new design. Existing portal/admin/email/favicon rules remain unchanged.

Keep the approved references available to reviewers. They must not be bundled as multi-megabyte runtime images.

## 9. Test and prove the result

Run actual existing lint/test/build scripts. Add focused tests covering:
- Configured-only rendering; unknown/missing/unsafe URLs rejected or hidden.
- Existing CMS footer links retained, including empty-data behavior.
- One accessible link per enabled platform; visible labels; safe external attributes.
- Exact destination preservation; personal-channel labels not misrepresented.
- Existing events once per normal activation; navigation works without analytics.
- No fake follow/success state and no network request merely from hover.
- Keyboard access, reduced motion, touch, 200% zoom and no horizontal overflow.
- All obsolete orbit mounts removed without affecting unrelated components.

Create a LOCAL, non-public icon gallery showing all supported platforms at 48/56/64px, idle/hover/focus, reduced motion, and the actual integrated footer. Keep unconfigured platform previews non-navigable and clearly labeled Preview only. Use the existing preview/test harness; do not add a discoverable production showcase route.

Capture before/after screenshots at 1440, 1024, 768, 390 and 320px. Include a close-up of the active badge and a mobile footer. Compare to both references: this must remain dimensional, brushed, bold and recognizably FAMtastic — not just colorful generic icons.

Report measured old/new social-section heights and actual JS/CSS/image deltas. Do not claim cross-browser or accessibility testing you did not run. If blocked, say exactly what is unverified.

## 10. Finish on the branch

Commit only task-owned files when permitted by repo instructions, using a focused message such as:
`feat(footer): add FAMtastic social badge system`

Return the branch/commit, files changed, implementation paths, configured/hidden platforms, source/asset issues, screenshot locations, test results, measured footprint and exact local preview command.

Do not stop at documentation or a standalone demo: wire the result into the existing footer on the review branch. Do not push, merge or deploy. Finish ready for Fritz's visual review.
