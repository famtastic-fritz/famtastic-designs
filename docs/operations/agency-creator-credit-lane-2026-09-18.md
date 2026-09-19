# Agency creator-credit lane — September 18, 2026

Scope: famtastic-fritz/famtastic-designs only. Worktree fd-creator-credit,
branch codex/agency-creator-credit, fresh base 8eb12209. Parent consolidation file
docs/operations/creator-credit-rollout-2026-09-18.md is reserved and untouched.
Parent confirmed MBSH 0653d920d20fe82aa429be7901adcd8544b7be65 deployed first,
then authorized scoped agency main merges/existing-live releases.

## Inventory and implementation

Machine inventory: ../evidence/agency-creator-credit-2026-09-18/inventory.json.
Customer-facing inventory: 48 frontend/public static showcase pages, 170 React/SEO
output shells, four existing unlisted marketing HTML pages, and zero files in
friends-20260918. The wider source appendix is discovery context only: upstream
.agents/skills examples are third-party templates excluded unless exported, not
customer deliverables or retrofit targets. Historical review/evidence and video
composition HTML likewise is not retrofitted merely because it is tracked.
This is not an entire-ecosystem or private-runtime proof inventory.

Covered owners/build paths:
- React App: public pages, standalone portal, token/proof/deep-dive/appointment
  shells. Existing financial, auth, navigation and form logic unchanged.
- Vite/SEO production build: all copied public showcase HTML, including Booked &
  Branded rooms/emails/labs, Alex Touch v1/v2 and owner views, Noise Cuts and owner,
  Omar Top Deals/v2/owner, Thirst Trap 772/v1/v2/owner, creative matrix.
- Booked & Branded pilot and research builders, static-proof and portal bundlers.
- Beauty cohort direction/hub builder and its static QA; original exact hosted
  credit is the sole new exception to its remote-resource restriction.
- Provider pipeline: deterministic credit after construction and before manifest
  hashes/independent QA; byte-identical local PNG for its offline resource contract.
- Four agency pilot builders (church, FAMU Corner, Bossy Nails, Candy Lady) require
  new output directories; credited HTML hashes are included in their new manifests.
- Broward barbershop generator requires a new output directory; engine fixture
  renderer uses the shared Python/JS contract. No old pilot output was regenerated.
- PHP deterministic stub builder and shared BrandedEmail shell for future renders.
- Marketing HTML versioner and existing unlisted-publication enforcement gate.

Read-only live discovery: showcase families exist. /proofs/friends-20260918 exists
but has no files (not a live demo to retrofit at inspection). One existing unlisted
marketing directory contains index.html and three cards; public token not used for
attribution. Scoped release captures its exact paths in the server receipt.

## Verification and limits

Passed: Node22 production build; original PNG hash and shared brand asset sync;
five creator-credit contracts including no unpublished launch, old footer/live-copy
preservation, complete backup, refusal to overwrite version, callback size limit;
beauty cohort JSON/CSV contracts; 86 mail presentation assertions; PHP lint; Python
compile; 34 portal Design DNA source assertions; shell syntax; git diff checks.
Earlier local standalone Playwright: 153 credit cases (51 routes ×320/390/1440),
48 email responsive/images-disabled cases. Those occurred BEFORE the owner required
CUA-only browser tests. No further standalone browser automation will be used. The
mobile-clearance addition came afterward and needs CUA verification. No actual mail
client/inbox/customer acceptance or private authenticated workflow claim.

Local disk pressure: removed only this task's generated dist (~207MiB), test temp
outputs (~1MiB), and newly installed node_modules (~87MiB). Saved inventory and
browser evidence first. No worktree/source/user assets removed. Further build occurs
on remote host; no new local whole-artifact copies.

## Release steps and scope

Fresh-fetch and merge current main into this branch; source commit, push main only
after checks. Existing canonical deployers gain FAMTASTIC_CREATOR_CREDIT_ONLY=1:
frontend builds exact main remotely, backs up every changed HTML path, preserves
current live static copy and old metadata, uploads only compiled React assets and
already-live HTML, and excludes absent targets. It includes the two known proofs
directories but creates no new customer route. A separate scoped receipt retains
before/after hashes; normal .frontend-release remains the broader-release receipt.

Backend mode requires exact pre-change hashes and deploys only CreatorCredit.php,
BrandedEmail.php and ProofCampaignService.php from the same verified private release.
No migrations, config/cron, queues, Composer, customer data or mail sends. A changed
live base fails closed for inspection. Separate backend credit marker proves scope.

Rollback: restore only paths named in the credit backup/receipt and the two original
PHP files; remove the newly introduced CreatorCredit.php only after restoring its
caller. Restore old React shells, keep harmless hashed assets. No database restore.

Exclusions/pending: immutable historical evidence and approval hashes; standalone
customer repositories/platform/studios (other lanes); historical media compositions,
stills/videos; database-stored prior proof HTML (requires new authored version and
fresh review, not in-place mutation); untracked/non-main demos absent from inventory;
private authenticated visual QA; actual email client/inbox proof. No unpublished
customer launches. Server dynamic authenticated proof body inventory is not claimed.
Source changes for historical builders were syntax-checked, not fully rerun with
old copyrighted assets/provider jobs. Drive mirror status will be recorded at close.

## Runtime inventory correction and response decoration

The earlier zero-file friends count was a file-only find result, NOT proof of no
deliverables. The directory has three PHP-backed symlinks to private versioned
releases: coastbound-electric (now 8ae887fd3aef9ba5b960eb35332f8980da5193cb,
changed concurrently from 126c10517f91b160235b70c51e189987ccaef279 and already has
canonical logo/UTM credit), south-shore-communication-systems
(9105c9727e8773de195f31e9eb1c0c2452f9f64f), your-concierge-guru
(0fcdf04d583ac82da77d83427616911440960b11). These have public index.php/admin.php,
backend/bootstrap.php and a var symlink to private persistent state. Never flatten
these into static HTML or copy/reset their state. No customer repo changes in this
agency lane. Other lane coordination is necessary before swapping those releases.

Drupal runtime: 114 HTML files in 31 campaign directories under public_html/web/proofs.
WebsiteRequestProofController serves account/private-share directions;
PublicPreviewController serves signed lead directions. Both already rewrite asset
URLs after authorization. CreatorCredit::present now decorates the response after
those checks, without saving, changing approval hashes, customer records or status.
There is no byte-signature blocker; the prior statement requiring fresh customer
review for footer presentation is superseded. Same-origin PNG preserves the current
CSP; no access/security header is weakened. A pure PHP regression proves original
bytes/hash and old footer remain unchanged, and rendered credit is final/idempotent.

The scoped backend release is now five files (three services, two controllers).
Initial source commit 9c782bf8; integration e51ed18f9d8ff3dc9c16f35683466c444fa9f926
was pushed to main. Its remote build passed; rollout stopped on the new symlink
discovery before any live file write. Follow-up isolates those linked PHP releases
and adds serving-time enforcement. Exact final release receipts follow below.
