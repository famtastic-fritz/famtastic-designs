# Approved content direction — family propagation

Status: **local-tested, expanded review ready; not pushed or deployed**.
Branch: `codex/content-experience-web-basics`, parent proof5d98e4d1. The owner's
“Approved!” approved the Web Basics visual direction and its explicitly proposed
Packages → Solutions → Services → About → Contact propagation. It did not authorize
a production release, real lead submission, customer email or account change.

## Review

- Before/after desktop/mobile gallery: http://127.0.0.1:8765/content-rollout/
- Local interactive pages: http://127.0.0.1:4187/packages,
  http://127.0.0.1:4187/services, http://127.0.0.1:4187/about,
  http://127.0.0.1:4187/contact.
- Local source is the agency worktree, not the unrelated Documents Shopify repo.
- The preview reads public production CMS content. **Do not submit real details**;
  only the automated test installs a non-sending intercepted transport.

The creative-direction skill informed pacing and invariant preservation only.
Its example commercial promises were not adopted; FAMtastic's source facts and
canonical brand rules remain authoritative.

## What changed

| Surface | Implemented local treatment | Preserved |
| --- | --- | --- |
| Seven package details | Metal price plate, numbered scope, editorial fit, existing guide links | Exact CMS price, recurring label, timeline, features, optional add-ons, approval/payment gates |
| Packages hub | Comparison columns with restrained badges and price hierarchy | Existing sort order, best-for copy, first7 features, destinations and selection analytics payload |
| Six solution details | Technical index, numbered challenges, glass system, source process, FAQ | Existing source fields, reviewed-only testimonials and branch-selected SolutionFinder |
| Services hub | Indexed editorial capability rows | Existing six services, first4 deliverables, descriptions and destinations |
| About | Large native type and red/gold/blue F/A/M definitions | Exact approved meanings, complete existing Drupal business story; no fabricated founder content |
| Contact | Invitation, project-fit intake, black-glass form with visible focus | Existing IDs, fields, validation, payload, endpoint, fallback and intake-first order |

Solutions already resolves through `/services`. No second route, new page builder,
CMS schema or backend deployment was added. Blogs, Work/customer worlds, homepage,
portal/admin, intake/auth/legal styling and the existing email system are excluded.

Two small safety corrections support this presentation:

1. Web Basics research routing uses its exact slug. The old `/199/` price/title
   match also matched the $1,999 Custom Website; it now correctly goes to `/start`.
   Web Basics renewal/cost-comparison language never appears in other package plates.
2. Contact's opt-in crown requires the existing backend's `ok:true`, positive integer
   `request_id`, and `received`/`partial_success` status. A notification failure still
   means saved request but keeps its server warning. Missing receipt and failed
   transport show no crown. Mail-client handoff is not request or delivery proof.

FAQ reduced-motion behavior is opt-in for the new solution recipe. Other consumers
retain their previous default. No new assets, fonts, image generation or dependency.

## Checks actually run

- `node scripts/test-content-families.cjs`: **68 responsive cases** across17 existing
  routes at320/390/768/1440. One H1, short native script spans, source-content
  fidelity, prices/CTAs, no overflow/page exceptions, readable controls and focus.
  Existing FAQ open/close and service-specific finder open/close also pass.
- Six representative families pass320px blocked-font layout and real keyboard
  entry. Six excluded-route smoke checks remain outside `.fam-page`.
- Contact validation plus four intercepted POST scenarios pass: saved, saved with
  notification failure, incomplete receipt, and failed transport. No unexpected
  write attempts. No real mail, request, account or payment was created.
- The family suite fetches current anonymous CMS source once, then reuses that
  snapshot and caches other anonymous JSON:API GETs. This avoids repeated remote
  read timeouts. It proves source projection, not sustained production availability.
- `node scripts/test-content-experience.cjs`: original Web Basics five-width
  regression passes, including real guide cards, commercial disclosures, safe
  URLs, keyboard/reduced-motion, escaping/missing node and font fallback.
- `node scripts/capture-content-families.cjs after`:12 live-readonly desktop/mobile
  captures, zero overflow/errors. Author visually reviewed hero, details and full
  mobile images. Before captures were taken from the parent layout before enrollment.
- `node scripts/test-website-logo.cjs`: six-width existing public-logo/nav checks pass.
- `node scripts/test-cursive-headings.cjs`: existing homepage four-width loaded and
  blocked-font checks pass. `node scripts/sync-brand-assets.cjs --check`: pass.
- Node22.23.2 frontend build passes: JS857.18kB/gzip248.25, CSS199.37kB/gzip38.17.
  Versus the approved proof: +10.40kB JS/+1.90kB gzip; +10.64kB CSS/+1.74kB gzip.
  The existing>500kB bundle warning remains; no performance certification is claimed.
- Current Build DNA and source hashes:
  `docs/evidence/content-experience-rollout/build-dna.json`. The parent proof record
  stays frozen at5d98e4d1. Earlier test assertion/remote-timeout iterations are
  recorded, not hidden. Full accessibility, authenticated flows and production
  release checks are not part of this local visual acceptance.

## Existing content issues, not new guarantees

Source preservation exposed existing copy that needs a separate factual review:
service pages contain percentage, speed, ranking and setup-time claims; Contact
says one-business-day response while the backend default is three. This pass did
not rewrite the CMS, promote those claims into metric/proof panels, or verify them.
Resolve the response-time policy and review legacy promises before any wider copy
release. The explicit reviewed-proof gate continues to hide old seed testimonials.

## Reproduce / next release gate

Use Node22 on PATH. Start the existing frontend preview:

```sh
VITE_DRUPAL_PROXY_TARGET=https://famtasticdesigns.com/web npm --prefix frontend run dev -- --host 127.0.0.1 --port 4187
```

Run the two browser suites, capture `after`, then
`node scripts/render-content-families-review.cjs`. Serve `.local-email-preview` on
127.0.0.1:8765 to view `/content-rollout/`. The `before` images are ignored local
artifacts from5d98e4d1; do not silently replace them from the new checkout. On a
fresh machine, recreate them in a separate parent-revision worktree.

Design/agent rules now state the approved direction and exact route enrollment.
Before any authorized release: fetch/reconcile remote, review exact commit and
expanded visuals, run release preflight, deploy through existing scripts, verify
release markers and public/mobile/browser behavior. A local commit is not GitHub
or production parity. No DB migration or customer delivery is needed for this pass.
