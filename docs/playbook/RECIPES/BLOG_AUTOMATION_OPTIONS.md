# Blog Automation Options — a decision doc, not a decision

Status: **options only — no mechanism has been scheduled or made automatic.**
Fritz's explicit ask (2026-09-04 session) was "more blog creation and some
automation there as well." This document covers the automation half. It
deliberately stops short of picking a mechanism or a cadence: scheduling
something that runs unattended against production is exactly the kind of
choice that deserves a human's explicit sign-off, not a default an agent
picked for them.

## What already exists (built, not scheduled)

Two real scripts already do the mechanical work; neither runs on its own
yet:

1. **`scripts/qa-content-links.py`** (built 2026-09-04) — crawls every
   published blog post live via JSON:API and checks every link it contains
   resolves. Exit 0 = clean, exit 1 = at least one broken link, with the
   exact URL and post. Currently invoked manually; see
   `.agents/skills/famtastic-content-qa/SKILL.md` for when a human or agent
   should run it today.
2. **`scripts/suggest-next-blog-topic.py`** (built 2026-09-04, same session
   as this doc) — reads the original 80-post content plan
   (`backend/config/famtastic-content-series.json`), the
   `marketing/blog/drafts/` folder, and the live published post list, and
   reports which planned topics are live, drafted-but-not-live, or not
   started at all. Read-only, no writes, no publish. Currently invoked
   manually.

Both are safe to run as often as anyone likes — neither writes anything.
The open question is only: what triggers a run, automatically, without a
human remembering to type the command?

## Option A — macOS launchd job (same mechanism as Studio)

This repo already trusts launchd for a long-running process
(`com.famtastic.studio` — see the root `CLAUDE.md` "Studio Process
Management" section). A `com.famtastic.blog-qa` plist could run
`qa-content-links.py` on a fixed interval (e.g. daily) and write its output
to a log file, with a separate mechanism (email, Studio dashboard, a Slack
webhook) surfacing a failure.

- **Pros:** No new infrastructure — same pattern already operating and
  understood. Runs locally, no cloud cost. Easy to inspect
  (`launchctl list`, `tail -f` the log) the same way Studio already is.
- **Cons:** Only runs while this specific Mac is powered on and the agent
  isn't asleep — not a true always-on guarantee. A failure is silent unless
  something is built to surface it (a plain cron/launchd job with no
  alerting is a job nobody reads until they go looking). Doesn't gate
  anything — it would report a broken link the day after it went live, not
  before.

## Option B — git pre-push hook

A `.git/hooks/pre-push` (or a tracked hook via `core.hooksPath`) that runs
`qa-content-links.py` before any push reaches `origin`, and blocks the push
on exit code 1.

- **Pros:** Catches a broken-link regression before it reaches even a
  feature branch on GitHub, let alone production. Zero extra infrastructure.
  Ties the check to the moment code actually changes, which is when a link
  bug is usually introduced (as it was three separate times on
  2026-09-04 — see `.site-context/SITE-LEARNINGS.md`).
- **Cons:** Only fires on `git push`, not on a Drupal-side content change
  made independently of this repo (e.g. someone edits a post directly via
  Drupal's admin UI with no corresponding git commit — `qa-content-links.py`
  checks *live* content, which can drift from what's committed here). Also
  only protects future pushes; it does nothing for content that's already
  live and rotting (a linked page renamed six months from now). Requires
  every contributor's local clone to have the hook installed — hooks aren't
  distributed by cloning alone unless `core.hooksPath` is checked in and
  documented as a required setup step.

## Option C — step in `scripts/deploy-frontend-godaddy.sh` or `deploy-backend-godaddy.sh`

Add `qa-content-links.py` as a preflight step in the existing deploy
scripts, blocking `--apply` on a nonzero exit code.

- **Pros:** Runs at the moment that actually matters most — right before a
  change reaches production — using infrastructure and a gate pattern
  (`deploy-frontend-godaddy.sh` already treats a clean git worktree as a
  hard requirement) this repo already trusts. Catches drift regardless of
  whether the deploy was code-driven or content-driven, since it checks the
  live site, not the git diff.
- **Cons:** Only runs at deploy time, which may be infrequent — a broken
  link could sit live for days between deploys with nothing checking it.
  Slightly conflates "frontend deploy" concerns with "blog content health,"
  which are different domains even though they share a script family.
  Needs a decision about which deploy script(s) it belongs in, since blog
  content is a Drupal (backend) concern but the frontend renders it.

## What this doc deliberately does not do

It does not pick A, B, or C. It does not combine them into a recommended
default. It does not create a cron job, a launchd plist, or a git hook.
Scheduling automation against production content is the kind of standing,
unattended decision this project's own blocking rules treat as something a
human should explicitly choose — cadence, mechanism, and failure-handling
all need a real answer from Fritz, not an agent's best guess.

## What a decision would need to specify, whichever option is picked

- **Cadence:** how often (on every push? nightly? weekly? only at deploy?).
- **Scope:** all published posts every time, or only posts touched since the
  last run?
- **Failure handling:** block something (a push, a deploy) or just notify?
  If notify, notify where — a log file nobody reads, an email, a message
  into an existing channel?
- **Ownership:** who gets paged when it fails, and what's the expected
  response time?

Any of A/B/C (or a combination — e.g. B for fast local feedback plus a
periodic launchd run for content-only drift) is buildable in under an hour
once that's answered. Nothing here is blocked on more research; it's
blocked on Fritz's call.
