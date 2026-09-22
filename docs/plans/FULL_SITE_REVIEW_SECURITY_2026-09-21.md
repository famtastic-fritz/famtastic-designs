# Full-site review security and correctness review

Review date: September 21, 2026 (America/New_York; September 22 UTC).
Reviewer: independent
`courier_research` agent. Worktree: `codex/travel-addicts-delivery`, based on
`f5bc140e`, including the feature changes in the working tree.

**Result: no remaining concrete source blocker was found after the corrections
below. This is a source and local-validation review, not a production delivery or
live browser attestation. No deployment or production account mutation occurred
in this review.**

## Scope and observed boundaries

Reviewed `FullSiteReviewPackage`, `FullSiteReviewRenderer`,
`FullSiteReviewService`, `FullSiteReviewController`, `FullSiteReviewCommands`,
their routing/container definitions, `PortalProjectsView.jsx`, the focused PHP
and frontend tests, and both deployment scripts. Followed the existing customer
edit, deep-dive resume, proof queue, worker context, and checkout entry points to
check whether the new review could accidentally enter another lifecycle.

The customer reader resolves the signed-in user's customer record, exact request
owner, and current active organization membership. Each page/resource read
rechecks this binding. Anonymous and other-customer controller requests return
the same 404. The separate staff QA reader requires the existing administration
permission; it does not impersonate the customer or record customer approval.

The manifest allows bounded static pages, assets, and explicitly labeled plain
text documents. Reader paths reject traversal, encoded path components,
symlinks, undeclared files, and invalid roles/types. Stored bytes are checked
against their exact length and SHA-256 before serving. The customer projection
omits the full file manifest and internal ownership identifiers.

The response CSP provides an opaque-origin sandbox without `allow-same-origin`.
It disables API connections, form submission, workers, frames, and remote media
loads. The renderer inlines verified CSS, classic JavaScript, images, and fonts;
rewrites local page links to the authenticated reader; and gives external links
no opener. Authored scripts still execute inside this sandbox: this mechanism is
for an explicitly trusted staff package, not an unrestricted public uploader.

Staff create-and-attach records staff authorship and the actual authority
reference. Exact customer/organization membership, a unique request key,
membership/request locks, and event bindings prevent a retry from silently
creating another draft or changing its identity. A failed attachment rolls back
its new draft and audit event. Attachment rejects active proof jobs, selected
work, projects, and Commerce bindings. It contains no mail, queue dispatch,
purchase, acceptance, or launch operation.

## Findings resolved

### 1. A completed-site review could re-enter the concept workflow

The first implementation left the brief editor and submit action available.
Saving/submitting preserved the attachment but could start the existing
three-direction job/notification flow, while the portal continued to show only
the completed-site state. The direct Site Studio dispatch and claimed deep-dive
resume paths also needed protection.

Resolution observed:

- Customer edits reject an attached review before changes or notifications,
  inside the existing request transaction and row lock.
- Claimed deep-dive resume now takes a transaction and row lock before the same
  rejection.
- The central proof enqueue helper rereads the stored request under a row lock
  and rejects both an attached stored request and an attached supplied intake.
  This also covers the direct Site Studio dispatch path and stale intake callers.
- Worker context rejects attached reviews, and attachment refuses any existing
  canonical request proof job.
- Portal brief/domain editors are hidden for this state. The change-request
  link uses the dashboard's supported Messages section. Checkout still requires
  a selected proof and accepted staging receipt; attachment does not satisfy it.

The focused regression covers save, submit, deep-dive resume, direct dispatch,
worker context, stale-intake enqueue, original intake preservation, and empty
job/outbox tables.

Relevant source: `CustomerPortalService::writeWebsiteRequestUpdate()`,
`writeClaimedDeepDiveRequest()`, `queueWebsiteRequestProofJob()`,
`websiteRequestProofContext()`; `FullSiteReviewService::attach()`;
`frontend/src/components/portal/PortalProjectsView.jsx`.

### 2. One response could combine two immutable package versions

The original controller read the initial HTML and then reread the request for
each resource without pinning its version. A new attachment between those reads
could produce old HTML with newer assets, even though each individual read
passed its own hash check.

Resolution observed: `FullSiteReviewController::response()` captures the initial
manifest digest and requires every resource callback to match it. A changed
version fails the complete response with the uniform 404. Current ownership and
membership checks still run on every resource read.

The new regression changes the attached version between the initial HTML read
and the resource read and expects a 404 rather than mixed-version HTML.

### 3. Scoped deployment needed safe argument transport and recovery

The first script passed a multiline manifest specification as an SSH command
argument. OpenSSH's remote command string would split that specification into
shell words/commands. Its original baseline check was also separated from
promotion by source preparation, and its rollback handled command errors but
not interruption signals.

Resolution observed in `scripts/deploy-full-site-review-backend.sh`:

