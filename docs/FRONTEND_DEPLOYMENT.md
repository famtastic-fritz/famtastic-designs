# Frontend Deployment

## Deployment contract

The React frontend in `frontend` is the canonical public frontend. Git tracks
its source and the deployment tooling; Vite's generated `dist/` directory stays
untracked.

Shay is the usual deployment orchestrator, but the lane is agent-agnostic.
Codex, Claude, Hermes workers, Shay, and other agents use the same commands,
safeguards, and evidence contract. Any agent may prepare, review, build, verify,
and dry-run. Any agent explicitly authorized by the user or active task for the
production change may run `--apply`.

Authorization is based on the task, not agent identity. This is one lane, not
separate Shay/Codex/Hermes implementations. Every orchestrator invokes the same
Git-tracked script and receives the same backup, transfer, and verification
behavior.

Production is a mixed GoDaddy document root containing the frontend, Drupal,
and runtime files. It is a runtime target, not a source checkout. The exact
Git commit is checked out and built in `~/deploy/famtastic-designs`, outside
`public_html`. Deploy only the contents of the resulting `frontend/dist/`,
preserve its directory structure, and never use `rsync --delete`.

The artifact boundary has identical relative paths:

```text
frontend/dist/index.html       -> public_html/index.html
frontend/dist/assets/<file>    -> public_html/assets/<file>
frontend/dist/contact/index.html -> public_html/contact/index.html
frontend/dist/<route>/index.html -> public_html/<route>/index.html
```

Do not flatten the artifact and do not create production symlinks to compensate
for an incorrect transfer. If `index.html` requests `/assets/<file>`, that file
must physically exist at `public_html/assets/<file>`.

## Local development

```bash
npm --prefix frontend ci
npm --prefix frontend run dev
```

Production-build verification:

```bash
npm --prefix frontend run build
npm --prefix frontend run preview -- --host 127.0.0.1
```

Open the preview in a real browser. Confirm that `#root` is populated and that
there are no uncaught exceptions or failed JavaScript/CSS requests.

## Production deployment

Deploy only from a clean worktree at a committed SHA.

First run the read-only local and remote preflight:

```bash
./scripts/deploy-frontend-godaddy.sh
```

Review the file list, then apply it:

```bash
./scripts/deploy-frontend-godaddy.sh --apply
```

The script:

1. requires a clean worktree whose `HEAD` exactly equals GitHub `main`;
2. checks remote Git, Node/NVM, npm, rsync, disk, and repository access;
3. checks out the exact commit in a private server-side release directory;
4. selects the repository-pinned Node version and runs `npm ci` plus the build;
5. rejects raw `/src/` references or missing compiled assets;
6. creates a timestamped remote backup of `index.html`, `assets/`, and the
   previous release record;
7. promotes assets, route-specific SEO shells, and other non-root files first,
   verifies each route shell byte-for-byte, then installs the root `index.html`
   last;
8. preserves directory structure and never deletes unrelated runtime files;
9. records the commit, timestamp, Node version, and backup path in
   `~/public_html/.frontend-release`;
10. verifies live asset status codes and MIME types.

This is a server-side **build**, not a Node application server. Apache still
serves the generated static files. Node is needed only during deployment.

Override infrastructure values only when the hosting account changes:

```bash
FAMTASTIC_SSH_TARGET=user@host \
FAMTASTIC_REMOTE_ROOT=public_html \
FAMTASTIC_REMOTE_DEPLOY_BASE=deploy/famtastic-designs \
FAMTASTIC_REPOSITORY_URL=https://github.com/OWNER/REPOSITORY.git \
./scripts/deploy-frontend-godaddy.sh --apply
```

## Browser acceptance

After every applied deployment, test both:

- `https://famtasticdesigns.com`
- `https://www.famtasticdesigns.com`

For each hostname, record the final URL and confirm:

- `#root` is nonempty;
- the main heading renders;
- JavaScript is served as JavaScript and CSS as CSS;
- there are no page exceptions, console errors, or failed asset requests.

HTTP 200 alone is not acceptance: the SPA fallback can return `index.html` with
status 200 for a missing asset.

## Rollback

The apply command prints the timestamped backup path. Restore only the frontend
files from that backup:

```bash
ssh "$FAMTASTIC_SSH_TARGET"
tar -xzf ~/backups/famtastic-frontend-TIMESTAMP.tgz -C ~/public_html
```

Then repeat browser acceptance on both hostnames. The release record contains
the exact archive path. Do not replace Drupal or the rest of `public_html`
during a frontend rollback.

## Historical incident

The evidence, timeline, and architectural correction for the flattened-assets
failure are documented in
[`INCIDENT-2026-07-30-BLANK-PAGE-RCA.md`](INCIDENT-2026-07-30-BLANK-PAGE-RCA.md).
