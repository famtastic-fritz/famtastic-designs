# Campaign Posting Architecture

**Status**: current state + open decision
**Created**: 2026-09-03
**Owner question that prompted it**: *"although we can generate assets locally, I
think this should be a function of the server or Drupal cron to actually send —
and should I have a way to build new campaigns? Not all will be the same."*

Both instincts are correct. This document records why, what changed on
2026-09-03, and the one decision still open.

---

## 1. Why nothing has ever posted

Not a regression. The pipeline was built to stop, and every stop was still
engaged. Five independent blockers, in the order they bite:

| # | Blocker | Evidence |
|---|---|---|
| 1 | All 68 records unapproved (`approval.publish: false`), `public_publish_enabled: false` | `publish-executor.php` selects on `approval_publish = 1` → **zero candidates** |
| 2 | Only 12 of 68 records ever became Postiz drafts (days 1–3); 0 ever scheduled | manifest `provider_ids` |
| 3 | Double CLI gate (`FAMTASTIC_MARKETING_PUBLISH` + `--i-have-owner-publish-approval`) | `publish-executor.php` |
| 4 | The Cost Is Not The Reason campaign had **no execution path at all** | its schedule JSON was referenced by zero code in the repository |
| 5 | Its media exists only on the operator workstation | video paths resolve to nothing; `.gitignore` excludes `*.mp4` |

Blocker 3 is the one that gets talked about. Blockers 1, 2 and 4 are the ones
that actually stopped everything — removing the gates alone would still have
posted nothing.

**Blocker 4 is the structural one.** Every campaign to date had its own bespoke
queue script (`queue-55-cent-days-1-3-drafts.sh`, `queue-days-4-17.py`, …). A
campaign nobody hand-wrote a script for had no path to the provider. That is not
a bug in any one campaign; it is the architecture guaranteeing the next campaign
fails the same way.

---

## 2. The design flaw: sending is laptop-dependent

Asset generation running locally is fine — it is bursty, creative, and
supervised. **Sending is not.** A drop scheduled for 23:50 requires, right now:

```
operator Mac awake  →  colima VM running  →  Postiz container healthy
                    →  ngrok tunnel up (OAuth callbacks need HTTPS)
                    →  a human runs a CLI script
```

Any one of those being false at 23:50 means the post silently does not exist.
That chain has never held unattended, which is the physical reason the campaign
did not go out.

### Moving the trigger to Drupal cron is necessary but not sufficient

This is the important nuance. `famtastic_pipeline_cron()` exists (and is
currently under a pilot exact-dispatch lock that skips general automation), and
there are no queue workers. Adding a cron-driven publisher is straightforward.

But **cron on the GoDaddy server cannot reach Postiz**, because Postiz is on the
Mac. `127.0.0.1:4007` on the server means the server itself. Server cron would
fire perfectly on time and then fail to connect — the same missed drop, with
more moving parts and a more confusing failure.

**The trigger and the executor have to move together.** Whichever option is
chosen below, the ordering is: relocate Postiz first, then move the trigger.

---

## 3. Options for the send path

**Checked 2026-09-03: nothing existing can host it.** The production host is
GoDaddy **cPanel shared hosting** (`132.148.233.159`), which cannot run Docker or
long-lived containers — it runs Drupal and cron only. `marketing/providers.json`
holds creative, model, storage, payment and analytics providers; there is no
compute host among them. Per the repository's provider rule, reuse was checked
first and there is nothing to reuse, so options A and B both mean one new paid
service.

| | Option | What moves | Trade-off |
|---|---|---|---|
| **A** | **Self-host Postiz on a small VPS**, then drive it from Drupal cron | Postiz + trigger | Unattended sending finally works. ~$6–12/month; 2 GB is the floor (the workstation instance has already been OOM-killed at 3 GB). Keeps every script in this repo working unchanged — only `FAMTASTIC_POSTIZ_BASE_URL` and the API key change. **Recommended.** Built and dry-run verified: `docs/marketing/POSTIZ_SERVER_MIGRATION.md`. |
| **B** | **Postiz Cloud** (hosted by the vendor) driven by Drupal cron | Postiz + trigger | No infrastructure to run or patch. Vendor subscription; re-auth all five channels; you give up control of the data and upgrade cadence. Same API, so the scripts still work. |
| **C** | **Drupal posts directly to platform APIs**, dropping Postiz | Everything | No middleman and no tunnel. But it means owning Meta/X/TikTok/YouTube/LinkedIn OAuth, token refresh, rate limits, and per-platform media rules — months of work that Postiz already does. Not recommended. |
| **D** | **Keep it on the Mac**, add a launchd job | Trigger only | Free and immediate. Still fails whenever the Mac sleeps, colima stops, or ngrok drops. Acceptable as a stopgap for tonight's evaluation drops; not an answer. |

