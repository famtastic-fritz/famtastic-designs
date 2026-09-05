# Four-Campaign Flood + Delivery Automation

**Purpose**: Turn a full warehouse into a running delivery system. As of 2026-09-05 start, 98 live blog posts, 7 films, 38 stills and 21 plates existed with **zero posts scheduled**. That is the gap this plan closes.

**Goal**: Four campaigns built and queued across 8 days (2026-09-06 → 09-13), plus a recurring job that keeps producing without being asked. Success = a non-empty queue every day for 8 days, every asset traceable to a live blog post, and a cron that refills it.

**Status**: active — three of four campaigns queued and live in Postiz, one queued campaign pending, delivery cron running, video library built and blocked only on a git push.

**Started**: 2026-09-05
**Ended**: —
**Branch / worktree**: `main`, no worktree.

## Tasks

- [x] C1 — Booksy-client campaign (`booked-and-losing`) — **queued live**, 6/6 drops verified in Postiz
- [x] C2 — Commerce-instinct campaign (`already-know-the-game`, grunge, audience never named) — **queued live**, 6/6 drops verified in Postiz
- [x] C3 — Late-adopter campaign (`ive-managed-fine`, video-heavy, 6 objection films) — **built, all 6 films rendered/narrated/graded, NOT yet queued**
- [x] C4 — Proof-first campaign (`see-it-first`) — **queued live**, 6/6 drops verified in Postiz
- [x] Automation: recurring delivery cycle (`scripts/delivery/run-cycle.sh`) — **running on launchd, 07:12 daily**
- [ ] Schedule C3 into Postiz (blocked on nothing — just needs the `queue-campaign-drops.py --schedule` run)
- [ ] Video library (`/watch`) — **built, verified, committed locally, NOT YET PUSHED** (see Blocked, below)
- [ ] Deploy the video library to production (depends on the push landing)
- [ ] Post-run review: what shipped, what failed, what to adjust for the next flood

## Current state, in one table

| Item | State | Evidence |
|---|---|---|
| Postiz queue | **36 posts scheduled**, Sep 6–13 | Read from Postgres directly, not from script output |
| `booked-and-losing` | queued | 6/6 `VERIFIED` in queue |
| `already-know-the-game` | queued | 6/6 `VERIFIED` in queue |
| `see-it-first` | queued | 6/6 `VERIFIED` in queue |
| `ive-managed-fine` | **built, not queued** | 6 films on disk, `approval.publish: false` |
| Delivery cron | running | `launchctl list \| grep famtastic.delivery-cycle` |
| `/watch` library | **committed, not pushed** | local commit `18a3b238`; push failing on pack-size/inflate error |
| Repo push state | **2 commits behind push**, local `HEAD` ahead of `origin/main` | `git log --oneline origin/main..HEAD` |
| Production deploy | **not run since the 15-post blog deploy** | video library not yet live |

## Blocked

- **`18a3b238` (the video library, 119.8 MB of new MP4) will not push, and this is a network-layer problem, not a git problem.** `5949dbee` (49.7 MB) failed ~8 times then succeeded after a local `git repack -a -d -f`. `18a3b238` still fails after: repack, `pack.compression 0`, `pack.packSizeLimit`, and switching the remote to HTTPS. The HTTPS attempt surfaced the real signal: `LibreSSL SSL_read: bad record mac` — a TLS integrity failure, meaning bits are being corrupted in transit on this network path for large transfers specifically. `git fsck --full` on the local repo is clean; this is not repo corruption.
  - Remote reverted back to SSH (`git@github.com:...`) — unchanged from session start.
  - **Do not keep retrying blindly.** Try from a different network (not this wifi/VPN path), or split the video payload out of one commit into several smaller ones (~20-30 MB each) so no single push exceeds whatever is breaking, or push the video files via `git-lfs` if adopted, or have the owner pull `18a3b238`'s diff onto a machine with a clean path and push from there.
  - `d6f38420` (this plan file, 0 MB) pushes fine — confirms the wall is size-triggered, not repo-wide.
- **Production deploy of `/watch` cannot happen until `18a3b238` lands on `origin/main`** — the deploy script requires local `HEAD == origin/main`.

## Immediately next (resumable from here)

