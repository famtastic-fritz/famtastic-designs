# Git sync and release discipline

## Purpose

FAMtastic work moves through multiple agents, worktrees, and occasional direct
owner commits. Staying current is an explicit operating step. GitHub history is
the collaboration source; production is updated only by deploying an exact
reviewed commit through the checked-in scripts.

## Required sync points

Fetch and inspect remote state:

1. at the start of a meaningful implementation session;
2. before integrating, committing, or pushing a handoff;
3. immediately before a deployment preflight; and
4. again before `--apply` if the preflight and apply are not one continuous
   reviewed operation.

Use a clean worktree and inspect divergence before changing history:

```bash
git fetch --all --prune
git status --short --branch
git rev-list --left-right --count origin/main...HEAD
git log --oneline --decorate HEAD..origin/main
git diff --stat HEAD..origin/main
```

Read the incoming commit subjects and affected files. Do not resolve a conflict
by discarding work you have not understood. Preserve unrelated owner and agent
changes, and rerun the acceptance checks after reconciliation.

## Pull and rebase rules

- Do not run uncontrolled scheduled pulls. A dirty worktree, an in-progress
  rebase, or a concurrently edited branch needs human-readable reconciliation,
  not automation.
- Prefer a fresh worktree from current `origin/main` for new work.
- If an existing feature branch is behind, fetch and deliberately rebase or
  merge according to that branch's collaboration state.
- Never force-push `main`. After intentionally rebasing an already-published
  feature branch, use only `git push --force-with-lease` and report that the
  feature history changed.
- Commit code, tests, contracts, and required documentation together when they
  describe one behavior. Do not leave the operating doctrine pointing at an
  uncommitted local implementation.

## Push rules

Before push:

```bash
git fetch --all --prune
git rev-list --left-right --count origin/main...HEAD
git status --short
```

The branch must not be unknowingly behind `origin/main`; the relevant tests and
`git diff --check` must pass; and no secrets, local evidence, generated customer
proofs, or unrelated user files may be staged. Push the reviewed commit and
report its SHA and branch.

A pushed feature branch is not production. Merge or fast-forward the approved
change to `main` through the repository's normal integration path before a
production deployment.

## Production parity

- Never `git pull`, build, or edit inside `public_html`.
- Deploy only a clean, pushed SHA that is reachable from current
  `origin/main`, using `scripts/deploy-frontend-godaddy.sh` and/or
  `scripts/deploy-backend-godaddy.sh`.
- Deployment still requires explicit production authorization. A request to
  commit, push, or “keep things synced” is not automatically an `--apply`
  authorization.
- Verify the deployed release marker equals the intended Git SHA, then perform
  the affected apex/`www`, API, authenticated, mobile, and browser checks.
- “GitHub is current,” “the deployment script completed,” and “production was
  browser-proven” are three different evidence statements. Report them
  separately.

If local, remote, and production SHAs disagree, stop and reconcile the lineage
before new production work. Do not patch the server to make the mismatch less
visible.
