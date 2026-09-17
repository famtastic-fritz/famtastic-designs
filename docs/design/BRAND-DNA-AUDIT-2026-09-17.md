# Brand DNA enhancement audit — before implementation

Baseline: `1566d531652fcf2ac0bde0cbc9120ba6f2db9c84`; live application release
`98c17d58`. Repository identity verified: `famtastic-fritz/famtastic-designs`,
full worktree `Development/worktrees/fd-client-selected-build-flow`. The attached
legacy `~/famtastic/sites/site-famtastic-designs` path is not the active source.
Review branch: `famtastic/brand-dna-site-enhancement-v1`. No merge/deploy/send.

## Findings and bounded decisions

| Surface | Existing implementation | Decision |
| --- | --- | --- |
| Public | React Layout, v1 components, Inter/Space Grotesk, particle hero, established pages and grids | Scope expression to agency wrapper and existing component classes; no page/route/content changes or new particles. |
| Client | PortalNav, PortalHomeView, portal.css; dense forms/messages with real state | Subtle material layer and interaction states; crown denotes authorship, never invented approval. |
| Admin | Drupal Claro subtheme; generic admin/auth Twig shells; operational CSS | Restrained shell texture, focus/selection, login expression. No table/log decoration or permission changes. |
| Email | Existing OutreachMailer dispatches versioned StagingReviewEmail; PHP table renderer, escaped body, plain-text alternative | Existing consumer; tokens/master already match. Keep renderer and delivered templates byte-stable unless a concrete conflict requires a fix. No new shell. |
| Logo | Exact 2172x724 PNG in frontend, docs and admin theme | Preserve SHA ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950. |
| Compact marks | Legacy famtastic-mark.svg in editorial/campaign contexts, theme logo.svg | Do not mass-replace these compositions. Crown becomes favicon/explicit primitive, not automatic substitute everywhere. |
| Favicon | No explicit agency favicon links/manifest in frontend head; theme defaults retain Drupal/legacy metadata | Add complete extracted crown family and explicit frontend/admin/customer metadata; no service worker or PWA behavior. |
| Customer worlds | Static showcase projects have independent colors/typography | Exclude their files and descendants from enhancement selectors. |
| Documentation | BRAND.md, root design.md, portal Design DNA, email brand system; CODEX delegates to AGENTS; CLAUDE symlinks AGENTS | One visual specification under docs/design; root design.md retains experience/workflow authority and imports visual rules. Machine-readable visual DNA is new, not a competing delivery/build-DNA schema. |

No dedicated Ops design specification or existing canonical Site DNA file was found
by filename/content search; current admin theme/CSS and portal Design DNA are the
operational baseline. `.site-context` contains learning/change records, not a DNA
generator. Retain existing 0–10 customer creative preference; new FAM 0–3 refers
only to surface expression, not customer consent, budget or delivery state.

## Acceptance plan

Before/after public routes: home desktop/mobile, work, $199 offer. Locally rendered
portal/admin component fixtures cover dashboard, proof, dense utility and success
presentation without changing production or inventing customer records. Label
fixtures explicitly; they are not authenticated workflow proof. Capture all favicon
sizes and local browser-tab metadata. Run existing email/portal/logo checks, build,
PHP lint, source diff guards, icon dimensions/PNG hash checks and reduced-motion QA.
No standalone frontend lint/typecheck script is configured. Local Drupal vendor
is absent, so full Drupal functional tests remain a disclosed limit.
