# Repository source of truth

## Decision

FAMtastic Designs has one supported application stack:

| Responsibility | Canonical path | Generated or deployed form |
| --- | --- | --- |
| Public site and customer portal | `frontend/src/` | `frontend/dist/` then GoDaddy `public_html/` |
| Static frontend assets | `frontend/public/` | copied into `frontend/dist/` by Vite |
| API, CMS, and customer pipeline | `backend/` | Drupal runtime on GoDaddy |
| Deployment and acceptance | `scripts/` | commands run against an exact Git SHA |
| Website-delivery swarm contracts | `website-delivery-swarm/` | Shay-callable runner and evidence artifacts |
| Governed marketing campaigns and social-presence baselines | `marketing/campaigns/` | Draft manifests, creative source, evidence packages, explicitly unlisted proofs, and approved public Lab case studies |
| Portable marketing engine seed | `marketing/engine/` | Provider-neutral contracts only; no brand, customer, Drupal, credential, or campaign truth |
| Repository agent skills | `agent-skills/` | versioned callable procedures |
| Operational guidance | `docs/` | repository documentation |

The former root Nuxt/AgencyOS application and local Directus prototype are
historical only. They were removed after the React/Vite plus Drupal stack was
accepted as the production baseline. Git history remains the archive.

## Change flow

1. Start from the latest `origin/main` in a clean branch or worktree.
2. Change canonical source under `frontend/`, `backend/`, `scripts/`, or `docs/`.
3. Build and run the tests appropriate to the changed area.
4. Commit the complete change and merge it to `main`.
5. Deploy the exact committed SHA through the repository deployment scripts.
6. Verify the release marker, both apex and `www` frontends, and affected APIs.

No agent or person should edit `public_html`, upload a flattened build, maintain
a parallel deployment procedure, or treat generated output as an independent
source. Shay is the usual deployment orchestrator, but the procedure is
agent-agnostic and authorization comes from the task owner.

## Local commands

```bash
npm --prefix frontend ci
npm --prefix frontend run dev
npm --prefix frontend run build
```

For the complete containerized stack:

```bash
docker compose up --build
```

Backend-only setup is documented in `backend/README.md`. Production procedures
are documented in `docs/FRONTEND_DEPLOYMENT.md` and
`docs/BACKEND_DEPLOYMENT.md`.

## Guardrails

- A production deployment requires a clean worktree and committed SHA.
- Never run `git pull` or application builds inside `public_html`.
- Never flatten `frontend/dist/`; preserve `dist/assets/`.
- Never use `rsync --delete` against `public_html` because Drupal shares it.
- Never commit secrets, local databases, uploaded files, generated proofs, or
  dependency directories.
- Campaign evidence committed under `marketing/campaigns/` must be an explicitly
  selected golden baseline with rights and disclosure review; ordinary generated
  customer proofs continue to live under ignored artifact storage.
- A public Lab case study may use the isolated allowlisted static publisher only
  for its exact reviewed campaign source. It does not authorize a dirty-worktree
  React deployment, broad content publication, or direct customer-state writes.
- The reusable lean social-presence recipe is
  `docs/architecture/LEAN_SOCIAL_PRESENCE_PRODUCTION_PROCESS_V1.md`; an individual
  campaign directory is evidence and an exemplar, not the canonical process.
- If documentation or automation contradicts this file, stop and reconcile it
  before deployment.
