# FAMtastic social icons and compact footer — design.md

**Version:** 1.0 · 2026-09-19
**Status:** Implementation handoff based on owner-approved visual concepts. Not a release receipt.
**Scope:** Public React footer and reusable social badge assets/components.
**Suggested repo location:** `docs/design/FAMTASTIC-SOCIAL-FOOTER.md`.

> Keep the site. Replace the oversized social presentation. Put the FAMtastic character into the icons themselves.

## 1. Authority and reference boundaries

Root `design.md` continues to govern experience, content and workflows. `BRAND.md` governs identity/voice. `docs/design/FAMTASTIC-DESIGN-SYSTEM.md` governs canonical logo/crown, materials, typography and surface intensity. This document is a scoped extension, not a replacement design system.

Read `social-footer/REPO-RECEIPT.md` for the actual default-branch files inspected. The local Codex branch may be newer; inspect it before editing.

### Visual references

- `social-footer/references/social-icons-approved.png`: the approved metal/enamel/brush badge family. Authoritative for character and relative treatment, not literal third-party mark geometry or the altered large wordmark in this generated board.
- `social-footer/references/footer-approved.png`: approved placement concept with a brand block, social row, navigation and CTA hierarchy. Not a mandate to copy its full dimensions or every illustrated block.

**Do not copy** the mockup's invented testimonials, faces, names, star ratings, newsletter form, 2024 copyright, unverified accounts, fake success state, regenerated logo, or speculative navigation. These are concept artifacts, not approved business facts or functioning features. No mountain/character scene is needed for this compact footer task.

The supplied PNGs are reference boards, not separated production icon assets. The implementation must create real layers/assets at actual UI sizes. Do not create clickable image maps over a flattened board.

## 2. The visual promise

Every icon should feel like a small object produced by FAMtastic: smoked metal, glass/enamel depth, red/gold/blue brush accents and controlled lime energy. It should still identify its destination immediately.

The shared housing is FAMtastic's visual signature. The symbol inside identifies the external platform. The two identities must remain distinct.

### Badge construction

| Layer | Treatment | Constraint |
| --- | --- | --- |
| Housing | Soft squircle/circular inset; smoked graphite metallic edge | Same family geometry across platforms; no generic flat circle substitute |
| Face | Near-black enamel with soft inner shadow and a restrained reflection | Do not obscure the symbol or add animated glare over it |
| Brush detail | Small red, gold and blue fragments confined to the outer housing | Use a deliberate brush asset or paths, not a full rainbow/conic-gradient wheel |
| Energy edge | Thin lime highlight along a short edge/arc | Quiet at rest; one local interaction highlight |
| Platform mark | Official, unchanged, sharply rendered asset | Correct colors, proportions, minimum size and clear space |
| Signature detail | Optional tiny canonical crown on the outer housing | Decorative only; never attached to/overlapping the platform mark |
| Label | Actual HTML platform name below the badge | Visible at rest; no image-baked lettering or hover-only identification |

Preserve visual depth through the housing, not by embossing or making the Facebook/YouTube/etc. marks three-dimensional. Current Meta guidance explicitly prohibits such Facebook-logo modifications. Check each platform's official rules rather than assuming all permit the same effects. Record asset URLs and relevant restrictions; do not claim platform approval.

### Crown treatment

The existing extracted crown geometry and lime color remain canonical. Generated gold crowns do not create a new master or authorize tracing a new one.

A small outer-housing signature is an optional narrow extension for this social-icon family, not permission to put crowns on all navigation. At 48px, omit it if detail gets muddy. Keep at most one *prominent* crown in the social region. If the logo is nearby, do not add another large crown just to fill space.

If official clear space cannot accommodate an icon-level crown, use a single group-level signature instead. Never suggest a platform endorses FAMtastic or that a social account is verified.

## 3. Design tokens and sizes

Reuse existing tokens. These values describe intent, not a competing source of truth:

```css
--fam-black: #070907;
--fam-surface-1: #101310;
--fam-surface-2: #141814;
--fam-border: #252b25;
--fam-lime: #7cfc00;
```

F/A/M brush colors come from existing approved assets/tokens; do not change them globally. External platform mark colors follow the corresponding official assets.

| Context | Starting artwork size | Layout guidance |
| --- | --- | --- |
| Default desktop footer | 64px | Approximately 80px cell width; 12–16px gaps |
| Compact/tablet footer | 56px | Reduce rim detail before shrinking marks |
| Mobile footer | 48–56px | 44px minimum target; labels remain visible |
| Asset/visual review | 48, 56, 64, 128px | Inspect actual-size rendering, not just enlarged previews |

