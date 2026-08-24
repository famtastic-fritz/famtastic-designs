# Agent Instructions

## Standing rule: documentation sync (mandatory before session close)

After any meaningful work session, update ALL FOUR surfaces — no exceptions:

1. `docs/CHANGELOG.md` — dated entry, one bullet per shipped change.
2. `docs/CAPABILITY_REGISTRY.md` — only when evidence actually changed;
   never upgrade a classification without proof; connection-proven ≠ publish-proven.
3. `.site-context/SITE-LEARNINGS.md` — when operational lessons, failure modes,
   or operator guidance emerged (dated entry, observation + guidance format).
4. Google Drive mirror — write a dated status summary to
   `~/Library/CloudStorage/GoogleDrive-fritz.medine@gmail.com/My Drive/FAMtastic/famtasticdesigns.com/`
   (`.gdoc` files cannot be edited locally; create synced files instead).

The repo is the source of truth; Drive is the mirror. If a surface can't be
updated, say so explicitly in the session report rather than skipping silently.

## Required operating context

- Read `docs/AGENT_OPERATING_CONTRACT.md` before product, customer, Commerce,
  intake, mail, proof, or deployment work. It applies equally to Codex, Claude,
  Shay, and every other CLI agent.
- Use `docs/CAPABILITY_REGISTRY.md` to distinguish implemented, provider-proven,
  and production-proven capabilities.
- FAMtastic Concierge is the customer-facing communications identity; FAMtastic
  Connections is the durable lead/status projection. Read
  `docs/architecture/FAMTASTIC_CONNECTIONS_CONCIERGE_CONTRACT_V1.md` before
  changing Concierge, Inkbox, Solution Finder follow-up, or Site Studio work
  intake. Never put credentials in a CLI configuration, commit them, or let an
  agent autonomously send, quote, grant, charge, purchase a domain, or deploy.
- For every creative proof, selected-direction refinement, campaign experience,
  or Site Studio-bound build, follow `docs/architecture/BUILD_DNA_STANDARD_V1.md`.
  Create and validate one `famtastic.build-dna.v1` record at run creation;
  journal the real provider/model status, prompts or prompt artifacts, inputs,
  outputs, hashes, timing, costs, fallback, reviewer decision, and retrieval
  locations as each stage happens. Register the Drupal projection before an
  eligible handoff and copy the same record into the Site Studio packet. Never
  guess missing model identity, duration, cost, prompt, or session information.
- Route designed motion, proof walkthroughs, and reusable social cutdowns through
  HyperFrames; route fast, draft-only narrated social/video assemblies through
  MoneyPrinterTurbo. Neither tool is a publishing authority or a substitute for
  a creative-proof reviewer. Record the provider/model, source assets, render
  command, duration, cost status, hashes, QA, and approval state in the same
  Build DNA record before handing a media artifact to a customer, campaign, or
  Site Studio.
- For marketing strategy, demand generation, blog series, SEO content, FAQs,
  CTAs, product explanations, or pricing recommendations, read
  `docs/DEMAND_ENGINE_DOCTRINE.md` and use
  `agent-skills/famtastic-demand-engine/SKILL.md`.
- Installed `blog-*`, `seo-*`, and marketing skills are supporting references.
  FAMtastic's repository contracts override generic skill guidance.
- The project-shared marketing core lives in `.agents/skills/` and begins with
  `.agents/product-marketing.md`. For marketing, conversion, intake, campaign,
  measurement, or sales-handoff work, read that context and then load only the
  narrowest relevant specialist (`cro`, `signup`, `onboarding`, `popups`,
  `revops`, `ad-creative`, `marketing-ideas`, `marketing-loops`,
  `sales-enablement`, `ai-seo`, `analytics`, `ab-testing`, `social`,
  `site-architecture`, `schema`, or `offers`). These are version-pinned
  upstream references, not independent sources of FAMtastic product truth;
  `docs/DEMAND_ENGINE_DOCTRINE.md`, the capability registry, products, terms,
  and approval gates take precedence.
