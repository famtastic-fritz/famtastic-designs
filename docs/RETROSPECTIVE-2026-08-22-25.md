# Retrospective: Aug 22–25, 2026 — Four Days That Rebuilt the Machine

Author: opencode session · Mirrored to Google Drive · Companion to `docs/SYSTEMS.md` and `docs/playbook/`

## The arc

| Day | Theme | Output |
|---|---|---|
| Aug 22 | Management infrastructure | fam-auditor, fam-unifier, Playbook DNA (recipes/roster/templates), fam-ceo, Master Plan (5 tracks), strategic verdict |
| Aug 23 | CEO autonomous runs + mail crisis | Prod cron root-cause fixed, T1 Phase A closed, replies view + notifications + banner shipped, Support Triage hired (classifier + L0 drafts + SLA), Wave 0 runner, 17-day assets re-cut, autonomy charter |
| Aug 24 | Channel marathon I | Postiz spinner root-caused, permanent ngrok domain + self-healing script, 56 media rows fixed, Instagram connected + Graph-verified, SYSTEMS.md, doc-sync law, DNS TXT verification |
| Aug 25 | Channel marathon II | X connected (OAuth 1.0a), YouTube connected (live API 200), TikTok sandbox lessons, orchestrator OOM diagnosed, **all five channels live**, manifest audit (campaign is FB-only) |

## The CEO (fam-ceo): mandate, performance, improvements

**Mandate**: run FAMtastic Designs through recipes — orient from playbook, triage by revenue × visibility, dispatch the workforce, verify evidence personally, hire against blocked steps, report to Fritz with receipts. Fritz keeps only the gates: outreach, billing, DNS, deploys.

**What it did well**
- First autonomous run closed T1 Phase A end-to-end: found the production cron root cause (PHP CGI wrapper eating drush silently) that humans had missed, fixed it with backups, and shipped three visibility features with validator receipts — in one session.
- Hired correctly: dev-mail delivered A4–A6 same-session; Support Triage shipped B1/B2/B4 with evidence.
- Honest reporting: self-reported a 3-minute crontab outage mid-fix, restored from backup, ledgered the lesson.
- Governance worked: flagged an "unknown-provenance" edit to the tunnel script (it was ours), refused dirty-tree deploys, ran read-only heartbeats that committed doc syncs.
- Corrected a false premise honestly: re-evidenced the "mail broken" claim against live production data instead of inheriting it.

**Where it fell short (and the fix each one produced)**
1. **Asserted "zero connected channels / OAuth never completed" without querying the database** — it trusted a stale handoff doc over live state. Facebook had been connected for two weeks. → Produced the probe-systems law (AGENTS.md/SYSTEMS.md): never assert runtime state without querying the system.
2. **Didn't notice the orchestrator was OOM-dying** every boot — its health checks covered the frontend, not worker processes. → Channel-health card + orchestrator monitoring queued; colima memory resize pending.
3. **Never revisited campaign content against the owner's current intent** — executed the plan as written instead of asking whether the plan was still wanted. → Content-verdict session added before any publishing.
4. **Left the channel-health card unconfigured** for a day despite it being two env vars. → Being wired now.

**Improvement ideas (next iterations of the CEO)**
- Morning brief should include: Integration table snapshot, orchestrator liveness, outbox counts, campaign gate status — pulled, not remembered.
- A "premise audit" step in orientation: list every inherited assumption and mark verified/stale.
- Auto-escalate any pm2 process exit ≠ clean, not just HTTP health.
- Own the Wave 0→1 ladder as its primary revenue KPI.

## Pitfall catalog (each cost real time; each now has a prevention)

| # | Pitfall | Root cause | Prevention now in place |
|---|---|---|---|
| 1 | "Infinite spinner" on Postiz login | Auth cookie hostname-bound; config pointed at dead tunnel | Permanent ngrok domain; use one hostname only |
| 2 | Every image broken after URL change | Media table stores absolute URLs | Restart script rewrites paths automatically |
| 3 | OAuth callbacks blocked | ngrok free interstitial + rotating quick-tunnel URLs | Static domain; click-through once; production = real domain later |
| 4 | Meta OAuth "URL Blocked" | Redirect URIs whitelisted per-app, stale entries | Verify exact URI per product section; DNS TXT preferred |
| 5 | "Insufficient Developer Role" | Tester invite not ACCEPTED inside Instagram (Tester Invites, not email) | Runbook gate 5 |
| 6 | TikTok "client_key" rejected | Production keys inert pre-approval | Sandbox credentials for own-account use |
| 7 | Postiz "Invalid state" on OAuth | Handshake states expire in 60 minutes; stale tabs | Mint fresh → click immediately; close old tabs |
| 8 | "Authentication failed" (YouTube) | Client secret miscopied; silent token-exchange failure | Probe `oauth2.googleapis.com/token` with fake code: `invalid_client` = bad secret, `invalid_grant` = good |
| 9 | htaccess 500s during verification fix | Rewrite rule matched its own output (loop); blind append onto file without trailing newline | Scoped `/$`-anchored rule; always upload whole file; test matrix |
| 10 | Env file wiped custom vars on restart | Script rebuilt env from container capture only | awk last-wins merge preserves manual additions |
| 11 | Cron silently dead for weeks | drush resolved to cPanel CGI wrapper; stderr discarded | PATH fix + per-job logs; heartbeat check |
| 12 | Orchestrator OOM (exit 137) | 3GB colima VM across WordPress+Temporal+Postiz stacks | Memory resize queued (owner OK needed; bounces containers) |
| 13 | Two agents editing one tree | Concurrent CLI sessions | Drift-check hooks, provenance flags, stash-then-restore discipline |
| 14 | Stale docs treated as truth | Docs lag reality | Probe-systems law + SYSTEMS.md inventory |

## Continuity: what happens when a session dies

Progress does **not** live in any agent's memory. It lives in: git (commits + receipts), `docs/playbook/` (recipes ARE the status board), `docs/SYSTEMS.md` (estate map), the Postiz/Drupal databases, and the Drive mirror. A dead session costs minutes, not days — the recovery entrypoint is `/standup` in the repo, which orients from disk. This document is the meta-layer: the map of how the map got made.

## Queued next (in order)

1. Wire `FAMTASTIC_POSTIZ_API_KEY` + base URL into prod Drupal → Channel health card live
2. Content verdict: review day 1–3 copy → keep or regenerate
3. Bind 56 unbound campaign records to channels (Content Engine) → queue drafts
4. Colima memory resize (fixes orchestrator OOM) → then publish-gate policy
5. Wave 0 → Wave 1 (20 leads)
6. Rotate exposed app secrets (Instagram, TikTok)
7. Build `/terms` + `/privacy` pages
8. Production plan doc: HyperFrames/OpenArt/HeyGen/Adobe → per-channel asset factory