Sizes are design targets, not claimed conversion findings. Expand a housing if an official mark's minimum size/clear-space rules require it. Do not enlarge the whole social section to accommodate decorative detail.

Functional typography: existing Inter/system stack. Heading treatment: existing display font. Optional `Follow the vision.` uses the existing approved signature treatment; no new font family or font request for this task. Platform labels and CTA text never use cursive.

## 4. Footer composition and space budget

### Desktop

```text
[ Existing page finale / project CTA, when present; do not duplicate ]
---------------------------------------------------------------------
[ Canonical logo + brand copy ] | STAY CONNECTED
                               | [FB] [IG] [YT] [X] [TikTok] [Email]
                               | names remain visible below badges
                               |------------------------------------
                               | SERVICES | PACKAGES | COMPANY
                               | existing live, functional link lists
---------------------------------------------------------------------
[ Current year / copyright ] [ Privacy / Terms ] [ Existing signature ]
```

At a viewport of at least 1200px, target a 120–160px-high **social module**, not a 120–160px entire footer. The footer can grow for real link content and readable wrapping. No large empty copy column, orbital diagram or 300px social-only stage.

The existing Services/Packages/Company inventory remains. CSS placement can change inside the footer, but do not shorten titles, replace live data, invent columns or remove important links to make the screenshot fit.

### Mobile and zoom

Stack the brand block, compact social group, existing link columns, then legal content. Aim for four social cells per row at 390px and three at 320px where labels fit. Let the layout wrap naturally at other widths. No horizontal-scrolling social strip, hover-dependent links, hidden overflow, fixed-height clipping or forced single-line labels.

A smaller viewport does not authorize a sticky footer dock. Existing header, menus and touch controls remain unchanged.

### CTA and attention hierarchy

Recommended priority: project-start action first; recognizable brand second; social connections third; utility links fourth. This is a design strategy to test, not a promise about eye movement or sales.

- Keep the solid lime project CTA as the strongest action; idle badges are mostly dark.
- Group social links in one predictable place instead of scattering repeated icons.
- Keep labels visible so the meaning does not depend on guessing or hover.
- Use one short invitation, not eight competing taglines.
- Give the hovered/focused link feedback, not a whole-row animation.

Visual hierarchy uses relative contrast, scale and grouping [UX-2]. Visible labels reduce icon ambiguity, especially on touch where hover labels do not help [UX-1]. This does not establish a guaranteed conversion lift; measure real clicks and project starts separately.

If the page already ends with a project CTA, omit a second footer CTA band. Reuse existing page-composition rules to decide this; do not build a duplicate panel and hide it with fragile CSS. No new newsletter system in this task.

## 5. Motion specification

September19 owner refinement: a separate decorative footer-background layer may
slowly fade short cursive phrases; it must have pause and reduced-motion support.
The table below continues to govern the social badges themselves. Mobile utility
links keep44px targets with smaller inter-row gaps; Company uses two columns and
a full-width email row. No links are removed. The requested Fritz character is
not enabled until its approved artwork is identified.

| State | Appearance / behavior |
| --- | --- |
| Idle | Static materials and reflections. No timed loop, orbit, pulse or glitter |
| Hover, fine pointer | 180–280ms edge/contrast transition; optional one-time <=450ms decorative reflection sweep |
| Keyboard focus | Equally clear highlight plus a visible outline; motion not required |
| Press | Restrained pressed feedback; native link activates immediately |
| Return from external profile | Normal idle/focus behavior; no Followed/Connected checkmark |
| Reduced motion | No sweeps, rotations, floating or scale animations; static focus/contrast remains |

Decorative housing layers may animate. The platform marks must not morph, rotate, extrude, shimmer, or change color. No continuous per-frame React state, animation interval, particle engine or pointer-follow effect.

No fake successful-follow state. An outbound click is not proof of a follow, subscribe, account connection, form submission or business result.

Use the existing CSS-first approach. Prefer transitions of transform/opacity on small decorative layers; do not add Framer Motion, GSAP, Three.js, Lottie or another animation dependency for these controls. Existing shared libraries need not be removed, but this feature must not depend on new heavyweight tooling.

## 6. Profiles, channels and truthful states

`social-profiles.reference.json` is a handoff reference, not an instruction to introduce a second runtime config.

Default candidates from the inspected repo: Facebook, Instagram, YouTube, X, TikTok and Email. Confirm against the latest local owner configuration. YouTube and X are documented owner channels, not newly created corporate channels. Preserve those distinctions in accessible labels and configuration notes.