- New generated marketing content is draft-first. Live prices, recurring
  charges, legal promises, real promotional sends, advertising spend, and
  broad publication remain explicit approval gates.
- For HeyGen campaign video work, use the official skills in
  `.agents/skills/heygen-*` and `docs/marketing/HEYGEN_CAMPAIGN_AUTOMATION.md`.
  Codex, Claude Code, and Shay share one brief, asset ledger, QA gate, and
  publishing record; never create an untracked parallel video workflow.
- For campaign automation, read
  `docs/marketing/FAMTASTIC_MARKETING_FLOW_2026-08-12.md` and use the stable
  content IDs in `marketing/campaigns/55-cents-17-day/manifest.json`. Local
  models may draft and classify; they may not approve claims or publish. Never
  commit Poe, HeyGen, scheduler, social OAuth, or customer-list credentials.
- For local or Poe-backed model work, read
  `docs/marketing/LOCAL_MODEL_AND_AGENT_ROUTING_2026-08-12.md` and route through
  `marketing/local-models.json`. The same contract applies to Shay, Claude,
  Codex, and scripts. A model name ending in `:cloud` is cloud execution even
  when invoked through a local CLI; never report it as private/local inference.
- The marketing engine incubates here but must respect
  `docs/architecture/MARKETING_ENGINE_INCUBATION_AND_EXTRACTION.md`. Keep
  provider-neutral code in `marketing/engine`, FAMtastic brand configuration in
  `marketing/brands/famtastic`, and campaign/customer/Drupal truth outside the
  portable boundary. Run `python3 scripts/campaign-readiness.py` before saying
  campaign production is ready.
- Before choosing a paid creative, document, video, model, storage, publishing,
  or analytics provider, read `marketing/providers.json` and
  `docs/marketing/ADOBE_SUITE_CONNECTION_MAP_2026-08-13.md`. This provider
  registry applies equally to Codex, Claude, Shay, local agents, and scripts.
  Reuse an available subscription before proposing a new paid service. Never
  infer API entitlement from a consumer subscription, never commit credentials,
  and record provider-produced artifacts in the canonical campaign evidence.

## Package Managers

- React frontend in `frontend`: use **npm** and its committed `package-lock.json`.
- Drupal backend in `backend`: use **Composer** and its committed `composer.lock`.

## Repository Source of Truth

- `frontend/` is the only public frontend source. There is no root frontend.
- `backend/` is the only API, CMS, and pipeline source.
- Do not recreate the removed root Nuxt/AgencyOS/Directus prototype or copy code
  from it without a newly approved architecture decision.
- Treat `frontend/dist/` as generated output. Change `frontend/src/`, rebuild,
  commit the source change, and deploy the exact committed SHA.
- Follow `docs/SOURCE_OF_TRUTH.md` when adding tooling or documentation.

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

## Production Backend

- Canonical transactional source:
  `backend/web/modules/custom/famtastic_pipeline`.
- Follow `docs/BACKEND_DEPLOYMENT.md` for every backend production change.
- Use `./scripts/deploy-backend-godaddy.sh`; all agents use the same primitive.
- A production deployment requires a clean Git worktree at the current
  GitHub `main` SHA and explicit production authorization.
- Never edit or directly upload module files in production.
- The script must create code and database backups before `drush updatedb`.
- Runtime dependency additions require a reviewed platform migration; do not
  assume a private Composer validation changes the live vendor tree.

## Commit Attribution

AI commits MUST include:

```text
Co-Authored-By: (the agent model's name and attribution byline)
```

## Standing Rule — Lessons Learned Are Mandatory Records (2026-08-24)

Every incident, correction, or non-obvious fix MUST be captured the same day:
1. `docs/SITE_LEARNINGS.md` — dated entry: what happened, root cause, the rule that prevents recurrence.
2. The relevant recipe/change log (`docs/playbook/RECIPES/*.md`, `docs/CHANGELOG.md`).
3. The FAMtastic Drive decision log when a business decision (not just code) drove it.
Historical records of WHY and HOW we got here are part of the deliverable. An agent
that fixes without recording has not finished the task.
