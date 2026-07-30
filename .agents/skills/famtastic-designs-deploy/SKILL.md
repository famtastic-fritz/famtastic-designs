---
name: famtastic-designs-deploy
description: Orchestrate safe production deployments for famtasticdesigns.com through Shay or the Hermes host body. Use for requests to deploy, publish, release, roll back, repair, or verify the FAMtastic Designs site on GoDaddy, including delegated deployment work from Codex, Claude, or other agents.
---

# FAMtastic Designs Deploy

Treat Shay as the deployment authority. Treat the Git repository as the source
of truth and `scripts/deploy-frontend-godaddy.sh` as the execution primitive.

## Workflow

1. Work in `/Users/famtasticfritz/famtastic/sites/site-famtastic-designs`.
2. Read `AGENTS.md` and `docs/FRONTEND_DEPLOYMENT.md`.
3. Require source changes to be committed and merged through Git.
4. Run `./scripts/deploy-frontend-godaddy.sh` and review the dry-run transfer.
5. Report the commit, changed files, backup plan, and dry-run result.
6. Obtain explicit deployment authorization unless the active user request
   already clearly authorizes production deployment.
7. Run `./scripts/deploy-frontend-godaddy.sh --apply`.
8. Capture the printed remote backup path and deployed commit.
9. Verify apex and `www` with a real browser, including console, exceptions,
   failed requests, MIME types, final URL, and nonempty `#root`.
10. Record the result in the active Shay task/session.

## Delegation rules

- Codex, Claude, Hermes workers, and other agents may build, review, test, and
  run the dry-run.
- They must not invent a separate upload command or flatten `dist/`.
- They must hand deployment evidence back to Shay.
- Only Shay executes `--apply` by default. Another agent may do so only when the
  user explicitly appoints that agent for the production deployment.

## Hard stops

- Dirty or uncommitted worktree.
- Build failure or raw `/src/` reference in `dist/index.html`.
- Transfer preview that touches Drupal/runtime files.
- Missing remote backup.
- Use of `rsync --delete` against `public_html`.
- Failed MIME, browser, console, or network acceptance.
- Unknown production commit or ambiguous authorization.
