# Canonical release decision — immutable snapshots

The final canonical deployment uses `stage-campaign-release.php` to package Git-tracked JSON into `backend/campaign-releases/<SHA>` outside the document root. It writes a small `campaign-source.json` marker into staged module code. The marker and module move/rollback together; the locator uses only that declared snapshot when present and exposes its source SHA/sync time. Missing snapshot data remains unavailable rather than falling back to stale mutable files. Without a marker, local/legacy discovery still unions approved roots.

No mutable CLI schedule is overwritten and no external writer pause is assumed. The `campaign-release-files.php` promotion/rollback helper below is retained as a tested optional primitive, **not invoked by the canonical deployment**. Its quiescence warning still applies if adopted separately. Snapshot retention is conservative: keep prior snapshots for rollback; remove only through separately scoped verified cleanup. Media is outside this JSON allowlist and missing media stays explicit.

# Campaign JSON release packaging — 2026-09-30

This is a packaging primitive consumed only by `scripts/deploy-backend-godaddy.sh`, not a new deployment lane. Implementation-only; no production command, provider request, publish or schedule ran. FAMtastic foundational definition v1.0.0 and repository operating/release contracts read. CMS specialist context inherited from campaign handoff: `engineering/engineering-cms-developer`, pinned library `765be42358100bf89d2faa567668a94c602f9a26`.

## Interface and exact insertion

Host dependencies are existing PHP and Git, no Node/npm requirement. Before code promotion, using a private package path that survives removal of `$stage_root`, run:

```bash
campaign_package="$deploy_dir/tmp/campaign-package-$timestamp-$commit_sha"
campaign_backup="$HOME/backups/famtastic-campaigns-$timestamp-$commit_sha"
php "$source_dir/scripts/stage-campaign-release.php" \
  --repo "$source_dir" --commit "$commit_sha" \
  --output "$campaign_package" --live-root "$production_dir"
```

`$production_dir/marketing/campaigns` is the locator-approved directory adjacent to `web`, outside Drupal's document root. Packaging never writes there; `--live-root` only reads its existing files and `campaign-release-manifest.json`. Do not place the stage under any served directory. This path is independent of the existing backend stage cleanup. Canonical deploy retains it until campaign promotion and verification complete.

Output: `campaign-release-manifest.json` with schema `famtastic.campaign-release.v1`, exact `source_commit`, and `files[]` entries containing `path`, `sha256`, `bytes`, Git blob ID and `expected_live_sha256` (null for absent targets). Package preserves `marketing/campaigns/<slug>/<filename>`. Only three exact file basenames are eligible: `posting-schedule.json`, `manifest.json`, `scorecard.json`. Deeper evidence manifests, provider configuration, `.env`, customer lists, drafts and arbitrary media do not enter the package.

The helper requires full SHA, matching checkout HEAD, a real regular Git blob, safe slug, valid object/array JSON and working bytes identical to the selected blob. An untracked (including ignored) allowlisted candidate is an error, not something silently released. Symlink components and dirty source candidates are errors. All validation completes before output creation. An I/O failure leaves a partial stage without a valid completed manifest and must abort promotion.

## Backup, promotion and rollback contract

The owning canonical deployer must implement these steps under its deployment lock:

1. Package and validate before changing production. Abort if any existing destination differs from both new bytes and its hash in the previous release manifest. This deliberately blocks stale Git data from overwriting a CLI-updated provider schedule. Missing previous receipt is acceptable only for absent targets or byte-identical targets. Reconciliation is explicit; do not regenerate a receipt to bypass the check.
2. Snapshot exactly the manifest-listed old files and old receipt to the existing private backup area. Record which targets were absent. Preserve all other files, including CLI-authored campaigns. Never replace a whole campaign directory, glob-copy marketing, or use `rsync --delete`.
3. Immediately before promotion, revalidate every expected live hash (or continued absence), reject symlinks again, and validate every staged SHA256. A hash check alone is not a cross-process lock: live CLI schedule writers must be quiescent or honor the same lock throughout check/promotion. If that cannot be established, stop the release; do not claim race-free schedule preservation.
4. Write each new file into a temporary sibling in the private destination, verify bytes, then `rename` it to its exact destination. Rename is atomic per file; the collection is **not** one atomic transaction. Record each successful promotion in the rollback journal. Install the receipt last, after all files pass hash checks. The existing canonical error trap must cover the entire promotion, including the first file.
5. On any later release failure, restore only journaled files from their exact backups (using sibling temporary file plus rename), remove only exact targets proven absent before this release, and restore/remove the previous receipt accordingly. Never erase a newer concurrent external write: compare target with the just-promoted hash before rollback; a mismatch is a reconciliation failure requiring intervention.
6. Add source SHA, manifest SHA256 and backup/journal locations to `.backend-release`. Retain campaign backups with the rest of the canonical rollback evidence.

The exact-file helper implements backup/journal, hash checks, per-file atomic rename and drift-refusing rollback. Its lock coordinates callers of this helper; existing external CLI writers are not proven to share that lock. Canonical integration must establish writer quiescence for race-free promotion. No media deployment is added. Campaign image buttons may still return an honest missing-file result until a separate exact approved media package exists.

## Local evidence

- `php -l scripts/stage-campaign-release.php`: pass.
- `php scripts/test-stage-campaign-release.php`: 29 assertions pass in disposable local Git fixtures: exact source bytes/SHA/hash, allowlist exclusion, invalid/abbreviated commit, existing stage, untracked candidate, dirty source, invalid JSON, Git symlink, invalid/traversal-style slug, uncontrolled live change, prior-hash compare contract, post-receipt writer drift, live symlink and failed-validation output absence; first install, unrelated live preservation, exact absent-file rollback, receipt restoration, tracked update, external-writer rollback refusal, stage-to-promote drift and tampered package refusal.
- Actual checkout dry run at `f750a163d418f8fbda4aa601165fe70c31f4545f`: 17 allowlisted JSON files staged into `/private/tmp/famtastic-campaign-json-dryrun-20260930`, no live root, no network. The 17 is a file count, not the historic 17 campaign days.
- Tests make commits only in disposable fixture repositories; no task-repository commit was made.

Owned source: `scripts/stage-campaign-release.php`, `scripts/campaign-release-files.php`, `scripts/test-stage-campaign-release.php`, this handoff. Canonical deploy edits, integrated rollback tests, shared documentation mirrors and release approval belong to the integration owner.

## Canonical promotion/error-trap insertion

Only after `rollback_code` is defined and its ERR trap installed, call:

```bash
php "$source_dir/scripts/campaign-release-files.php" --action promote \
  --package "$campaign_package" --live-root "$production_dir" --backup "$campaign_backup"
```

Inside `rollback_code`, before retention deletes any source helper/package/backup:

```bash
if [[ -f "$campaign_backup/journal.json" ]]; then
  php "$source_dir/scripts/campaign-release-files.php" --action rollback \
    --live-root "$production_dir" --backup "$campaign_backup" || {
      echo "Campaign rollback requires reconciliation; newer live data was preserved." >&2
    }
fi
```

Do not suppress that reconciliation failure in the deployment receipt or final report. Promotion writes a durable journal before its first destination change and attempts rollback itself on mid-promotion failure. Canonical rollback is repeatable and skips already-restored exact old hashes. Preserve the package/backup until successful verification. A completed helper promotion is file-copy proof only, never provider scheduling/publishing proof.
