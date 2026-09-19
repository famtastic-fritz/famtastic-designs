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

Release result and exact SHAs: pending below; source readiness is not deployment.
