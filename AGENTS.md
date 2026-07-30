# Agent Instructions

## Package Managers
- Root Nuxt app: use **pnpm**.
- React frontend in `frontend`: use **npm** and its committed `package-lock.json`.

## Production Frontend
- Shay is the usual deployment orchestrator, but the deployment lane is agent-agnostic.
- Any agent may implement, review, test, and run deployment dry-runs.
- Any agent explicitly authorized for the production change may run `--apply`.
- Authorization comes from the user/task, never from the agent's identity.
- Report production evidence to the active task; do not create a parallel deployment lane.
- Canonical source: `frontend`.
- Canonical build output: `frontend/dist`.
- Canonical runtime: repository `.nvmrc`; server builds must use it.
- Production document root: `~/public_html` on GoDaddy.
- Build the exact Git commit in `~/deploy/famtastic-designs`, outside `public_html`.
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
| Install | `npm --prefix frontend ci` |
| Develop | `npm --prefix frontend run dev` |
| Build | `npm --prefix frontend run build` |
| Preview | `npm --prefix frontend run preview -- --host 127.0.0.1` |
| Deploy preflight | `./scripts/deploy-frontend-godaddy.sh` |
| Deploy | `./scripts/deploy-frontend-godaddy.sh --apply` |

## Commit Attribution
AI commits MUST include:
```text
Co-Authored-By: (the agent model's name and attribution byline)
```
