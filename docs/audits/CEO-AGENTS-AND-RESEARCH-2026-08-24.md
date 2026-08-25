# CEO PLAN: AGENTS + RESEARCH — 2026-08-24

**Commissioned by**: Fritz (owner) · **Executed by**: @fam-ceo
**Inputs**: `CEO-FULL-REVIEW-2026-08-24.md` (Automation 58 / Money 40 / Track 32 / Grow 22), `MASTER-PLAN.md` (five tracks), `ROSTER.md`, charter.
**Constraint honored**: read-only session except this file (one-write cap). ROSTER.md rows and `.opencode/agent/` files are staged below as copy-ready text; they land under my normal hire power next session or on owner go-ahead.

---

## QUESTION 1 — WHAT ADDITIONAL AGENTS DO WE NEED

### Verdict up front

Three hires are justified by the scores. Everything else the scores demand is either already owned by a hired agent whose work is gated/dispatched-held, or is a Fritz decision no headcount can cross. The single biggest lever on Make-money (founder-$1) requires **zero hires** — it requires pulling an existing trigger.

### Hire list (ranked by score impact)

| Rank | Role | Agent file | Mandate | First assignment (score tie) | Owns | Unblocks |
|---|---|---|---|---|---|---|
| 1 | **Automation & Reliability Engineer** (`fam-ops`) | staged | Own the prod automation substrate: cron correctness, worker-queue utilization, alert truthfulness, server-side execution of laptop-bound loops | Kill the worker-late false-positive race: fix `LifecycleOperationsService.php:194-198` to judge liveness off `last_finished` + grace window, not bare `next_due`. Receipt: 0 false positives in a 24h outbox query. Then queue the automation queue's first real job (prod shows processed=0 on all three workers). **[Automation 58]** | New recipe `RECIPES/AUTOMATION_RELIABILITY.md` (must be authored per Law 1 before dispatch) covering: alert hygiene, worker backlog, publish executor for SOCIAL_POSTING steps 4–6, renewals-cron scaffold behind gate, laptop-bound inventory (Postiz, CEO-heartbeat launchd, local Studio builds) | Grow (posting survives lid-close), trust in alerts (237/267 lifetime sends are noise today), social-ops stops being blocked on missing executor; Make-money gets the renewals path scaffold |
| 2 | **Growth & Attribution Analyst** (`fam-growth`) | staged | Make every growth claim falsifiable: capture source at lead creation, join posts→leads→orders, instrument conversion events | Persist UTM snapshot at prospect create (`PublicRequestController`/`LeadIngestionService` — currently zero utm reads) + schema the post→lead→order join (LEAD_TO_LAUNCH R4), then replace the apology at `MarketingCommandController.php:314` with real content-ID attribution. Receipt: synthetic lead carries utm_source/content_id retrievable in admin. Second: GA4 coverage from 2 pages/2 events to all ~29 routes incl. purchase event. **[Track 32]** | T4 measurement loop (`STRATEGY-PRICING.md`) + LEAD_TO_LAUNCH R4 | T2 done-criterion "UTM/GA4 attribution visible"; wave acceptance criteria become testable; campaign sends stop burning leads with zero learning; Grow becomes measurable |
| 3 | **Commerce & Fulfillment Engineer** (`fam-commerce`) | staged | Close the post-payment loop without Fritz-per-order: revision flow, launch/delivery path, retention + renewal charging design | Complete revision loop step 9 (partially built) to DONE-with-receipts — it sits on the critical path of delivering for the very first paying customer whenever founder-$1 executes. Then proof-generation step 6 in-house path, then retention step 13 design. **[Money 40]** | LEAD_TO_LAUNCH steps 6, 9, 11, 13 + provider-aware renewal charging implementation (post-R1 research, Fritz-gated) | Delivery doesn't stall after first real order; renewals MRR becomes possible at all (today auto-charge throws by code, `HostingLifecycleService.php:96-98`); Grow's "re-bill without Fritz" gap |

### Honest non-hires — assignments to agents we already employ

