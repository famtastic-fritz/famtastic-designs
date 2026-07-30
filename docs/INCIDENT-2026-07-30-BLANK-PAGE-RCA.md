# Blank-page incident root-cause analysis

## Executive finding

The July 30 blank page was caused by a malformed July 23 deployment artifact,
not by different source-folder layouts and not by a need for symlinks.
`index.html` correctly requested:

```text
/assets/index-D_uAwkFB.js
/assets/index-DiIN1rSa.css
```

The deployment script copied the *contents* of `dist/assets` into
`public_html`, producing:

```text
public_html/index-D_uAwkFB.js
public_html/index-DiIN1rSa.css
```

Requests to the correct `/assets/...` URLs therefore missed the files. The SPA
fallback returned `index.html` with HTTP 200 and `text/html` for the JavaScript
request. Browsers refuse to execute HTML as a JavaScript module, so React never
mounted and the empty `#root` appeared as a blank page.

## Evidence and timeline

All server timestamps below are GoDaddy server time (`-07:00`) on July 23,
2026:

| Time | Evidence |
|---|---|
| 14:24:38 | `~/.nvm` and Node `v26.5.0` were installed. |
| 14:26:08 | `public_html` changed from `master` to `production`. |
| 14:26:56 | Git commit `1654cf22` recorded the deployment. |
| 14:27:00 | `index.html`, root-level JS/CSS bundles, and the production reset all landed. |
| July 30 11:19 | The emergency repair restored the same bundles under `public_html/assets`. |

The responsible historical script was introduced in `7825009e`. Its line 94
used:

```bash
cp -r v2/frontend/dist/assets/ .
```

Because the source ends with `/`, this copies the directory's children into
the current directory. The deployment commit confirms the result: it added
`index-D_uAwkFB.js` and `index-DiIN1rSa.css` at repository root while
`index.html` referenced `/assets/...`.

The evidence supports the user's recollection: Node was installed immediately
before the Git-based deployment was created. The workflow did begin building
locally, then used a production branch to commit flattened build output and
reset the live document root to it. Installing Node on the server did not
update that workflow, and the workflow itself contained the flattening bug.

## Contributing design failures

1. `public_html` was both a Git source checkout and a mixed runtime directory.
   It contains frontend files, Drupal, Composer dependencies, and hosting
   files. A Git reset there cannot safely model ownership of the whole tree.
2. Generated build output was committed to a special `production` branch,
   creating a second source of truth separate from `main`.
3. The deploy process did not validate that every asset referenced by
   `index.html` existed at the same relative production path.
4. HTTP 200 was treated as success even though the SPA fallback can return
   HTML for a missing JavaScript file.
5. Node was not pinned, so local and remote builds could silently use different
   major versions.
6. There was no atomic ordering rule. Updating `index.html` before its assets
   can create a temporary blank page even during an otherwise correct deploy.

## Sustainable target architecture

Git `main` is the only source of truth. Deployment builds the exact current
`main` commit on GoDaddy in a private directory outside `public_html`:

```text
~/deploy/famtastic-designs/repository.git
~/deploy/famtastic-designs/releases/<commit>/source
```

The repository `.nvmrc` pins Node 22 LTS. The server uses `npm ci` and
`npm run build`, validates `frontend/dist`, creates a frontend-only backup,
copies versioned assets first, and installs `index.html` last. It records the
commit, timestamp, Node version, and backup in
`public_html/.frontend-release`.

`public_html` remains a runtime target, not a development checkout. The
existing checkout and historical root-level bundles should be cleaned only in
a separate, backed-up maintenance change after Drupal/frontend ownership is
fully enumerated. They are not required by the new lane and must not be
deleted casually.

## Corrective and preventive controls

- One Git-tracked deployment script for Shay, Hermes, Codex, and humans.
- Clean worktree and exact equality with remote `main`.
- Read-only preflight by default; explicit `--apply` for production mutation.
- Private server-side checkout and build; no source promotion to `public_html`.
- Node runtime pinned by `.nvmrc` and `package.json`.
- Artifact-reference validation before promotion.
- No flattening, symlinks, or `rsync --delete`.
- Assets first and `index.html` last.
- Frontend-only backup and machine-readable release record.
- MIME verification plus mandatory real-browser acceptance on apex and `www`.

## Five whys

1. **Why was the page blank?** React's module bundle did not execute.
2. **Why did it not execute?** `/assets/...js` returned HTML rather than
   JavaScript.
3. **Why was the asset missing?** The deploy copied `dist/assets/*` to the
   document root instead of preserving the `assets/` directory.
4. **Why was that not caught?** The deployment tested availability/status, not
   path equivalence, MIME type, or browser rendering.
5. **Why could one script error persist?** Build artifacts were committed to a
   production branch and reset directly inside a mixed document root, with no
   single validated release boundary.
