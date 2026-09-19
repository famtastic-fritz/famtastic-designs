# FAMtastic creator-credit rollout audit — September 18, 2026

## Mandate and acceptance

Default, unless Fritz explicitly approves a scoped exception: preserve the existing
footer/copyright/credit and add the exact owner-approved FAMtastic Designs logo in
the final centered, clickable row of every authored site, proof, prototype and
lead/demo web output. Lower-cost work is not exempt. The destination is
https://famtasticdesigns.com/. No redesign or alternate logo is authorized.

The approved PNG is 2172 × 724; SHA256
`ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
Displayed widths are normally 160–220px, with proportional height, an accessible
link name and a minimum 44px target. Neutral backing may provide contrast.
Critical geometry is inline/cache-independent. Persistent mobile controls must not
cover the link. Existing content, legal links, forms and customer identity survive.

Optional attribution uses public site slugs with `utm_medium=creator_credit` and
`utm_campaign=created_by_famtastic`. No new analytics script, cookie or personal
identifier was introduced. An actual Inc-to-agency click retained the tags.
This is source attribution capability, not proof of a new analytics dashboard or
recorded conversion. Agency proof links currently use a generic agency source.

## Verified live releases

MBSH96 was deployed **first**, before the other lanes were released.

| Surface | Source / runtime receipt | Verified result |
| --- | --- | --- |
| MBSH96 Reunion | runtime `76b578c23fe76202fb39323367de1412afa1a6de`; documentation/main `2880e9aa9e2d83b523b6ebbfb373062eaa18ad19` | 52 complete authored HTML/PHP documents covered, including templates and legacy copies; public, portal, admin and preview sources; exact live logo hash and release manifest verified. First release was `0653d92`. |
| FAMtastic Designs frontend | `50a07d5ec8b451dbfbcff34936ad4ae46a06f6ea` | 222 existing HTML targets: 170 React/SEO shells, 48 showcase pages, four existing unlisted marketing pages. Existing-only release, no absent customer routes launched. |
| Agency backend/admin | `7490f297831ca1460f20ed2b40b73be812555c0b` | Six scoped presentation files: existing email shell, proof builder, protected/public proof controllers, shared credit helper and admin theme. No DB/config/queue migration. |
| Legacy public proof presentation | same backend release | 48 new credited versions in 16 public campaigns; all 48 HTTPS responses matched the new hashes. All 114 original HTML files in 31 campaigns remain byte-identical. All 66 direct paths in 15 protected campaigns still return 403. Authorized controllers decorate responses without rewriting originals or approvals. |
| Pros In Training | `0bd0e6b5e18e17320d222b47d66adb438cf220c4` | Final logo below existing staging explanation; parent desktop/mobile browser verification. No final-launch or client-acceptance claim. |
| FAMtastic Inc | `3bfd4fe0d7687daf1c8ee178b148305c26e48b0d` | Live cache-safe 190px logo; exact index-only HTTPS hash verified; desktop/mobile check and actual outbound click. |

MBSH source count is not a count of independently visited production routes.
Agency inventories are distinct; do not add them together and call the result a
count of unique customer websites. Detailed agency evidence and rollback paths:
[agency lane report](agency-creator-credit-lane-2026-09-18.md) and
[runtime receipt](../evidence/agency-creator-credit-2026-09-18/runtime-release.json).

## Build-process enforcement

| Repository | Verified remote main | Scope / adoption |
| --- | --- | --- |
| FAMtastic ecosystem | `e5746089d14a82f4909e98aa1876226700754012` | Startup, generated context, design/build principles, repository contract, marketing and retired-Studio guidance. Dirty primary checkout preserved; remote instructions are not claimed loaded there. |
| Component Studio | `5371b242a314cb4c689d39bf0e975bf8373b2b0d` | Foundation 1.1.1, canonical PNG, semantic/hash validator and scaffold rule. Divergent primary branch preserved; the existing documented runtime override now binds the clean pinned worktree, and discovery is available. |
| Site Studio Next | `b43e440fdfb8a25b2bc81c3c879c01d52df7a33b` | Generation, model/CMS/React source templates, transfer, export and build guards. Primary safely fast-forwarded; documented idle-service reload kept the same data root and zero builds. No jobs dispatched. |
| Media Studio | `de7d78d1fad6100c5297d0c4676b94efcf09ff01` | Canonical asset/rights metadata and medium-aware attribution rules. Primary safely fast-forwarded. |

Existing compliant rows pass without duplicate work. An older selected bundle
missing the credit gets a presentation-only derived artifact and a receipt with
original/derived hashes. No page regeneration, additional Fritz approval, provider
call, customer acceptance or payment occurs. Invalid rows or conflicting/missing
assets fail explicitly instead of silently overwriting source. The original
selection/approval record remains intact.

Next's Component checkout mismatch predated this credit release according to the
old catalog pin and primary reflog. The documented machine-local
`FAMTASTIC_REPOSITORY_CHECKOUTS` override now uses the clean
`/Users/famtastic-fritz/Development/worktrees/creator-credit-component` at the
verified pin. **Keep that worktree while it is runtime-bound.** Component and
Media discovery now report available (six and four entries respectively), with
the same data root and zero builds after the scoped idle-service reload. No
customer build was run to prove end-to-end delivery. The old divergent primary
was not overwritten or merged. The prior service plist was backed up.

The agency also covers its existing BrandedEmail shell, React portal/proof shells,
native Drupal admin theme, deterministic/provider proof generation, static and
portal bundlers, Booked & Branded builders, beauty cohort, pilot builders and
marketing publication preflight. Source checks and CI guards are recorded in the
lane report. This is not a second email or website delivery system.

## Independent parent browser evidence

- MBSH homepage: desktop and 390px mobile, original PNG loaded, one centered
  clickable row; clearance verified above fixed help/chat controls. Portal sign-in
  desktop also checked. No sign-in or commerce action performed there.
- Pros In Training: desktop and 390px mobile; staging note preserved above the row.
- Tighten Up Your Locs: live public homepage desktop; original credit/privacy/terms
  retained. Later cache-hardening release is recorded separately by its owner lane.
- Inc: 390px mobile, 190px image, no horizontal overflow; clicking it reached the
  correct agency URL with source tags. Existing CSS cache regression fixed first.
- Agency homepage: bare and www hosts, desktop/mobile, one 180px final row.
- Palmera Fade Society C existing proof: desktop/mobile, existing demonstration
  form/footer retained; no form submitted.
- Authenticated Drupal `/web/admin`: 390px mobile, one loaded 180px logo, 84px
  link height, zero center offset and unobstructed target with navigation closed.
  The open mobile navigation drawer intentionally covers page content; it was
  closed for the check and its original preference restored. No admin operation
  or record was changed. `/web/user/1` also rendered one credit.
- The Reckoning: local public root at 1280/390 and desk at 1280/390; logo is clear
  of the visible consent panel and fixed desk navigation. Local proof alone is not
  a deployment receipt. No consent or business form was submitted.
- Subsequent live Reckoning homepage desktop/390px CUA confirms the original
  final logo remains clear of the visible consent panel. Kakes live homepage at
  390px also shows one loaded 190px original logo and the correct public-slug
  destination. No customer or consent action was submitted.
- Three recent friends proofs: Coastbound/Tuff E Nuff, Your Concierge Guru and
  South Shore public pages verified at390px; centered final160px original logos,
  existing footer/privacy/owner-desk links retained and correct public slug tags.
  Coastbound's timed offer dialog was closed to inspect the page; no form,
  subscription or offer was accepted. Concierge also checked at desktop width.

## Regression checks and boundaries

- MBSH: nine test files, source identity validation, PHP lint for changed runtime
  files, deterministic 52-document credit check and immutable deployment checksums.
- Inc: source/package check, repository validator and new cache-safety test.
- Agency: production frontend build, five Node credit contracts, 86 mail
  presentation assertions, 34 portal DNA assertions, PHP presentation/admin tests,
  six production PHP lints and legacy-version routing/preservation/access checks.
- Component: 32 tests; Media: 10 tests. Next: final affected eight files/54 tests
  and later two-file/10-test negative/fulfillment regressions. The earlier full
  81-file/941-test pass preceded refinements; final full rerun hit local ENOSPC and
  is **not** reported as a full final pass. Lint passed.

No real mail was sent/resubmitted for this rollout; Gmail/Outlook rendering and
inbox delivery are not claimed. No payment, booking, authentication policy,
customer approval, notification queue, financial record or stored proof was changed.
Existing live-site retrofit authorization was not used to launch unpublished sites.

## Findings and explicit exclusions

1. Existing shared CSS can remain cached after HTML changes. Inc initially showed
   the full intrinsic-width image. The live fix pins critical geometry inline and
   versions dependent CSS; the rule now records this requirement.
2. Mobile fixed controls can cover a correctly centered logo. MBSH needed extra
   clearance; proof/portal/desk layouts were checked for this independently.
3. File-only inventories miss symlink-backed apps. The three recent friends demos
   have PHP releases and private persistent state; they are not static folders.
4. Historical evidence, signed approval records and original media are not edited
   in place. Where web presentation needed a retrofit, derived versions or serving
   decoration retain the originals. External/vendor skill examples are not client
   deliverables unless exported.
5. PNG/video/print pixels cannot carry clickable links. Their rule is a visible
   exact logo plus accompanying destination metadata/caption or a clickable
   document/web wrapper where supported. Historical media was not bulk rerendered.
   Legacy fixed-path August marketing runners now refuse overwriting evidence;
   a newly versioned successor/export remains an explicit follow-up.
6. Drupal displayed an existing security-update warning during the admin check.
   No dependency/security upgrade was attempted as part of this footer rollout.
7. Local disk pressure interrupted an intermediate dry-run and a full test rerun.
   Only task-created disposable artifacts/dependencies and the fully merged MBSH
   temporary checkout were removed. User source/assets and dirty work remain.

## Customer-repository matrix

Inventory covers 32 site-prefixed repositories; the agency and other studios are
recorded above. SOURCE-ONLY is not a live release. This audit records incomplete
work explicitly; it is not a claim that the entire fleet retrofit is finished.

| Repository | Source checkpoint / branch | Release and remaining boundary |
| --- | --- | --- |
| site-alex-touch | `45c50951688a` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import; Earlier diagnostic mobile overlap findings on some owner/gallery routes require final CUA and any fixes |
| site-altitude | `42657bf7b935` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-auntie-gale-garage-sales | `ccc9d905b0a0` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT Existing Netlify staging confirmed; live old footer remains; provider publish-directory/branch binding unknown; parent read-only assessment pending |
| site-brother-k | `90ea91795477` / — | EXCLUDED: concurrent scaffold/proof authoring, uncommitted foreign work, no first launch  |
| site-coastbound-electric | `f3af01be24d9` / main | DEPLOYED existing surface; no first launch Authenticated owner-desk live browser QA not exercised; source template and isolated auth/integration tests covered |
| site-drop-the-beat | `a6fa00e7c1d7` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-famtastic-hosting | `888baacfd003` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed; no production deploy Post-cache-hardening Astro build and CUA; Narrow SSR release plan; broad unrelated deployment not authorized |
| site-famtastic-inc | `3bfd4fe0d768` / main | DEPLOYED existing surface; no first launch  |
| site-famtastic-thoughts | `1452eefa35a6` / — | EXCLUDED: reserved empty repository  |
| site-fresh-cuts-in-atlanta | `fd51888bf38e` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-groove-theory | `c8ecec2ad92e` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-guys-classy-shoes | `3341ff4207ce` / — | EXCLUDED: recovered archive, no confirmed publishable surface  |
| site-jamari-graduation | `5a61403565ea` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import; Earlier diagnostic mobile overlap findings on some owner/gallery routes require final CUA and any fixes |
| site-jj-ba-transport2 | `c3cf22072525` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-kakes-by-kesline | `54a3acf3746d` / codex/kakes-owner-desk | DEPLOYED existing surface; no first launch Standalone historical404 and authenticated static screenshot guide not retrofitted; Authenticated desk browser QA not exercised; local PHP auth/data tests passed |
| site-marios-pizza | `85229aa5f46f` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-mbsh-class-of-2000 | `128534620baa` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import |
| site-mbsh-reunion | `2880e9aa9e2d` / main | LIVE; runtime76b578c; see release table.  |
| site-omar-top-deals | `7cf4ea6f8376` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import; Earlier diagnostic mobile overlap findings on some owner/gallery routes require final CUA and any fixes |
| site-pros-in-training | `0bd0e6b5e18e` / main | DEPLOYED existing surface; no first launch  |
| site-readings-by-maria-research | `62cf3e2c7427` / — | EXCLUDED: recovered archive, no confirmed publishable surface  |
| site-south-shore-communication-systems | `1840321bc121` / main | DEPLOYED existing surface; no first launch Authenticated owner-desk live browser QA not exercised; source template and isolated auth/integration tests covered |
| site-stockandship98 | `548ff24b764f` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import |
| site-street-family-reunion | `986466ac255c` / — | EXCLUDED: recovered archive, no confirmed publishable surface  |
| site-studio-next | `b43e440fdfb8` / main | MERGED and idle runtime reloaded; no customer builds dispatched.  |
| site-the-best-lawn-care | `8f0c2669d268` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed; no deployment or new launch Historical Netlify URL not reverified; current publication eligibility unresolved |
| site-the-daily-grind | `25e901b35864` / dev | Footer partial and fail-closed publication guard merged. Twelve tests pass; normal build/adoption intentionally rejects four pre-existing truncated pages before output writes. Original HTML unchanged; NO DEPLOYMENT. |
| site-the-daily-grind-in-atlanta | `785c765c6871` / dev | SOURCE COMPLETE for complete authored surfaces; verified default dev updated; NO DEPLOYMENT No verified existing live URL or provider path; no first launch |
| site-the-reckoning | `d293cc6ca1ae` / main | DEPLOYED existing surface; no first launch  |
| site-thirst-trap-772 | `4d7afd339b3e` / codex/creator-credit-20260918 | SOURCE-ONLY feature branch pushed. Default build/current-output guard passes; historical artifacts and approvals unchanged. Not deployed. Fresh CUA and any separately authorized existing artifact replacement/import |
| site-tighten-up-your-locs | `1ddd7f49d969` / main | DEPLOYED existing surface; no first launch Private/auth surfaces not covered by public three-page retrofit |
| site-your-concierge-guru | `fe2a763689f6` / main | DEPLOYED existing surface; no first launch Authenticated owner-desk live browser QA not exercised; source template and isolated auth/integration tests covered |

### Remaining work

- Six versioned prototype repositories still need final mobile fixes/QA and main
  merges. Existing feature branches preserve source checkpoints and original
  artifacts. Alex has a confirmed mobile-overlap finding.
- Hosting and Lawn source updates require final release eligibility/QA checks.
- Auntie Gale has an existing Netlify staging site and an available local provider
  credential. Read-only comparison found all seven published HTML files differ
  from the pre-credit source (layout, navigation, phone text and stylesheets).
  Published netlify.toml contents remain unverified. No functions or schedules
  were reported. Source deployment would exceed the footer-only scope; reconcile
  the current published source and preserve configuration before publication.
  Site d50d0586-8f28-407f-aebe-14c175a88e1d remains on ready deploy
  69d717ab39d6b376e5a3e72c. No Netlify writes occurred. Missing CLI alone is not
  a credential blocker.
- Daily Grind requires restoration of four pre-existing truncated pages; do not
  fabricate missing content. Its footer partial is updated but pages are not fixed.
- Private Locs surfaces, Kakes legacy guide/404 and authenticated customer desk
  browser coverage remain separately listed gaps, not implied complete.
- Disk pressure is actively limiting remaining checks. No source/assets were
  deleted to make space. Unlaunched sites are not launched under retrofit authority.

### Final follow-up receipts

- Hosting: feature888baacfd003 remains unmerged; main2a8ed5c31410. Tests/build
  passed and mobile homepage/login credit is clear. Full breakpoint QA unfinished.
  Live certificate is expired; documented runtime files are absent and deployment
  binding unresolved. Task-created dependency symlink is untracked.
- Lawn: feature8f0c2669d268 remains unmerged; maina51c7a1d6d5d. Seven pages at
  three widths passed CUA including open-popup clearance. A two-line clearance
  CSS fix remains uncommitted in the isolated worktree; tests passed after it,
  full build passed before it. Existing Netlify site responds, provider binding
  remains unresolved. Only validated disposable task build output was removed.
- Locs: public three-page release remains live. Login/forgot/reset, owner desk,
  newsletter owner and confirmation/unsubscribe, and419/429 runtime templates
  are still uncredited; assessed read-only, not represented as complete.
- Kakes: public/auth/desk renderer release remains live. Standalone404 and
  authenticated admin guide remain pending. Decorate the guide response rather
  than modifying historical guide HTML/screenshots; version the404 safely.
- MBSH documentation/main advanced to9c2d7c6 after plan closeout. Its live runtime
  remains76b578c, unchanged by documentation-only commits.