- The specification is transported as one newline-free base64 argument and
  decoded remotely.
- A clean checkout and exact current-main SHA are required. The backend delta
  and promoted runtime files are restricted to the explicit eight-file list.
- The live baseline is checked before preparation and again immediately before
  promotion. The remote source files must match the expected new hashes.
- A private scoped release lock prevents concurrent runs of this same lane.
- New dependencies promote before the existing customer service and routing/
  container definitions. Backups are private.
- Rollback handles ERR, INT, TERM, and HUP, restores existing callers before
  removing newly added classes, verifies restored hashes/file absence, and
  reports source restoration separately from cache restoration.
- The wrapper selects this lane before the normal backend deployment work.
  Scoped commands perform code promotion, cache rebuild, service/command
  discovery, cron-content comparison, and hash checks. No catalog seed,
  database update, configuration import, cron execution, queue runner, mail
  dispatch, or payment action is invoked by this lane.

## Validation evidence

The independent reviewer executed these final commands in the feature worktree:

| Command | Observed outcome |
| --- | --- |
| `bash -n scripts/deploy-full-site-review-backend.sh scripts/deploy-backend-godaddy.sh` | Exit 0; no syntax diagnostics. |
| `git diff --check` | Exit 0; no whitespace diagnostics. |

The implementation agent reported these final local results; the independent
reviewer inspected the source/assertions and did not independently rerun them.
Evidence is in the agent tool transcript, with no separate stdout file.

| Command/run | Implementation agent's reported outcome |
| --- | --- |
| PHP command below | Exit 0; **21 tests, 301 assertions** on PHP 8.5.9 / PHPUnit 11.5.56. Known Drupal integer-mode `fetchAll` deprecation and simpletest output-directory warning remained. This supersedes the earlier 299-assertion run. |
| `node --test scripts/test-full-site-review-flow.mjs` | 1/1 passing. |
| Client Portal Design DNA validator | 34/34 passing. |

```sh
php /Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/backend/vendor/bin/phpunit \
  -c /Users/famtastic-fritz/Development/FAMtastic/sites/site-famtastic-designs/backend/web/core/phpunit.xml.dist \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FullSiteReviewTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WebsiteRequestAuditPreservationTest.php
```

The frontend test renders the real `FullSiteReview` component and checks
next-step precedence, links, and absence of acceptance/payment/concept controls.
It is a component test, not an end-to-end portal test.

The implementation agent also reported a local CUA browser fixture run: bundled
fonts/art rendered; Home-to-Starter navigation retained the synthetic session;
Starter was preselected; the enabled composer produced an unsent draft; cookies
and localStorage access were blocked inside the sandbox; signed-out and another
synthetic customer were denied. No real customer session or outbound contact was
used. That browser run exposed non-ASCII script text being entity-encoded by
libxml. The agent corrected it with nonce-bound raw-text placeholders and an
HTML closing-tag guard; the independent reviewer inspected that correction and
the added regression assertions. The agent reported the draft subject then
contained the expected UTF-8 encoded dash. These are local fixture results, not
production verification.

## Explicit limitations and remaining release evidence

1. No remote preflight, deployment, real account attachment, customer session,
   staff browser review, or production negative-authorization request was run by
   this reviewer. Source readiness does not establish account delivery.
2. Before reporting delivery, confirm the deployed revision/package hashes,
   actual private storage location and web-server denial of direct private
   files, real authenticated reader behavior, anonymous/other-customer denial,
   and the persisted draft/attachment with unchanged jobs, outbox, payment, and
   acceptance state.
3. CSP headers and HTML rewriting were inspected and asserted in unit tests,
   with the local synthetic browser evidence attributed above. Production
   opaque-origin behavior, navigation, all pages/documents, image/font rendering,
   request-composer handoff, and clipboard fallback remain live checks. The
   separate staff reader can prove staff QA; it is not evidence that the
   customer signed in or viewed the site.
4. SQLite fixtures and deterministic callbacks cover the local invariants; they
   do not simulate concurrent production database transactions or arbitrary
   malicious JavaScript. Request/membership locking was assessed from source.
   The reported local PHP suite used 8.5.9; the scoped release requires the
   configured production 8.3 runtime and lints candidate files there before
   promotion. That remote runtime check has not been performed by this review.
5. Deployment rollback has been reviewed and syntax-checked, not fault-injected
   on the real host. Files promote individually. The private lock does not
   serialize unrelated deployment lanes; no other release should run during
   apply. SIGKILL, host loss, disk failure, or a failed recovery cache rebuild
   still require operational recovery from the recorded backup.
6. A failed filesystem copy can leave unreferenced private package files even
   when the database transaction rolls back. They are not exposed as an
   attached review; cleanup/recovery is an explicit operator task.

No source files, deployment state, customer records, or repository history were
changed by the independent review. Its only authored artifact is this report.
