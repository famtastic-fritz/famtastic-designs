# Inbound mailbox repair

Purpose: Restore reliable inbound client replies without consuming historical mail or sending responses.

Goal

Drupal owns bounded discovery, correlation, retries, deduplication, draft state and health; a single cPanel clock calls its dedicated Drush command. Prove exactly one synthetic reply, backlog preservation and rollback.

Tasks
- [x] Re-anchor source and inspect production scheduler/mail counts.
- [x] Implement Drupal-owned mail scan and exact outbound correlation.
- [x] Add local acceptance, scheduler-loss incident and deployment guard.
- [ ] Commit, push and deploy exact reviewed source with backups.
- [ ] Prove production correlation, replay, backlog preservation and no sends.
- [ ] Close review evidence and documentation mirrors.

Status: active
Started: 2026-10-04
Ended: pending
Execution: codex/inbound-mail-repair; worktree /Users/famtastic-fritz/Development/FAMtastic/worktrees/inbound-mail-repair; land on main. Existing marketing lane preserved.
Research: Existing support scanner absent from production cron; hello has 55 new messages; support has zero. External clock currently calls bounded observe-only automation-tick. Current origin/main 8be9ecd3; backend marker bb91a07b with separate scoped releases.
Review: Pending local and hosted evidence. Owner acceptance pending.
Skills: famtastic-build-review; client-owner-training; agency-agents engineering/engineering-devops-automator at 765be42358100bf89d2faa567668a94c602f9a26.
Tracking: claude-mem work_state tools are not exposed in this session; this brief is the resumability fallback.

Proof

Production read-only preflight 2026-10-04: Drupal 11.4.5 / PHP 8.3.33 / connected database; exact bounded observe cron preserved. No mailbox bodies read or mail moved.