Future support: LinkedIn, Pinterest, Discord and RSS. Their glyphs can exist in the kit, but show no public link until a real approved destination exists. Do not infer `famtasticdesigns` handles from other platforms. Do not display dead placeholder links or open a modal asking users to wait for nonexistent accounts.

RSS and Blog are different destinations. Keep the existing Blogs navigation, but do not invent a feed URL or label a blog archive as RSS. Blog design is explicitly outside this task.

Recommended runtime data shape, adapted to the current code:

```text
id, label, href, enabled, accountLabel, destinationKind,
sourceReference, assetId, order
```

Validate allowed destination types: approved HTTPS external profiles; existing internal routes; configured mailto address. Reject unsafe protocols, placeholder destinations and unknown renderers. Unknown/unconfigured entries disappear from the public strip; the internal gallery can show them as noninteractive previews.

## 7. React integration

Verified entry points:

```text
Layout.jsx
  └─ SiteFooter({ services, packages })
       ├─ BrandLogo
       ├─ CMS-driven service/package links
       ├─ SocialSignal     ← replace the presentation here
       └─ dynamic year / legal links / signature
```

Suggested structure, not mandatory new files:

```text
SocialSignal         compact group, existing exported entry point
SocialBadge          reusable decorated link
socialProfiles       one configuration source
social-footer.css    scoped styles, or current project equivalent
brand/social/        approved platform assets + reusable housing detail
```

Prefer adapting an existing implementation over making these exact filenames. Keep SiteFooter props and data handling stable. Search all SocialSignal consumers and test them before deleting old styles/exports. Do not move Drupal requests into the icon component.

All meaningful content stays HTML. Reuse BrandLogo and FAMCrown; do not bake a logo, caption or click target into a JPEG. Local assets should be immutable and optimized; no third-party requests for logos at page load. Record official asset acquisition/source/license information. Do not invent vector masters by auto-tracing the generated board.

## 8. Accessibility and browser behavior

Project minimum: 44x44px interactive targets, visible labels, strong keyboard focus and readable contrast. WCAG distinguishes its AA minimum target criterion from its 44px enhanced criterion; the 44px project choice is not a claim that every AA control must be that size [A11Y-1].

- Real anchors for external profiles and mailto; existing router links for internal pages.
- `aria-label` includes the visible platform name and clarifies the account/destination.
- Describe new-tab behavior for external links; retain `rel="noopener noreferrer"`.
- Decorative rim/crown/brush elements are hidden from assistive technology and do not intercept pointer events.
- Tab order follows the visual order. Do not add nested interactive controls.
- Support first-tap navigation, Enter, browser context menus and modifier-click behavior.
- Reduced-motion preference removes nonessential motion without removing focus [A11Y-2].
- No hover-only information is necessary. Optional tooltips repeat/extend information; they cannot be the only label.
- At 200% zoom and narrow widths, content wraps without overlapping the logo, CTA or legal content.

## 9. Analytics and privacy

Existing SocialSignal dispatches `famtastic:social-click` and conditionally sends the `social_profile_click` gtag event. Preserve the existing fields and semantics once per activation. Reuse the current analytics consent path.

A click handler must not prevent navigation if gtag is missing, consent is denied or analytics throws. Do not add requests on hover. Do not log form data, emails from inputs, auth data, URL query strings or fabricated follow conversions.

Track social outbound clicks as social clicks; project-start clicks and completed inquiries remain separate existing outcomes. No new advertising pixel or experiment system in this scope.

## 10. Performance, source handling and scope

No new runtime dependency, webfont, video, animated GIF or continuously running animation for this task. Reuse existing assets and CSS wherever possible. Target a small combined platform-asset payload (initial working budget: <=100KB compressed where practical); report measured deltas and explain exceptions. This budget is an engineering target, not a benchmark guarantee.

Reference PNGs may live with review docs or outside source control per repo policy, but must not ship as footer background files. Provide source provenance and filenames for production derivatives. Keep dimensions explicit to prevent avoidable shifts.

No changes to email, favicon, portal/admin, header/social rails, blog layouts, payments, forms, offers or individual portfolio worlds. A shared public footer naturally appears on routes already using it; do not separately redesign those pages.

## 11. Acceptance checklist

