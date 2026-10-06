# October6 current-main reconciliation

Status: candidate; final committed revision and hosted proof are recorded in OWNER_HANDOFF_2026-10-06.md. User explicitly authorized commit and make-live on October6.

Starting source: refreshed main044c1010353710481564d30b7c8ca13ddf4bebc8. Production backend matched that revision, deployed11:03:15Z, with durable pilot_exact_dispatch_only=true, broad scheduler counts0, legacy claimable0. PR49afc8df9220a7da9389893a014cca2ba759ee76a0 was open and unmerged. Original worktree was absent; canonical dirty checkout was preserved.

## Reconciliation

Migration8066 had since been used for invoice/handoff and8067 for acquisition. Preserved both, merged schema unions, allocated8068 to staff drafts/planning. Preserved acquisition reply/contact-stop hooks and frozen approved-message boundaries. Routes/services and prior documentation histories are additive.

Current deployer intentionally materializes backend-source without Git metadata. It now extracts stage-campaign-release.php from the same verified bare-mirror commit; helper reads validated allowlisted JSON blobs from that exact commit. Worktree packaging still rejects dirty/untracked files. Immutable snapshot remains tied to module marker and rollback; mutable CLI schedules are untouched. Existing backups, dependencies, SMTP, cron and acquisition safeguards remain intact.

## Current checks

- Exact composer.lock installed locally; strict validation passes.
- PHPUnit396tests2470assertions pass (1deprecation/70PHPUnitdeprecations reported).
- Installed Drupal SQLite runtime /private/tmp/famtastic-selected-drupal.Tb2Y5h with empty inherited credentials, memory mail and disabled network/provider transports.14persistent workflow/AI-double checks pass.
- Actual updatedb from stored schema8067 after removing3newstaff tables and planning/label fields applies only8068. Campaign/thread counts preserved; invoice/acquisition tables retained; second hook idempotent; updatedb:status empty.
- Bare mirror/backend archive packaging34checks;3form serialization checks; snapshot priority/rollback and safe database-error message checks pass.
- PHP install lint, shell syntax, git diff --check pass.

## Release execution

Use clean exact current-main committed source only. Before apply fetch and run canonical deployer preflight with FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY=1. Preserve existinglocktrue/zeroschedulers and do not suspend, enable, clear or enroll any work. Script creates code/database/dependency backups; verify backup existence and marker, schema8068, Composer audit, routes and owner UI after apply. No paid AI inference, SMTP send, campaign publication or owner acceptance is implied.

Independent reviewer must approve final reconciled source before commit/deploy. Owner-task acceptance remains pending, distinct from hosted inspection.

Agency reference: engineering/engineering-git-workflow-master, pinned765be42358100bf89d2faa567668a94c602f9a26.