| Score demand | Resolution |
|---|---|
| First stranger revenue (gap #1) | **No agent can do this.** Founder-$1 is staged, idempotent, documented. It is a Fritz trigger + CEO-prepared change/rollback. Hiring here would be procrastination wearing a badge. |
| Blog stale 13 days (Grow) | Already owned: fam-content-engine (BLOG_FACTORY, 2 complete drafts through step 5). Dispatch hold is Fritz's policy, not a staffing gap. Re-dispatch costs nothing once hold lifts. |
| Campaign publish scheduling/verification | Already owned: fam-social-ops. Its blocker is the missing executor — that's fam-ops' deliverable, not a new role. |
| Backlog import stalled at 206/~1,300 | Already owned: fam-portfolio-manager; hard-blocked on Fritz's bulk-KILL ruling. |
| External/web research agenda (Q2 below) | Assign to existing roles per item (auditor = code-level internals; CEO/portfolio-manager = web research; brutal-reviewer pattern stays episodic). A standing researcher hire is not justified yet. |
| Mobile admin usability | Existing vacancy stays parked ("after T1 P0s close") — unchanged. |

### What I can self-make vs what needs Fritz

- **Self-makeable now (charter power: agent file + ROSTER row, no gates)**: all three hires above. No spend, no sends, no prod changes in their first assignments' *build* phase.
- **Fritz-gated moments each will hit**: fam-ops → any prod deploy of the race fix + moving Postiz server-side; fam-growth → DB migration deploy to prod; fam-commerce → anything touching billing behavior (Law 3) and the renewal-charge enablement itself.

### Staged ROSTER.md rows (paste into `## Vacancies` when agent files are written; move to Active on first dispatch)

```
| Automation & Reliability Engineer | `fam-ops.md` | Owns cron/queue/alert-truth layer + publish executor + renewals cron scaffold | Worker-late race fix receipt: 0 false positives/24h outbox query [AUTOMATION] | pending |
| Growth & Attribution Analyst | `fam-growth.md` | Owns attribution capture, post→lead→order join, GA4 conversion instrumentation | UTM snapshot persisted at lead create + join-table migration, admin-retrievable [TRACK] | pending |
| Commerce & Fulfillment Engineer | `fam-commerce.md` | Owns post-payment delivery loop: revisions, launch path, retention, renewal charging | Revision loop step 9 complete with validator receipts [MONEY] | pending |
```

Agent-file skeletons (HIRING TEMPLATE shape) for all three are summarized in this doc; full files get written at hire time with `<RUNBOOK>` steps quoting recipe IDs verbatim.

---

## QUESTION 2 — RESEARCH NEEDED TO MOVE THE SCORES

Ranked by expected score movement. "Internal" = answerable from repo/prod inspection alone; "External" = needs web/vendor research.

| Rank | Research question | Why the repo can't answer it internally | Where the answer lives | Type | Owner |
|---|---|---|---|---|---|
| **R1** | Can commerce_stripe execute **off-session renewal charges** (customer not present) against saved payment methods — including SCA merchant-initiated-transaction handling — on our prod module/gateway versions? | Our own code forbids trying: `HostingLifecycleService.php:96-98` throws unless provider='memory'. Capability was never attempted; feasibility depends on Stripe API semantics + module version support, neither readable from our tree. | Drupal.org commerce_stripe docs/issue queue + our installed version (internal check); Stripe docs on off_session confirmations & MIT indicators; commerce_recurring compatibility matrix | Mostly **External**, small internal version probe | fam-commerce (once hired); CEO interim |
| **R2** | What can **Postiz actually automate server-side**: draft→schedule→publish via API, status read-back, multi-channel, token scopes? Determines whether a publish executor is even buildable off-laptop. | Postiz has only ever been driven interactively from the laptop; manifest gates exist but no code path proves API-driven publish+verify. Our instance's API surface is unprobed. | Postiz GitHub/self-host API reference (external) + one capability probe against our instance with scoped tokens (internal) | **External + internal probe** | fam-ops with fam-social-ops |
| **R3** | Correct **UTM/attribution capture pattern for SPA frontend + headless Drupal**: persist-at-submit vs cookie/anonymous-ID stitching, first-touch vs last-touch convention, GA4 Measurement Protocol join for the purchase event. | Backend ignores UTMs entirely today (`grep utm` → 0 hits in capture services); the ~20-line persistence fix is known, but stitching pre-registration visitors to post-registration orders needs a chosen convention. | Internal for the core fix (SolutionFinder.jsx already sends the blob); external best-practice for identity stitching + GA4 MP event design | Mixed; core fix **Internal** | fam-growth |
| **R4** | **Competitor pricing scan for $199-tier starter websites + hosting/renewal price points** — where does our anchor sit vs local agencies, productized services, and DIY builders, and what renewal price clears friction? | Zero stranger revenue means zero market feedback; catalog ladder decisions (T4) currently rest on internal reasoning only. | Web: competitor sites, productized-service pricing pages, builder benchmarks; feeds STRATEGY-PRICING ladder + wave plan | **External** | CEO + fam-portfolio-manager (T4) |
| **R5** | **Conversion benchmarks for proof-first funnels**: realistic lead→proof→checkout rates for cheap-starter-site offers, cold-list wave acceptance rates, so wave criteria are falsifiable rather than vibes. | With n=1 order (our own test account), no internal baseline exists; acceptance criteria for wave 1 (20 leads) would otherwise be invented. | Industry benchmark reports (agency/funnel/lead-gen surveys); used to set STRATEGY-PRICING acceptance thresholds | **External** | CEO |
| **R6** | **Heartbeat/alert watchdog patterns**: grace-window liveness math beyond our specific race (and whether an external deadman service beats self-monitoring). | The specific bug is fully diagnosed internally (`LifecycleOperationsService.php:194-198` vs second */5 line); only the general pattern choice is open. | Textbook last_success-vs-now+grace patterns (internal fix sufficient); deadman-switch services only if we want laptop-independent verification (check providers.json first) | Mostly **Internal** | fam-ops |

Ranking rationale: R1 and R2 are hard capability unknowns gating recurring revenue (Money/Grow) and autonomous publishing (Automation/Grow). R3 is the cheapest certain win and unblocks two done-criteria (T2, T4) — it ranks below R1/R2 only because its answer is largely already known internally. R4/R5 convert marketing spend from vibes to falsifiable bets just before real waves send. R6 completes Automation but is 90% solved by one diff.

### Sequencing

1. Fritz pulls founder-$1 trigger (no hire needed) — everything else in Money is decoration until a stranger pays once.
2. I self-make the three hires (agent files + roster) and author `RECIPES/AUTOMATION_RELIABILITY.md` per Law 1.
3. Parallel: fam-ops starts R6-fix + R2 probe; fam-growth starts R3 core fix; fam-commerce starts step 9.
4. Research R1/R4/R5 results land before any real wave send or renewal-enablement gate reaches Fritz.

---

## Session notes

- Doc-sync standing rule (CHANGELOG / CAPABILITY_REGISTRY / SITE-LEARNINGS / Drive mirror): **deferred explicitly** — owner capped this session's writes to one file. No evidence-classification changed (no new proofs produced), so CAPABILITY_REGISTRY is correctly untouched; CHANGELOG entry for this doc rides the next sync-permitted session.
- This file is the single committed artifact of this session; ROSTER rows and agent files are staged text within it, not live edits.