- [ ] Existing orbit and LIVE core are gone; there is only one social module.
- [ ] Working footer integration exists, not just a gallery or image.
- [ ] Housing retains brushed F/A/M detail, obsidian depth and metallic edge at actual size.
- [ ] Official symbols remain recognizable, unmodified and correctly spaced.
- [ ] Canonical logo/crown assets remain unchanged.
- [ ] Default social module is compact; measured before/after height is recorded.
- [ ] Current footer link inventory/data contract, email, legal routes and year remain.
- [ ] Primary CTA remains more prominent; no duplicate finale/CTA blocks.
- [ ] Only approved configured destinations render publicly.
- [ ] Labels are visible; touch, keyboard, reduced motion and 200% zoom work.
- [ ] No fake follow-success state, dead `#` link, invented newsletter or testimonial.
- [ ] Existing analytics fire once and cannot block navigation.
- [ ] Tests and lint/build results are recorded with actual limitations.
- [ ] Screenshots exist at 1440/1024/768/390/320px, including focus and mobile.
- [ ] Local gallery supports all platforms and real UI sizes; not exposed as a public route.
- [ ] Official Design MD/Site DNA links to this scoped specification without being replaced.
- [ ] No push, merge, deployment or customer communication occurred.

## 12. Research / implementation sources

Sources were consulted 2026-09-19. They support the guidance below, not a guarantee of conversion or legal approval. Repository citations and blob identifiers are recorded separately in REPO-RECEIPT.md.

- **UX-1 — Icon clarity:** Nielsen Norman Group, *Icon Usability*: https://www.nngroup.com/articles/icon-usability/ . Apply visible labels and recognizable symbols; simplify detail at actual sizes.
- **UX-2 — Hierarchy:** Nielsen Norman Group, *Visual Hierarchy in UX*: https://www.nngroup.com/articles/visual-hierarchy-ux-definition/ . Use contrast, scale and grouping to establish priority; test the actual result.
- **A11Y-1 — Targets:** W3C, WCAG 2.2: https://www.w3.org/TR/WCAG22/ . Distinguish 2.5.8 minimum from 2.5.5 enhanced sizing; project target is 44px or larger.
- **A11Y-2 — Motion:** W3C, *Understanding SC 2.3.3*: https://www.w3.org/WAI/WCAG22/Understanding/animation-from-interactions.html . Nonessential interaction animation can be disabled; honor reduced-motion preferences.
- **BRAND-1 — Facebook:** Meta's official logo guidance: https://www.meta.com/brand/resources/facebook/logo/ . Use official complete marks and preserve color/shape/clear space; do not make the logo 3D or apply effects to it.
- **BRAND-2 — Additional platforms:** Use each platform's official current asset source. YouTube's legacy brand-resource URL currently redirects to https://brand.youtube/ . Inspect the relevant rules before implementation; this handoff does not assert all platform assets were downloaded or reviewed.


## 13. Implemented review branch — September 19, 2026

This section records the implementation, while the preceding specification remains
the owner's handoff. Where candidate destinations above differ, the reconciled
runtime configuration and [source receipt](social-footer/SOURCES.md) explain why.
YouTube is now the owner-confirmed FAMtastic-Designs Brand Account; X remains personal.

Integration: `SiteFooter({services, packages})` contains its single `SocialSignal`
in the right column above the unchanged navigation lists. `SocialBadge` owns the
independent CSS housing and official image slot. `frontend/src/lib/socialProfiles.js`
is the single destination/visibility source; disabled and unsafe entries disappear.
The original event contract remains once per normal/keyboard/middle activation.
No tracker initialization, duplicate event helper, success claim or hover request.

To enable a dormant platform: first obtain an owner-approved real destination,
review current official use/size rules in `social-footer/SOURCES.md`, then update
its one config entry. Never infer a handle. Run the focused tests, local gallery,
and footer QA. Email is contact; RSS needs a genuine feed, not the Blog route.
Existing company email and Blog navigation remain ordinary footer links.

Housing: 32% rounded square, directional smoked-metal rim, inset obsidian face,
original short red/gold/blue brush paths. Platform images are separate stable layers.
Canonical crown remains within the unchanged full logo. Default badges 64px, tablet
and phone 56px, smallest phone 48px; visible label cells >=44px. A 220ms edge/shadow
response and static pressed rim supply feedback without moving the mark. Reduced
motion removes both transitions, retaining focus outline and contrast.

Review: [implementation and QA receipt](social-footer/REVIEW.md). Gallery at
`frontend/.social-footer-review/` is development-only, excluded from the production
build. All ten platforms have 48/56/64/128px previews and material-state references.
Unknown profiles are non-navigable and explicitly marked Preview only.
