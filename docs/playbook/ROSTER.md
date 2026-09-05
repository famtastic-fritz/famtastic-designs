# Workforce roster

Maintained by the CEO (`fam-ceo`). This is the authoritative registry for
specialist workforce roles: every hire = an agent file in `.opencode/agent/` +
a row here with mandate + first assignment. Provider/model availability lives
in `marketing/providers.json` and bounded local-model roles live in
`marketing/local-models.json`; this file links to them rather than duplicating
their proof. Terminated roles stay listed with reason.

## Active

| Role | Agent file | Mandate | First assignment | Hired |
|------|-----------|---------|------------------|-------|
| CEO | `fam-ceo.md` | Runs the company via recipes; dispatches, verifies, reports to Fritz | Standing orders 1–5 in its prompt | 2026-08-22 |
| Architecture & Logic Auditor | `fam-auditor.md` | Read-only full-stack trace; finds dead affordances, broken contracts, unreachable states | Mail pipeline trace (Standing Order 1) + frontend↔backend pricing mismatch (SO-5) | 2026-08-22 |
| Unification Engineer | `fam-unifier.md` | Drift audit + approved consolidation moves, verified by validators | Awaits auditor findings; then portal↔admin vocabulary parity | 2026-08-22 |
| Social Ops agent | `fam-social-ops.md` | Owns SOCIAL_POSTING.md + CAMPAIGN_17DAY.md: manifest audit, gap fill, Postiz scheduling (gated), publish verification, attribution | SOCIAL_POSTING step 1 — day-by-day 17-day manifest audit → commit table to RECIPES/CAMPAIGN_17DAY.md | 2026-08-23 |
| Dev (mail/notifications) | `fam-dev-mail.md` | Builds T1 Phase A visibility layer (A4–A6) + LEAD_TO_LAUNCH mail fixes; local-only, evidence-first | AUTONOMOUS_CUSTOMER_SERVICE A4–A6: replies list view, selection notifications, attention banner | 2026-08-23 |
| Support Triage agent | `fam-support-triage.md` | Owns AUTONOMOUS_CUSTOMER_SERVICE Phase B: intent classification (B1), draft-only L0 queue (B2), SLAs (B4); never auto-sends — B3 is a Fritz gate | B1: documented classifier rules + labeled test-set evidence on ≥20 historical messages | 2026-08-23 |
| Content Engine / CMO | `fam-content-engine.md` | Owns BLOG_FACTORY: 2 SEO-checked, fact-grounded posts/week; draft-first — publishing is a Fritz gate | BLOG_FACTORY steps 1–5 for first two campaign-supporting posts | 2026-08-23 |
| Portfolio Manager | `fam-portfolio-manager.md` | Owns PRODUCT_PIPELINE: catalog register, 4-axis scoring, tier proposals, wave staging; waves + launches are Fritz gates | CATALOG.md register with first 50 scored + wave-1 candidates staged | 2026-08-23 |
| Brutal Reviewer | `fam-brutal-reviewer.md` | Adversarial full-system audit: plan vs build gap analysis, vapor detection, Drupal-fit test | First assignment: end-to-end revenue-engine review (lead capture → conversion) | 2026-08-24 |
| Admin CX specialist | `fam-admin-cx.md` | Conversion + customer-experience expert: admin command center, customer portal, funnel flows; test-data hygiene; post-deploy visual verification | First assignment: portal crawl validator + messages overflow + projects-section flow redesign (owner screenshot 2026-08-24) | 2026-08-24 |
| Automation & Reliability Engineer | `fam-ops.md` | Owns cron/queue/alert layer: heartbeat races, publish executor, renewals cron scaffold, alert hygiene | Fix worker-late race; zero false alerts/24h; first real queue job | 2026-08-24 |
| Growth & Attribution Analyst | `fam-growth.md` | Owns measurement truth: UTM persistence, attribution joins, GA4 coverage, falsifiable wave criteria | UTM at capture + content→lead→order join; GA4 all routes | 2026-08-24 |
| Commerce & Fulfillment Engineer | `fam-commerce.md` | Owns money path: revision loop, proof gen, retention, provider-aware renewals (gated) | Revision loop completion with receipts | 2026-08-24 |

## Vacancies (hire against real blocked steps)

| Role to hire | Needed for | Hire when |
|---|---|---|
| ~~Support Triage agent~~ | — | HIRED 2026-08-23 → Active |
| ~~Social Ops agent~~ | — | HIRED 2026-08-23 → Active |
| ~~Content Engine / CMO~~ | Owns BLOG_FACTORY + campaign asset production (with HeyGen/Adobe pipelines) | HIRED 2026-08-23 → Active |
| ~~Portfolio Manager~~ | Owns PRODUCT_PIPELINE: catalog register, scoring, wave selection prep | HIRED 2026-08-23 → Active |
| ~~Dev (mail/notifications)~~ | — | HIRED 2026-08-23 → Active |
| Mobile Admin Owner | `/admin/famtastic/**` usable from a phone; Fritz works from mobile for real | After T1 P0s close |

## Tooling adopted

| Tool / skill pack | Source | Used for | Registered |
|---|---|---|---|
| OpenCode specialist roles | `.opencode/agent/` + this roster | 13 named FAMtastic operating roles, selected by bounded recipe work | 2026-09-05 routing index confirmed |
| Ollama local models | `marketing/local-models.json` | Low-cost local drafting, independent critique, and image-aware review under bounded roles | 2026-09-05 local runtime receipt (`qwen3:8b`, `glm4:9b`, `gemma3:4b`) |
| OpenCode Go | `marketing/providers.json#opencode_go` | Optional cloud specialist/model comparison after authentication proof | Owner subscription reported 2026-09-05; local CLI present, current Go login unverified |
| Piece subscription | Pending exact product URL/name | Do not route until the owner identifies the specific service and operator boundary | Owner reported 2026-09-05 |

## Terminated

| Role | Reason | Date |
|------|--------|------|
