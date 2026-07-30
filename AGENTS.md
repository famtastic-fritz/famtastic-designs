# Agent Instructions

## Package Managers
- Root Nuxt app: use **pnpm**.
- React frontend in `v2/frontend`: use **npm** and its committed `package-lock.json`.

## Production Frontend
- Shay is the primary production deployment orchestrator and authority.
- Other agents may implement, review, test, and run deployment dry-runs.
- Hand production evidence back to Shay; do not create a parallel deployment lane.
- Only Shay runs `--apply` by default. Another agent needs explicit user authorization.
- Canonical source: `v2/frontend`.
- Canonical build output: `v2/frontend/dist`.
- Production document root: `~/public_html` on GoDaddy.
- Never flatten `dist`; `dist/assets/*` must deploy as `public_html/assets/*`.
- Never upload source directories, run `git pull` in `public_html`, or edit production files manually.
- Never use `rsync --delete` against `public_html`; it also contains Drupal and other runtime files.
- Follow `docs/FRONTEND_DEPLOYMENT.md` for every production change.
- Use `./scripts/deploy-frontend-godaddy.sh`; all orchestrators call the same primitive.
- A production deployment requires a clean Git worktree and a committed SHA.
- Verify both `https://famtasticdesigns.com` and `https://www.famtasticdesigns.com` with a real browser after deployment.

## Frontend Commands
| Task | Command |
|---|---|
| Install | `npm --prefix v2/frontend ci` |
| Develop | `npm --prefix v2/frontend run dev` |
| Build | `npm --prefix v2/frontend run build` |
| Preview | `npm --prefix v2/frontend run preview -- --host 127.0.0.1` |
| Deploy dry run | `./scripts/deploy-frontend-godaddy.sh` |
| Deploy | `./scripts/deploy-frontend-godaddy.sh --apply` |

## Commit Attribution
AI commits MUST include:
```text
Co-Authored-By: (the agent model's name and attribution byline)
```
