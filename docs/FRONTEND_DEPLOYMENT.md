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
and runtime files. Deploy only the contents of `frontend/dist/`, preserve
its directory structure, and never use `rsync --delete`.

The artifact boundary has identical relative paths:

```text
frontend/dist/index.html       -> public_html/index.html
frontend/dist/assets/<file>    -> public_html/assets/<file>
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

First inspect the exact transfer without changing production:

```bash
./scripts/deploy-frontend-godaddy.sh
```

Review the file list, then apply it:

```bash
./scripts/deploy-frontend-godaddy.sh --apply
```

The script:

1. requires a clean Git worktree;
2. runs `npm ci` and the Vite production build;
3. rejects raw `/src/` references or missing compiled assets;
4. creates a timestamped remote backup of `index.html` and `assets/`;
5. transfers `dist/` without flattening directories or deleting unrelated files;
6. records the deployed commit in `~/public_html/.frontend-release`;
7. verifies live asset status codes, MIME types, and response bodies.

Override the SSH destination only when the hosting account changes:

```bash
FAMTASTIC_SSH_TARGET=user@host \
FAMTASTIC_REMOTE_ROOT=public_html \
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
cd ~
tar -xzf backups/famtastic-frontend-TIMESTAMP.tgz
```

Then repeat browser acceptance on both hostnames. Do not replace Drupal or the
rest of `public_html` during a frontend rollback.
