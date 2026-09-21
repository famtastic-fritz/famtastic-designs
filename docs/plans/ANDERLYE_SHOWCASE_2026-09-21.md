# Anderlye agency showcase

Prepared in isolated worktree `fd-anderlye-showcase`, branch
`codex/anderlye-showcase`, from verified current main
`19ad376a659a764df422a3075840c9d443e9426f`. The primary agency checkout contains
unrelated owner work and was not edited.

## Scope and current state

Source-only static agency case at `/work/anderlye/`: four public files under
`frontend/public/work/anderlye/`, plus bounded publication helpers. No customer
application, private records, account, setup link or research work directory is
copied into the agency site. The customer remains independent at
`anderlyepl.famtasticinc.com`.

The page links to the customer site, its digital card, FAMtastic Connect and the
existing scoped project intake. Public non-PII UTM slugs identify those links;
no additional analytics script, cookie, conversion receipt or measured outcome
is claimed. Its final centered footer row uses the exact existing creator-credit
generator and immutable approved logo.

Copy labels the project newly built. It describes a freight CRM with manual
milestones and scopes dispatch/GPS/payment integrations separately. Generated
photography is visibly disclosed; it is not fleet/facility/customer evidence.
No revenue, conversion, carrier-network, delivery-performance or client quote is
invented. The customer's complete live lifecycle proof remains the root
operator's separate release responsibility.

## Why this is a bounded static publication

The first full local frontend build failed with `ENOSPC` while copying existing
video media. Only that newly generated partial `frontend/dist` was removed.
The root operator explicitly directed a four-file new-path release with no
overwrite of existing frontend files, and no further full build. Proposed
WorkHub and SEO-generator edits were reverted completely. This task-specific
exception does not replace `deploy-frontend-godaddy.sh` for ordinary releases.
The demand-engine full-build wrapper was not rerun for this exception.

An initially tempting CMS-only shortcut was checked against production:
anonymous JSON:API returned zero published `case_study` nodes; live field
definitions contain `field_challenge` and `field_solution`, while the existing
React case adapter consumes absent `body`, `field_summary`, `field_subtitle` and
`field_project_type`. WorkHub uses React navigation to that generic case page.
Adding a node alone would produce a thin/empty detail view instead of opening
the static document. No CMS row or schema was changed. The standalone case is
discoverable through its direct handoff link; gallery integration is a separate
small frontend/schema alignment task.

## Publication and rollback

The root operator subsequently confirmed the customer application live at
`4cb3580` and explicitly directed this isolated source commit/push and bounded
static publication. This exception publishes the exact dedicated branch SHA
containing current main; it does not merge unrelated agency source or move the
main frontend release pointer. Record the actual publication receipt below.

```sh
node scripts/check-creator-credit.mjs frontend/public/work/anderlye
node --test scripts/test-creator-credit.mjs
node scripts/sync-brand-assets.cjs --check
node scripts/test-anderlye-showcase.mjs
node scripts/publish-anderlye-showcase.mjs --dry-run
# After exact dedicated source is clean/pushed and root coordinates release:
node scripts/publish-anderlye-showcase.mjs --apply
```

Dry-run reads local source and remote path metadata only. It reports dirty source
without mutating providers. Apply requires the exact clean pushed
`codex/anderlye-showcase` SHA containing current main, the
four tracked allowlisted files, known origin, canonical creator credit and a
payload below 500 KB. It confirms the customer site/card and FAM card are
available before making the case public.

Remote target: `/home/xrdj7j99xhzt/public_html/work/anderlye`.
Private stage/receipt: `/home/xrdj7j99xhzt/deploy/anderlye-showcase/<SHA>`.
The parent `work` directory must already exist and resolve exactly; the target
and source-specific stage must be absent. A private release lock, SHA-bound
manifest, exact four-file inventory, per-file hashes and GNU `mv --no-clobber`
protect promotion. Existing frontend files and `.frontend-release` are untouched.
Only static assets and the HTML/CSS document become public.

After promotion the driver verifies all four file hashes and MIME types on both
apex and www, plus unchanged homepage bytes. A failed live check can roll back
only the still-pending exact candidate with unchanged inventory; it moves the
candidate back into private storage, preserving unrelated work. A conflicting
target, changed file, later new file, repeated source stage or finalized receipt
is refused rather than overwritten or deleted. Inspect receipts after any
uncertain interrupted transfer; do not force a retry.

The source dry-run on September 21 verified that the exact public target and
stage were absent and the parent writable. Remote `/bin/mv` reports GNU
coreutils 8.30; PHP lint passed. No remote mutation was part of that check.

## Local acceptance and limitations

- Static page served at `http://127.0.0.1:4186/work/anderlye/` from canonical
  `frontend/public`, without rebuilding or duplicating existing media.
- CUA Chrome screenshot/DOM review at desktop, 390, 320 and 768 pixels. At
  320/768, document widths matched available client widths (305/753 pixels,
  including the browser scrollbar difference); no horizontal overflow.
- One H1, all visible link targets at least 44 by 44 pixels after the footer
  correction, and final creator logo measured 180 pixels wide and loaded.
- Desktop, mobile hero and footer screenshots are retained in the task's CUA
  tool transcript. They are local proof, not a production-browser receipt.
- Five existing creator-credit contracts and exact-logo validation passed;
  shared brand assets match. Eight isolated publication tests passed, including
  no-write preflight, missing-file rejection, existing-target protection,
  no-clobber promotion, drift-sensitive rollback and no replay.
- The document contains no authored JavaScript. Chrome recorded three generic
  asynchronous extension-listener errors; extension-injected UI was present.
  This is disclosed instead of claiming a completely clean browser console.
- Public apex/www browser acceptance, actual contact-card navigation and the
  independent customer's operational journey remain release checks. No physical
  device, Safari, Firefox, search-indexing or performance outcome is claimed.

Asset provenance and source hashes are retained in
`docs/evidence/anderlye-showcase/build-dna.json`. The original-site issue audit is
in the independent customer repository's `docs/research/current-site-issues.*`:
carrier CTA placeholder number and a 404 PDF download are verified; the six HTML
routes and current 2026 copyright are present.