1. Retry the push (commit by commit, with longer backoff) until `5949dbee` and `18a3b238` land on `origin/main`.
2. Build the frontend and run `./scripts/deploy-frontend-godaddy.sh` (preflight) then `--apply` once pushed.
3. Verify apex + www + a `/watch/<slug>` URL return 200 in production.
4. Run `FAMTASTIC_MARKETING_PUBLISH=true python3 scripts/queue-campaign-drops.py --campaign ive-managed-fine --schedule`, then read the queue back from Postgres (not the script's own report — it has been wrong before this session).
5. Stop the stray Voicebox server left running on :17493 by the C3 agent (`pkill -f voicebox-server-aarch64`) once no other lane needs local TTS.
6. Run the post-flood review (see Review, below).

## Execution

Four campaign lanes ran in parallel, one automation lane, one video-library lane. Each campaign owns its own directory under `marketing/campaigns/<slug>/` and its own creative under `marketing/creative/campaign-assets/<slug>/`.

## Hard constraints carried from this session

**Channels.** Only Facebook and Instagram publish today. YouTube's OAuth token is expired and TikTok is not approved for public posting — both need the owner. All 24 flood drops target only these two channels.

**Product truth**, verified in `backend/config/famtastic-products.json`:
`FAM-FOOT-199` ($199, "55 cents a day") = ONE focused landing-page website + ONE year of managed hosting + first-year domain registration, or connecting a domain the customer already owns. **That is the whole bundle.** Business email is a separate $99 SKU. Maintenance is an upsell. `FAM-BUSINESS-499` = up to five pages — a different SKU. No live post or queued drop conflates the two (checked 2026-09-05).

**No invented statistics.** Argue the mechanism instead — house style, needs no citation.

**Never name or attack a competitor.** Booksy is context for C1's audience, never a target.

**No unbacked promises.** A "within 48 hours" claim is live on five old posts with no SLA in the catalog — not touched by this plan, flagged separately.

**Every URL curled before use.** `/web/` must never appear in a public URL.

**Series-first.** A campaign may only link to a blog post that is already live.

**Grade to the anchor.** `marketing/creative/heygen/reference-tokens.json` — mean luminance 150-175, olive `#7FB449` at 1-2% of frame. `already-know-the-game` and `ghost-town` diverge deliberately and say so in writing.

**Verify by looking.** Multiple automated checks written this session returned false results, including a queue-runner "PASS" that had silently adopted another campaign's records instead of queueing its own. `ffprobe`/exit-0 is not evidence; read the database back.

**Idempotency markers must be campaign-unique**, not just drop-unique — `drop-01` collided across all three campaigns built in the same session; fixed in `queue-campaign-drops.py` (scoped on `utm_campaign|utm_content`), but any future campaign should still verify its own queue read-back rather than trust `PASS`.

## Research

Blog corpus: 98 posts, 11 series (10 original + "The Own Your Online Presence Series", defined but unpopulated — owner decision pending). `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`, `docs/marketing/CHEAP_PRODUCTION_ECONOMICS_V1.md`, `docs/playbook/RECIPES/CAMPAIGN_PRODUCTION.md`.

## Review

Not yet run — pending C3 queueing and the production deploy. When run, cover: which drops actually fired vs errored (compare against `docs/SITE_LEARNINGS.md`'s existing YouTube/TikTok error pattern), measured spend per campaign against the ~$1 total for the whole flood, and one adjustment each lane would make next time (already logged in each campaign's own README — worth consolidating here).

## Skills

`famtastic-creative-studio`, `famtastic-voice`, `hyperframes`, `blog-cluster`, `blog-strategy`, `gpt-image-2-style-library`, `diagram-design`, `critique-*`.

## Proof

- Queue: read from Postgres directly — `docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -tAc "select count(*) from \"Post\" where \"deletedAt\" is null and state='QUEUE' and \"publishDate\">now();"` → 36.
- Video library: 8/8 films independently re-probed against their built `VideoObject` JSON-LD, all durations/dimensions/byte counts match.
- Measured spend: `booked-and-losing` $0.504, `already-know-the-game` $0.3024, `see-it-first` $0.00, `ive-managed-fine` $0.00. Whole flood well under $1.