Recommendation: **A**, with **D** as tonight's stopgap. A is the smallest change
that makes the failure mode disappear rather than move, and it leaves the entire
scripted pipeline in this repository intact.

The A migration kit is built and dry-run verified — server compose with real TLS
(`marketing/engine/postiz/compose.server.yaml`), a Caddy front door, and a
dry-by-default deploy primitive (`scripts/deploy-postiz-server.sh`). Procedure:
`docs/marketing/POSTIZ_SERVER_MIGRATION.md`. Nothing has been provisioned or
paid for; picking the host is the only remaining step.

### What a server-side send lane needs once Postiz has moved

1. A `QueueWorker` plugin in `famtastic_pipeline` (none exist today) that
   converts due, approved drafts to schedule and records provider IDs.
2. `famtastic_pipeline_cron()` enqueuing due records — noting the pilot
   exact-dispatch lock currently short-circuits general automation there.
3. `FAMTASTIC_MARKETING_PUBLISH` set in the **server** environment, not a repo
   file, so arming remains an infrastructure act rather than a commit.
4. Real backend deployment discipline per `docs/BACKEND_DEPLOYMENT.md`.

That work is deliberately **not** built yet: it depends on which option above is
chosen, and building it against the laptop Postiz would bake in the flaw.

---

## 4. Building new campaigns (as of 2026-09-03)

The per-campaign-script pattern is retired. A campaign is now postable when it
has **one file** and no new code:

```
marketing/campaigns/<slug>/posting-schedule.json
```

conforming to `marketing/engine/schemas/posting-schedule.schema.json`.

Campaigns differ — cadence, channels, media mix, copy shape — so the differences
live in that data:

- `drops[].scheduled_time` — absolute ISO 8601 **with offset**. Required, because
  a bare `HH:MM` cannot express a cadence that crosses midnight, which is exactly
  how a late-night sequence gets mis-ordered.
- `drops[].channels` — labels resolved through a shared map; a campaign can add
  its own via `channel_map`. A requested channel that is not connected is
  reported, never silently dropped.
- `drops[].copy` — per-surface copy variants; each integration takes the most
  specific key it prefers, overridable per campaign via `copy_preference`.
- `drops[].content_id` — the idempotency key, emitted as `utm_content` in the
  tracked link. A rerun adopts an existing draft instead of duplicating a live
  post.

### Workflow

```bash
# 1. scaffold
python3 scripts/new-campaign.py --slug spring-refresh --name "Spring Refresh" \
    --drops 4 --anchor 2026-09-10T23:50:00-04:00 --interval 150

# 2. fill in copy, channels, media paths; set approvals when signed off

# 3. validate (blocks on TODO placeholders, unmapped channels, bad times,
#    duplicate content_ids, out-of-order drops)
python3 scripts/new-campaign.py --validate spring-refresh

# 4. dry run — resolves media and channels without contacting Postiz
python3 scripts/queue-campaign-drops.py --campaign spring-refresh --dry-run

# 5. queue as DRAFTS (safe unarmed, idempotent)
python3 scripts/queue-campaign-drops.py --campaign spring-refresh

# 6. convert to a live schedule (requires the arming switch)
FAMTASTIC_MARKETING_PUBLISH=true \
  python3 scripts/queue-campaign-drops.py --campaign spring-refresh --schedule
```

---

## 5. Arming and safety after 2026-09-03

Owner directive: record-level approval gates opened; the second CLI gate
removed so an unattended scheduler can run without a human typing a flag.

**`FAMTASTIC_MARKETING_PUBLISH=true` is the single remaining arming switch.** It
is deliberately never defaulted on in any committed file — `marketing/.env.example`
ships it `false`, and `campaign-readiness.py` asserts that. An agent session that
has not been armed by the host environment still cannot send.

Two safety properties were **added**, not removed, in the same change:

- **Stale-date guard.** Postiz preserves a post's stored date across the status
  change, so converting a backdated draft publishes it instantly. The days 1–3
  drafts still carry their 2026-08-23 creation dates; arming publishing without
  this guard would have fired twelve backdated posts at once. Such records are
  now reported `BLOCKED (stale_date)` and must be re-dated first via
  `scripts/retime-campaign-drafts.py`. Override only with `--allow-stale-dates`.
- **Fail-loud media resolution.** A drop whose media is missing on the executing
  host is reported BLOCKED by name. It is never posted image-only by silent
  fallback and never skipped — a drop vanishing quietly is the exact failure this
  campaign already hit once.

---

## 6. Open decision

**Which option in §3 for the send path?** Until that is answered, sending stays
on the operator workstation and a drop can only fire while that machine is awake
with colima and ngrok up. Everything else in this document is already in place.
