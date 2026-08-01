# FAMtastic Designs

This repository is the canonical source for the live FAMtastic Designs platform.

## Architecture

```text
frontend/             React 18 + Vite public site and customer portal
backend/              Drupal 11 API and autonomous customer pipeline
scripts/              Acceptance and deployment commands
docs/                 Current architecture, operations, and release records
docker-compose.yml    Local full-stack environment
```

The former root-level Nuxt/AgencyOS/Directus prototype has been removed. It is
available in Git history but is not a build, runtime, or deployment source.

## Local development

```bash
npm --prefix frontend ci
npm --prefix frontend run dev
```

The Vite development server runs at `http://127.0.0.1:5173`. See
[`frontend/.env.example`](frontend/.env.example) for frontend configuration and
[`backend/README.md`](backend/README.md) for the Drupal development environment.

For the containerized stack:

```bash
docker compose up --build
```

## Verification

```bash
npm --prefix frontend run build
./scripts/acceptance-autonomous-pipeline.sh
```

GitHub Actions also builds the frontend, validates and audits the backend, and
runs the autonomous pipeline acceptance suite.

## Deployment

Production is deployed only from a clean, committed Git SHA:

```bash
./scripts/deploy-frontend-godaddy.sh
./scripts/deploy-backend-godaddy.sh
```

The commands above run preflight checks. Add `--apply` only when the production
change is explicitly authorized. Read
[`docs/FRONTEND_DEPLOYMENT.md`](docs/FRONTEND_DEPLOYMENT.md) and
[`docs/BACKEND_DEPLOYMENT.md`](docs/BACKEND_DEPLOYMENT.md) before applying a
release.

## Source-of-truth contract

See [`docs/SOURCE_OF_TRUTH.md`](docs/SOURCE_OF_TRUTH.md). The short version is:

- `frontend/` is the only public frontend source.
- `frontend/dist/` is generated output, never an independently edited source.
- `backend/` is the only production API/CMS source.
- Git `main` and a committed SHA are authoritative; `public_html` is a deployed
  artifact, not a development workspace.
