---
name: famtastic-designs-deploy
description: Orchestrate agent-agnostic production deployments for famtasticdesigns.com. Use for requests to deploy, publish, release, roll back, repair, or verify the FAMtastic Designs site on GoDaddy from Shay, Hermes, Codex, Claude, or another authorized agent.
---

# FAMtastic Designs Deploy

Treat the active user/task as the deployment authority. Treat the Git repository
as the source of truth and `scripts/deploy-frontend-godaddy.sh` as the execution
primitive, regardless of which agent runs it.

## Workflow

1. Work in `/Users/famtasticfritz/famtastic/sites/site-famtastic-designs`.
2. Read `AGENTS.md` and `docs/FRONTEND_DEPLOYMENT.md`.
3. Require source changes to be committed and merged through Git.
4. Run `./scripts/deploy-frontend-godaddy.sh` and review the read-only preflight.
5. Report the commit, server runtime, private build path, backup plan, and
   preflight result.
6. Obtain explicit deployment authorization unless the active user request
   already clearly authorizes production deployment.
7. Run `./scripts/deploy-frontend-godaddy.sh --apply`.
8. Capture the printed remote backup path and deployed commit.
9. Verify apex and `www` with a real browser, including console, exceptions,
   failed requests, MIME types, final URL, and nonempty `#root`.
10. Record the result in the active task/session.

## Delegation rules

- Shay, Hermes, Codex, Claude, and other agents use the same workflow.
- Any agent may build, review, test, and run the dry-run.
- Any agent explicitly authorized by the user/task may execute `--apply`.
- Do not invent a separate upload command or flatten `dist/`.
- Build the exact Git commit outside `public_html`; never use its legacy
  production checkout as the source workspace.
- Record deployment evidence in the active task for the next agent.

## Hard stops

- Dirty or uncommitted worktree.
- Build failure or raw `/src/` reference in `dist/index.html`.
- Transfer preview that touches Drupal/runtime files.
- Missing remote backup.
- Use of `rsync --delete` against `public_html`.
- Failed MIME, browser, console, or network acceptance.
- Unknown production commit or ambiguous authorization.
