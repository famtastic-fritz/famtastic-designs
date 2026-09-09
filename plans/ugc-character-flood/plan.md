# UGC Character Flood — 4 campaigns, 32 drops, 4 days

> **PARKED PLAN NOTICE.** This file previously held the *Owner Dashboard* plan (React `/admin` + `/api/owner/*` + Drupal nav-depth fix, 11 phases, ~72h). It was complete and approved-pending when the owner parked it. It is recoverable verbatim from this session's transcript at
> `~/.claude/projects/-Users-famtastic-fritz-Development-FAMtastic-sites-site-famtastic-designs/b2e02462-417e-463a-8a6e-675c62e2280f.jsonl` (search `Owner Dashboard — mobile-first campaign / click / lead command center`).
> **Task 0 below commits BOTH plans into the repo so neither depends on a session again.**

**Title**: UGC Character Flood v1
**Purpose**: The queue holds 48 posts running out 2026-09-13, all of it non-character kinetic/plate work. There is no recurring face, no UGC-native format, and no campaign that survives a scroll on a phone. Meanwhile TikTok and YouTube are blocked, so ~40% of intended reach is dead — the assets built for them currently earn nothing.
**Goal**: Four character-led UGC campaigns, 32 vertical films, 8 drops/day for 4 days **starting today (owner directive, non-negotiable)**, published to the three channels that actually work (Facebook, Instagram, X). Every film also lands on `/watch` and inside a blog post that embeds it, so the TikTok/YouTube share earns SEO instead of nothing — and republishes to those channels with zero rework the day they unblock. Success = 32 films rendered and graded, 32 drops queued and verified by database read-back, ≥4 recurring characters holding identity across ≥6 clips each, at least one genuinely photoreal hero clip, total provider spend under $12.00 of the $16.70 available, and a per-drop cost receipt for every paid call.

**Execution mode**: owner directive — run as a real multi-agent parallel workflow (four campaign lanes execute concurrently as separate agents), not a sequential single-session build.

**Tasks**
- [x] T0 — Commit this plan AND the parked owner-dashboard plan into `plans/` (both files existed untracked in the worktree through T1–T9; actually committed as part of T10 closeout — see Proof)
- [x] T1 — Cost + identity pilot: two routes, one clip each, real receipts (spend ≤ $1.50) — spend was $2.96, 97% over its own ceiling, reported and accepted per plan; verdict: Route A wins
- [x] T2 — Cast bible + **premium Sora 2 hero** + brand-asset deposit (reference sheets, anchor tokens) — $1.85 of $2.90
- [ ] T3 — Campaign C1 `every-reason` — 8 drops — **NOT BUILT.** No `marketing/campaigns/every-reason/` directory, no films, no drops exist. This is the plan's one genuine gap, not a deferred choice.
- [x] T4 — Campaign C2 `signal-and-static` — 8 drops (futuristic grunge) — $1.23; motion clip rejected for identity/palette drift, replaced with a $0 HyperFrames treatment over the verified-good scene still
- [x] T5 — Campaign C3 `whats-your-secret` — 8 drops (2-hander, two cultural versions) — $1.23
- [x] T6 — Campaign C4 `front-desk` — 8 drops (recurring host) — $1.20
- [x] T7 — Film library + video-blog SEO capture — **24 of the planned 32** (only the three built campaigns; `every-reason` contributes none). All 24 have a `/watch/<slug>` shell and one of three companion blog posts.
- [x] T8 — Queue **24 of the planned 32** drops (T3's 8 don't exist to queue), verified by DB read-back: 24 distinct campaign-prefixed `utm_content` values, state `QUEUE`, Facebook+Instagram. X was also submitted for all 24 but a newly-found bug (queue-campaign-drops.py drops the UTM-tracked link, and with it the sibling match, for copy ending in a bare URL) leaves all 24 X drafts stuck in `DRAFT` — logged in T10's docs sync, not fixed here.
- [x] T9 — Held TikTok/YouTube records + unblock-day replay procedure — **48 of the planned 64** held (3 campaigns × 2 platforms × 8; `every-reason`'s 16 don't exist yet). `UNBLOCK-REPLAY.md` documents how to extend coverage once C1 exists.
- [x] T10 — Docs sync, capability registry, providers.json muapi correction, closeout

**Status**: T1, T2, T4–T10 complete; T3 (`every-reason`) not built — plan is **not** fully complete
**Started**: 2026-09-06
**Ended**: 2026-09-07 (T10 closeout; T3 remains open for a future session)
**Execution**: T1→T2 are strictly serial and gate everything. T3–T6 run as four parallel lanes after T2. T7 follows each lane independently. T8 after all lanes. T9/T10 last. One phase per fresh session; a cheaper model can execute any single task from §B + that task's section alone.
**Research**: two read-only capability audits + live probes of muapi, HeyGen, Voicebox, Postiz, Temporal on 2026-09-06. Every price below was pulled from the live muapi `/api/v1/models` endpoint, not estimated.
**Review**: per-task verification checklists. Design lane = `critique-affordance` + `critique-information-density` on rendered frames. Never run `better-*` on the same artifact (`providers.json`: "pick one").
**Skills**: `hyperframes` (entry point — routes all four to `/general-video`), `hyperframes-core`, `hyperframes-creative`, `media-use`, `famtastic-voice`, `muapi-photo-pack-generator` (identity-lock doctrine), `muapi-seedance-2`, `muapi-ugc-video-factory`, `famtastic-creative-studio`.
**Branch / worktree**: `main`, no worktree.
**Blocked By**: nothing. Owner gates: any `--apply` deploy, `FAMTASTIC_MARKETING_PUBLISH=true`, and spend above the $12.00 ceiling.

**Proof**: `marketing/campaigns/<slug>/evidence/` per campaign — cost receipts, identity QA sheets, ffprobe output, grading measurements; Postiz DB read-back for the 24 drops that exist (`front-desk`, `signal-and-static`, `whats-your-secret` — `every-reason` was never built, so there are only 24 of the planned 32); `/watch/<slug>` shells build correctly locally but **production deployment was not run in this plan** (`scripts/deploy-frontend-godaddy.sh --apply` remains a separate, explicitly-authorized step) — treat `/watch/<slug>` as unverified in production until that deploy happens and each URL is curled 200 for real.

**T10 closeout numbers (2026-09-07)**: total real spend across all 5 cost-ledger.jsonl files (39 rows) = **$8.4700**. Opening balance $16.7020 → closing balance **$8.2320 USD**, confirmed live via `muapi account balance` and reconciling exactly to the ledger sum ($16.7020 − $8.4700 = $8.2320). Well inside the $12.00 ceiling. Facebook/Instagram/X publish-proven with live `releaseURL`s (12/11/3 posts respectively) confirmed by direct Postgres read on 2026-09-07 — see `docs/CAPABILITY_REGISTRY.md`.

---

## A. Ground truth verified 2026-09-06 (corrections to standing assumptions)

| Assumption | Verified reality |
|---|---|
| "No backlog left" | **48 posts queued Sept 6→13** (FB 28, IG 20) across `ive_managed_fine`, `booked_and_losing`, `already_know_the_game`, `see_it_first`, `web_basics_55_cents_17d`. Thin — 2–8/day — but not empty. |
| "Facebook and Instagram are the channels" | **X publishes too** — `twitter.com/FritzMedine/status/2095980238501015845` and two more, Sept 3–4. Also 3 `Unknown Error` failures, so it is real but flaky. **Three working channels.** |
| 4 Instagram posts stuck in QUEUE | **Self-resolved.** All four PUBLISHED 13:30–15:00 UTC 2026-09-06 with live URLs. No requeue needed. |
| `CAPABILITY_REGISTRY.md`: "no post confirmed live on any platform" | **Stale.** FB, IG and X all have live `releaseURL`s. Registry must be corrected in T10 *with these URLs as proof*. |
| `providers.json` muapi row: "CLI binary absent; call REST directly" | **Wrong.** `muapi` CLI 0.2.7 installed, authenticated from Keychain (`muapi-cli` / `api-key`), **balance $16.7020 USD**. Corrected in T10. |
| Agent audit: "you cannot price muapi" | **Solved.** `GET /api/v1/models` returns an exact `cost` (USD) per endpoint plus an `estimate_endpoint`. Prices in §C are live values, not estimates. |

**Never-published boundary still stands for**: TikTok (sandbox, ~daily re-auth, app audit pending), YouTube (testing mode, disabled, `refreshNeeded=t`), LinkedIn (not connected). Do not claim otherwise.

---

## B. Phase 0 block — paste at the top of EVERY task session

```
PIPELINE CREDENTIALS AND TOOLS (all verified live 2026-09-06)
- muapi CLI 0.2.7 at `which muapi`. Auth: Keychain service `muapi-cli`, account `api-key`. Base https://api.muapi.ai/api/v1.
  Balance: `muapi account balance`  (was $16.7020 USD). NEVER print the key.
  Validate before spending: `muapi run <model> -i k=v --dry-run`   (prints request, sends nothing, costs nothing)
  Exact price of a call: GET /api/v1/models  → `cost` (USD) and `estimate_endpoint` per model.
  Run: `muapi run <endpoint> -p "<prompt>" -i k=v --wait --download <dir> --output-json`
  Host a local file: `muapi upload <file>` → URL (needed for lipsync audio_url / video_url)
  Every generation response carries the REAL cost — capture it into the receipt, never re-estimate.
- Voicebox TTS (local, $0, running): health `curl http://127.0.0.1:17493/health`
  Profile id: 8dc47c3f-e9c4-42d3-99f1-ffa047e02f34  ("FAMtastic Narrator", kokoro, af_heart)
  Generate: `echo "<line>" | python3 scripts/voice/voicebox-tts.py 8dc47c3f-e9c4-42d3-99f1-ffa047e02f34 out.wav`
  Mux:      `./scripts/voice/narrate-film.sh film.mp4 out.wav <lead_s> out-narrated.mp4`
  Start if down: ~/Development/voicebox/tauri/src-tauri/binaries/voicebox-server-aarch64-apple-darwin --port 17493  (60-70s first boot)
  GOTCHA: Voicebox returns MONO. narrate-film.sh adds back the 3 dB the stereo split costs. Target mean -20.6 dB, peak -1.1..-2.6 dB.
- HyperFrames (local, $0): pin hyperframes@0.8.29 in every project.
  cd marketing/hyperframes/<project> && ./scripts/stage-assets.sh
  npx hyperframes@0.8.29 check --strict
  npx hyperframes@0.8.29 render --quality high --output renders/<name>-1080x1920.mp4
  node scripts/verify-render.mjs renders/<name>-1080x1920.mp4 verify     # exit 1 on grading violation
  Machine of record: Apple M5, 4 workers, Node v24.19.0, ffmpeg 9.0. 25-60s per film.
- HeyGen CLI v0.8.1 (authenticated): 461 premium credits, reset 2026-09-25. **OAuth EXPIRES 2026-09-12** — re-auth or lose it mid-build.
  Existing avatar "FAMtastic Guide" avatar_id a7994fb69c394554b62585c1c7235211. ~12 credits per ~40s take. Presenter, NOT a UGC character — do not blur the two (CAMPAIGN_ART_DIRECTION_V1.md:202).
- Gemini Flash Lite image: $0.0336/image at imageSize '1K', 9:16 proven. Worker website-delivery-swarm/gemini_flash_lite_image_worker.mjs. Keychain FAMtastic.Gemini.Image.
- OpenAI gpt-image-2: swift website-delivery-swarm/openai_image_worker.swift --preflight (passes today). ~$0.196-0.238/large image. HARD ceiling flag --max-cost-usd is REQUIRED with --execute.
- Photoshop MCP: mcp__photoshop-bridge__*, $0, Photoshop 2026 must be running. ps_create_text is ASCII-ONLY (spell "cents", never the ¢ sign) and takes fontFamily (PostScript name), not font. Inter/Space Grotesk are NOT installed — falls back to HelveticaNeue-CondensedBold.

FORMAT AND PLATFORM CONTRACT (docs/marketing/PLATFORM_CREATIVE_THEMES_V1.md)
- Every film: 1080x1920, 30fps, h264 High yuv420p, AAC 48kHz STEREO, +faststart.
- Duration: TikTok 15-34s · Shorts 15-45s · IG Reels/FB <90s · X autoplay is MUTED so frame one must carry the hook with no audio.
- Safe areas: TikTok is heaviest — keep everything inside the middle 60% of frame width, top 150px / bottom 250-300px clear.
- Max ~8-10 words of burned-in text on any single frame. Lime accent ONLY on the end price card, never a persistent wash.
- Caption caps enforced in code: x 280 · tiktok 2200 · instagram-standalone 2200 · youtube 5000.
- Postiz per-platform `settings` (scripts/queue-campaign-drops.py:109-138) — a single missing field rejects the WHOLE request:
  tiktok {privacy_level PUBLIC_TO_EVERYONE, duet/stitch/comment false, brand_content_toggle false, brand_organic_toggle false, content_posting_method DIRECT_POST, autoAddMusic "no"}
  instagram-standalone {post_type "post"} · x {who_can_reply_post "everyone"} · youtube {type "public"} · facebook {} · linkedin {}
  Channel map: instagram_reels|instagram_carousel -> instagram-standalone · youtube_shorts -> youtube · facebook_video -> facebook

CAMPAIGN FILE CONTRACT
- marketing/engine/schemas/posting-schedule.schema.json ($id posting-schedule.v2.json, schema_version const 2)
  Required top level: schema_version(=2), campaign_id, program_id, series_id (REQUIRED KEY, nullable value), time_zone, drops(minItems 1)
  Required per drop: drop_id (^[a-z0-9-]+$), content_id (^[a-zA-Z0-9_-]+$), scheduled_time, channels(minItems 1), copy(minProperties 1)
  scheduled_time = ISO 8601 WITH explicit offset (regex-enforced; absolute by design so a late-night cadence cannot mis-order)
  status enum: draft | ready_for_evaluation | armed_for_scheduling | completed | archived
  Drop states: idea -> copy_ready -> media_ready -> queued -> scheduled -> published
  Three independent approval booleans per drop: content, media, publish
  Copy keys by surface: tiktok_reels_shorts, x_post, facebook_instagram, linkedin_facebook, x_thread_opener, all_channels
  media_resolution.policy is const "resolve_at_runtime_fail_loud" — a missing file BLOCKS the drop by name with the exact path it looked for. Never posted image-only, never skipped.
  Schema has NO additionalProperties:false — campaigns legitimately add short_landing_url, channels_note, platform_settings, source_blog_post, surface_assets.
  manifest.json for a NEW campaign is NOT schema-validated (campaign-manifest.schema.json only covers 55-cents-17-day). It is a free-form editorial record; follow booked-and-losing's shape.
- SCAFFOLD, don't hand-write:
  python3 scripts/new-campaign.py --slug <slug> --name "<Name>" --drops 8 --anchor <ISO±offset> --interval <min> --program-id FAM-FOOT-199 [--series-id <id>]
  Then: python3 scripts/new-campaign.py --validate <slug>   (catches duplicate drop_id, reused content_id, unknown channel, literal "TODO", drops out of chronological order)
        python3 scripts/campaign_schema_validate.py marketing/campaigns/<slug>/posting-schedule.json
        python3 scripts/validate-campaign-schedule-hygiene.py    # CROSS-CAMPAIGN
- ⚠ GLOBAL TIMESTAMP UNIQUENESS: validate-campaign-schedule-hygiene.py FAILS on any duplicate `scheduled_time` across EVERY campaign in the repo, and on any duplicate provider id. See §D for the collision-free minute allocation — do not reuse a round hour.
- Queue: FAMTASTIC_MARKETING_PUBLISH=true python3 scripts/queue-campaign-drops.py --campaign <slug> --schedule
  Set the env var INLINE on the one command. Never export it. Never commit it. Unarmed runs only create DRAFTs and are safe.
  The script has NO --help; usage prints to stderr only when --campaign is missing.
- IDEMPOTENCY: adoption keys on `utm_campaign|utm_content`. Uniqueness comes from utm.campaign being distinct per campaign — but ALSO prefix content_id (bl-drop-01) per the booked-and-losing precedent. In 2026-09-05 three campaigns all used drop-01, the second adopted the first's live records, queued nothing, and printed "PASS — adopted=6".
- Over-limit copy is NOT truncated: the channel is excluded loudly. If every channel is over limit the drop is blocked_all_channels_over_limit.
- Stage 2 groups sibling records by `utm_campaign|utm_content`, never by publishDate (timestamp grouping once pulled an unrelated campaign's row in). A drop whose scheduled_time is already past is blocked_stale_date.
- VERIFY BY READING THE DATABASE, never the script's own PASS:
  docker exec postiz-postgres psql -U postiz-user -d postiz-db-local -tAc "select state, to_char(\"publishDate\" at time zone 'UTC','MM-DD HH24:MI'), substring(content from 'utm_content=([a-zA-Z0-9_-]+)') from \"Post\" where \"deletedAt\" is null and \"publishDate\">now() order by 2;"

BLOG LINKING (series-first)
- 98 live posts as of 2026-09-06. List them: curl -s -H "Accept: application/vnd.api+json" "https://famtasticdesigns.com/web/jsonapi/node/blog_post?filter[status]=1&page[limit]=50"
  `/web/jsonapi` is correct for THIS machine endpoint only — `/web/` must never appear in a published link.
- A campaign may only link to a post that is ALREADY LIVE (CAMPAIGN_PRODUCTION.md:16-19). There is NO code gate — it is your check. Run `python3 scripts/qa-content-links.py --json`.
- Blog URLs are canonical WITH A TRAILING SLASH; without it the server 301s. Use the slashed form so every link hits 200 on the first request.
- Objection/rebuttal posts already live to link against: /blog/why-running-business-on-gmail-and-linktree-costs-revenue/ · /blog/linktree-vs-real-website-what-you-trade-away/ · /blog/do-you-have-to-leave-the-booking-app/ · /blog/who-owns-your-client-list-booking-app/ · /blog/do-you-guarantee-google-rankings/ · /blog/199-website-inclusions-and-boundaries/ · /blog/what-happens-when-first-year-hosting-ends/ · /blog/how-much-do-you-charge-dms-costs-bookings/ · /blog/why-link-in-bio-page-doesnt-show-up-in-google/ · /blog/proof-first-website-see-before-you-pay/  (re-curl every one before use)

CLAIMS AND REPRESENTATION — NON-NEGOTIABLE
- FAM-FOOT-199 ($199, "55 cents a day") = ONE focused landing page + ONE year managed hosting + first-year domain registration (or connecting a domain already owned). THAT IS THE WHOLE BUNDLE.
  Business email is a separate $99 SKU (FAM-BUSINESS-EMAIL). Maintenance is an upsell (FAM-MAINTENANCE). FAM-BUSINESS-499 = up to five pages. Never conflate.
- NO invented statistics. Argue the mechanism instead. NEVER name or attack a competitor. NEVER promise a search ranking, a timeline, or a revenue outcome.
- Spell numbers for voice: "one hundred ninety-nine dollars", "fifty-five cents a day".
- Every public URL is curled before use. `/web/` must NEVER appear in a public link. Campaigns may only link to blog posts that are ALREADY LIVE.
- CHARACTERS ARE ILLUSTRATIVE, NEVER TESTIMONIALS. See §F. This is the highest-risk rule in this plan.

MEASUREMENT DISCIPLINE
- Never score a build before its run reports success. An in-flight render is UNMEASURED, not bad.
- ffprobe exit 0 is NOT evidence of a good film. Watch it, or at minimum verify duration/audio/luminance relationships.
- Before reporting any defect, ask what would have to be true for it to be a measurement artifact — then check that first.
```

---

## C. The production pipeline and its real economics

Every price below came from the live muapi models endpoint on 2026-09-06.

### C.0 The premium-anchor rule — what the expensive spend must leave behind

`CHEAP_PRODUCTION_ECONOMICS_V1.md:12` — *"Buy one premium thing per campaign. Recreate everything else for free, and remix."* And `:104-112` — *"The premium anchor is not simply one expensive item sitting in a set of cheap ones. It is the **style reference every cheap asset is matched against.** Buy the anchor first, then derive."* `:130-136` — generating cheap assets **before** the anchor exists means they have nothing to match and you pay twice.

**Owner directive layered on top: the premium spend must produce durable brand assets, never a one-off clip.** Every premium generation in this plan is bought once and then mined. A premium call that leaves nothing reusable behind is a failed call.

**What each premium buy must deposit into the brand asset library:**

| Artifact | Path | Reused by |
|---|---|---|
| Character reference sheet (front / 3-4 / profile / expression row) | `marketing/creative/cast/<char>/reference-sheet.png` | every later still, every campaign |
| Locked anchor still + sha256 | `marketing/creative/cast/<char>/anchor.png` + `anchor.sha256` | identity lock on all 32 clips |
| `character_id` (if Seedance training used) | `cast-bible.json` | permanent, reusable forever |
| **Measured grade tokens from the hero video** — mean YAVG, accent hex as it *renders*, accent area %, shadow floor | `marketing/creative/cast/anchor-tokens.json`, schema mirroring `heygen/reference-tokens.json` | the grading target every cheap clip is matched to |
| Wardrobe / setting / lighting constants | `cast-bible.json` per character | prompt reuse, drift prevention |
| Extracted stills from the hero video | `marketing/creative/campaign-assets/<slug>/plates/` | Photoshop stills, blog heroes, carousels |

Follow the anchor precedent exactly: measure the hero video the way `reference-tokens.json` was measured (five frames, mean luminance, accent-as-rendered, accent area fraction), and record it as tokens — **not as a guess**. The existing anchor's own boundary applies: *"Measured from ONE take. A second take under different lighting will move these values; re-measure rather than assuming."*

### C.1 Character creation — every installed route, ranked

Swept all three skill libraries. In priority order:

1. **`muapi-photo-pack-generator` — Identity Lock Prompting. Read this first; it is the doctrine.** Never re-describe the person (age, ethnicity, hair) in a scene prompt — that is what makes the model draw a *new* face. Reference the anchor and change only the scene. Mandatory negative prompt: `different person, altered face, new identity, generic face`.
2. **`gpt-image-prompt-architect` → "Blueprint 9: Character Consistency Anchor Workflow"** — the prompt-architecture companion to the above. Repo-local at `.agents/skills/gpt-image-prompt-architect/`.
3. **`muapi-storyboard-to-cooking-video`** — ignore the subject, steal the technique: it builds a **character reference sheet** ("preserve their exact face, hair color, hair texture, eye color, skin tone") and confirms likeness *before* animating. That gate belongs in T2.
4. **`muapi-character-story-video`** — the 3-phase shape this plan follows: establish character → generate scenes from that reference → animate each.
5. **`muapi-multi-angle-reshoot`** — the pose/angle matrix. Every prompt ends *"Maintain exact clothing and face consistency."* This is the T2 QA matrix.
6. **`muapi-seedance-2`** — *"high character consistency, zero facial flicker, persistent clothing details"*; the only **trained persistent character** route (`omni-train` → permanent `character_id`).
7. **`muapi-freeze-effect-video`** carries the escape hatch worth knowing: *"If the model rejects realistic human likeness, switch to the Global tier model which allows looser identity matching."* Seedance's Chinese tier **refuses realistic human faces** except in character/omni-train modes — that is why VIP/Global tiers exist.

### C.2 Tier 1 — the premium photoreal hero (buy ONCE, mine forever)

At least one video must be genuinely realistic. Priced options, cheapest-first:

| Model | Cost | Note |
|---|---|---|
| **`openai-sora-2-image-to-video`** | **$0.80** | **Best value photoreal. Recommended hero.** |
| `veo3-fast-image-to-video` | $0.60 | Cheaper, lower fidelity than Sora 2 |
| `kling-v3.0-pro-image-to-video` | $0.72 | |
| `seedance-2-vip-image-to-video` | $1.50 | Tolerates real human faces, native audio, 9:16, 4–15s |
| `openai-sora-2-pro-image-to-video` | $2.40 | Reserve |
| `veo3.1-image-to-video` | $2.50 | Not justified at this budget |

**Plan: one Sora 2 hero for C3 (`whats-your-secret`) — the campaign whose whole premise is two believable people talking.** $0.80. Its extracted frames, measured grade and character sheet become the anchor tokens every other clip is matched against. If Sora 2 refuses the likeness, fall back to `seedance-2-vip-image-to-video` ($1.50), which is documented to accept realistic faces.

### C.3 Tier 2 / 3 — the volume routes

### Route A — still-chain + animate + lipsync (RECOMMENDED default)
| Step | Model | Cost | Notes |
|---|---|---|---|
| 1. Character anchor (once per character) | `nano-banana` t2i, 9:16 | $0.03 | Locked into the cast bible |
| 2. Scene still | `nano-banana-edit`, `images_list=[anchor]`, `aspect_ratio 9:16` | $0.03 | Multi-reference = identity holds |
| 3. Motion | `pixverse-v5.5-i2v`, `images_list=[still]`, `aspect_ratio 9:16`, `duration 5\|8\|10`, `resolution 1080p`, `style none\|cyberpunk` | $0.10 | **`style: cyberpunk` is the futuristic-grunge lever** |
| 4. Voice | Voicebox local | $0.00 | |
| 5. Mouth | `sync-lipsync` (`audio_url`, `video_url`) | $0.04 | Feed it the muapi-uploaded Voicebox wav |
| **Per clip** | | **$0.17** | 1080p, 9:16, up to 10s |

### Route B — trained persistent character
| Step | Model | Cost |
|---|---|---|
| Train once per character | `seedance-2-omni-reference-train` | **$0.50** → permanent `character_id`, used forever as `@omni-character:<id>` |
| Per clip | `seedance-2-mini-omni-reference` | **$0.15** (480–720p ceiling, 4–15s, auto audio) |

### Explicitly excluded — priced and rejected
`seedance-2-vip-image-to-video` $1.50 · `veo3.1-image-to-video` $2.50 · `kling-v3.0-pro-image-to-video` $0.72 · `seedance-2.5-*` $1.70–$9.35 · any `-4k` variant. **32 clips on Veo3.1 would cost $80 against a $16.70 balance.** Do not use them without a new owner authorization.

### Worth one free probe
`gemini-omni-character` is listed at **$0.00**. T1 probes it. If it genuinely holds identity for free, it replaces step 2 and drops the per-clip cost to $0.14.

### Budget envelope (hard ceiling $12.00 of $16.70)
| Line | Calc | Cost |
|---|---|---|
| T1 pilot (Route A + Route B + free `gemini-omni-character` probe) | | ≤ $1.50 |
| **Premium hero — Sora 2 i2v, C3** (buys the anchor tokens + reference sheets) | 1 × $0.80 | **$0.80** |
| Premium hero retake allowance | 1 × $0.80 | $0.80 |
| Character reference sheets, 6 characters (`nano-banana-pro` for fidelity) | 6 × $0.12 | $0.72 |
| Cast anchors + pose matrix | 6 × $0.03 × 3 | $0.54 |
| 31 remaining clips, Route A | 31 × $0.17 | $5.27 |
| Retake buffer 40% | | $2.11 |
| Contingency | | $0.26 |
| **Total** | | **≤ $12.00** — leaves ≥ $4.70 |

`gemini-omni-character` is listed at **$0.00**. If T1 proves it holds identity, it replaces the paid scene-still step and returns ~$1.00 to contingency.

**Spend rules for the executor.** Check `muapi account balance` before and after every task and write both into the receipt. If a single task's spend exceeds its line above by more than 50%, STOP and report — do not continue. Every paid call gets a row in `marketing/campaigns/<slug>/evidence/cost-ledger.jsonl`: `{ts, task, model, endpoint, inputs_hash, request_id, real_cost_usd, balance_after, artifact_path, accepted|rejected}`. **A rejected derivative is evidence too — log it.**

---

## D. The four campaigns

All four route to `/general-video` per the HyperFrames entry point (character dialogue, multi-scene, not a URL showcase, not captions-on-footage, not beat-driven). Each gets a `BRIEF.md` written from §E — **the executor does NOT re-run the intent interview**; this plan is the interview's output.

| # | Slug | Angle | Palette | Look | Drops |
|---|---|---|---|---|---|
| C1 | `every-reason` | Objection → rebuttal. The reason someone doesn't have a site, said out loud, then answered. | `trades` — safety orange `#FF7A1A` on blue-black `#0D1117` | Vibrant, daylight, real rooms | 8 |
| C2 | `signal-and-static` | You are broadcasting on borrowed frequency. Own the signal. | `shutter` — stencil yellow `#E4C227` on cold steel `#161A1A` | **Futuristic grunge.** `pixverse style: cyberpunk` | 8 |
| C3 | `whats-your-secret` | Two peers, same trade, different results. One asks. | `famtastic` lime for v1 / `salon` rose-warm for v2 | Post-workout / post-shift, handheld, unstaged | 8 |
| C4 | `front-desk` | One recurring host answers a real question from the blog corpus, on camera. | `paper` — ink `#141210` on warm paper `#F4F1EA` | Bright, clean, native-social | 8 |

**Why `shutter` for C2 and not a new palette:** it already exists in `famtastic-social-frame.jsx:127-148`, is argued from its subject, and is documented as a deliberate divergence from the anchor luminance band (ground 24/255 vs the 150–175 anchor). The file's own rule stands: **a `shutter` frame must never be cut beside an `anchor-take-a` frame.** C2 therefore never shares a carousel or a film with C1/C4.

### C1 `every-reason` — the eight objections
Each drop: character says the objection in their own words (5–8s), beat, then the answer as a mechanism — never a promise. Draw the answers from live blog posts only.
1. "I get all my customers from Instagram." 2. "I can't afford a website." 3. "I'm not techy." 4. "I've got a Linktree." 5. "My customers already know where I am." 6. "I tried one before and nothing happened." 7. "I don't have time." 8. "My work is all word of mouth."

### C2 `signal-and-static` — futuristic grunge
Near-future, rain-lit, rust-and-chrome. The recurring image: a business whose sign is a rented hologram that flickers off when the platform decides. **Zero invented statistics; the argument is ownership, not fear.** `pixverse-v5.5-i2v` `style: cyberpunk`, `resolution 1080p`. This campaign deliberately breaks the luminance anchor and must say so in writing in its README, following the precedent set by `already-know-the-game` and `ghost-town`.

### C3 `whats-your-secret` — the owner's scene, two versions
The 2-hander. Two peers in the same trade, just finished work. One is doing better. The other asks how.
- **v1 — two young Black personal trainers**, gym floor, post-session, towels, water bottles. Warm dusk through the window.
- **v2 — two white contractors/landscapers**, tailgate of a truck at end of shift, different field per the owner's direction.
- Both land the same beat: *"I got a page of my own. FAMtastic. One hundred ninety-nine dollars — the site, a year of hosting, and the domain."* Then the end card.
- **4 drops per version = 8.** Each is a different moment in the same conversation, not eight retellings.
- **This is the highest-risk campaign in the plan. §F governs it and is not optional.**

### C4 `front-desk` — the recurring host
One character, one setting, answering one real question per drop, mined from the 98 live blog posts and the FAQ. This is the campaign that builds face-recognition across the whole set, and the host reappears as a cameo in C1 and C2. Questions must come from actual published content, and each drop links to the live post that answers it.

### Scheduling — 8 drops/day, STARTS TODAY (owner override, non-negotiable)
**Owner directive overrides the "wait for the backlog to clear" recommendation: Day 1 is today, the actual calendar day T8 runs, not a future date.** `time_zone: America/New_York`. Do not hardcode `2026-09-06` into any script or file — compute `day1 = date -u +%F` (or the ET equivalent) at the moment each task actually runs, since T1–T7 take real hours and "today" may roll over before T8 queues.

⚠ **`validate-campaign-schedule-hygiene.py` fails on any duplicate `scheduled_time` across EVERY campaign in the repo**, and today already has other campaigns' posts queued (48 total, through 09-13) — a collision is not hypothetical. **Each campaign owns a distinct minute offset, permanently:**

| Campaign | Minute | Slots (ET) |
|---|---|---|
| C1 `every-reason` | **:05** | 07:05, 11:05 |
| C2 `signal-and-static` | **:20** | 09:20, 15:20 |
| C3 `whats-your-secret` | **:35** | 13:35, 19:35 |
| C4 `front-desk` | **:50** | 17:50, 21:50 |

Two drops per campaign per day × 4 campaigns = 8/day, for 4 calendar days starting today. Every `scheduled_time` stays globally unique. **Never a `shutter` (C2) drop adjacent to a `paper` (C4) drop** — the offsets already keep them 2+ hours apart.

**Stale-slot rule for Day 1 only** (this is real: as of plan approval it is already 14:50 ET, so C1's 07:05/11:05 slots are already past): at queue time (T8), for each Day-1 slot whose computed `scheduled_time` is not at least 20 minutes in the future, **do not delete it — push it forward in 24-hour increments** until it lands on a free future slot (its own campaign's minute offset, next available day), and record the shift in that campaign's README under "Schedule adjustments." This never produces `blocked_stale_date` and never invents a new time slot outside the fixed per-campaign minute. `queue-campaign-drops.py --requeue <drop_id> --at <ISO±offset> --confirm` is the correct primitive if a drop was already queued at a now-past time. Run the hygiene validator **before** queueing, not after, and re-run it after any shift.

Channel routing per drop: **Facebook + Instagram always. X only when the copy fits 280 characters** — write an `x_post` variant for every drop and let the length decide; do not truncate a longer caption. TikTok and YouTube records are **built and held** (T9).

---

## E. Task-by-task

### T0 — Commit both plans (30 min, $0)
Create `plans/ugc-character-flood/plan.md` (this file) and `plans/owner-dashboard/plan.md` (recovered from the transcript path in the notice above). Register both per the repo plan convention. Verify with `node scripts/plans/audit.js`.
**Done when:** both files exist on `main`, audit reports them active, and neither plan lives only in a session.

### T1 — Cost + identity pilot (1h, ≤ $1.50) — GATES EVERYTHING
Do not build a campaign before this returns receipts.
1. `muapi account balance` → record opening balance.
2. **Dry-run first, every time:** `muapi run nano-banana -p "<anchor prompt>" -i aspect_ratio=9:16 --dry-run`.
3. Generate ONE anchor for the C3-v1 lead character. Record real cost from the response.
4. **Route A**: `nano-banana-edit` scene still → `pixverse-v5.5-i2v` (9:16, 1080p, duration 8) → Voicebox line → `muapi upload` the wav → `sync-lipsync`. Record every real cost.
5. **Route B**: `seedance-2-omni-reference-train` on the same anchor → `seedance-2-mini-omni-reference` for the same line.
6. **Free probe**: `gemini-omni-character` — confirm whether $0.00 is real and whether identity holds.
7. Put the two clips side by side and judge: identity fidelity, hands, wardrobe, lip-sync believability, resolution, whether it reads as UGC or as an ad.
**Verification:** `marketing/campaigns/_pilot/evidence/cost-ledger.jsonl` has a row per call with `real_cost_usd`; balance delta equals the ledger sum ±$0.01; both clips are 9:16 and playable; a written verdict names the winning route **and why**, with the losing clip kept as evidence.
**Anti-patterns:** scaling to 32 clips before this verdict; using an excluded model from §C; trusting the skill files' "~250 credits" numbers (credits ≠ USD here — the account is billed in USD).

### T2 — Cast bible + premium hero + brand assets (4h, ≤ $2.90)
Create `marketing/creative/cast/cast-bible.json` + one directory per character. **This is the reusability surface and the brand-asset deposit — everything after depends on it, and this is where the premium spend must leave something behind.**

**Read before writing a single prompt:** `muapi-photo-pack-generator` (Identity Lock), `gpt-image-prompt-architect` Blueprint 9, and the two representation files named in §F.

1. **Reference sheet per character** — `nano-banana-pro` ($0.12 × 6). One sheet each: front, three-quarter, profile, and an expression row, all one person. Technique lifted from `muapi-storyboard-to-cooking-video`: *"preserve their exact face, hair color, hair texture, eye color, skin tone."* **Confirm likeness before animating anything.**
2. **Anchor still + sha256** per character; pose matrix via `muapi-multi-angle-reshoot`'s pattern (every prompt ends *"Maintain exact clothing and face consistency"*).
3. **The premium hero** — one `openai-sora-2-image-to-video` clip ($0.80) for C3's lead pair. This is the realism proof and the style reference.
4. **Mine it into brand assets** (§C.0): extract stills to `plates/`; measure the hero the way `heygen/reference-tokens.json` was measured — five frames, mean YAVG, accent hex **as it renders**, accent area fraction, shadow floor — and write `marketing/creative/cast/anchor-tokens.json`. Every cheap clip is then graded to *these* numbers.

Per-character record: `character_id` (slug), `anchor_image` + **sha256**, `reference_sheet`, `muapi_character_id` if Route B wins, immutable traits (age range, build, skin tone, hair, wardrobe, setting), voice profile, approved palette, lighting rule, **forbidden changes**, verbatim `baseline_prompt`, and the mandatory negative prompt `different person, altered face, new identity, generic face`.

**Cast (6):** C3-v1 lead + partner (young Black trainers) · C3-v2 lead + partner (white contractors) · C4 host (the anchor face, cameos in all four campaigns) · C1/C2 utility character. Per the `booked-and-losing` precedent: span different ages, features and hair; **no costume or prop used to say who anyone is**; competence is the baseline, never struggle.

Follow `VIDEO_STORY_ENGINE.md:57-99`: vary **one dimension at a time**, run the pose matrix, QA identity/wardrobe/hands/background before promoting a character.
**Verification:** each character has ≥3 stills from different scene prompts a human agrees are the same person; every anchor hash recorded; `cast-bible.json` and `anchor-tokens.json` valid JSON; the Sora 2 hero plays and is measurably 9:16; a written provenance + consent-basis line per character (§F rule 5).
**Anti-patterns:** re-describing the person in a scene prompt (that is what draws a new face); animating before the reference sheet is approved; buying premium output that leaves no reusable token or sheet behind; **letting any generated image carry baked-in text** — `CHEAP_PRODUCTION_ECONOMICS_V1.md:142-147`: *"Every plate prompt must forbid baked-in text. Generated typography is unreliable and always off-brand. Type is set in Photoshop, over the plate."*

### T3–T6 — The four campaigns (4–6h each, ≤ $1.60 each)
Identical shape per campaign; run as parallel lanes.
1. **Scaffold**: `marketing/campaigns/<slug>/` with `manifest.json`, `posting-schedule.json` (schema v2, all required fields, `content_id` **campaign-prefixed**), `README.md`, `evidence/`, and `marketing/creative/campaign-assets/<slug>/`.
2. **Write 8 drops** — headline, `copy.facebook_instagram`, `copy.x_post` (≤280), `copy.tiktok_reels_shorts`, the spoken line, and the live blog URL each links to (**curl every URL first**).
3. **Render 8 films** via the T1-winning route; brand layer, captions and end card in HyperFrames ($0, local) over the generated footage.
4. **Narrate** with Voicebox; mux with `narrate-film.sh`; confirm stereo and loudness.
5. **Grade + verify**: `node scripts/verify-render.mjs <film> verify`. There is **no shared module** — seven forked copies exist, each with its own constants. Fork `campus-entrepreneurs`' copy (anchor-matched, `BAND [150,175]`, `ACCENT_MAX 2.0`) for C1/C3/C4, and `ghost-town`'s (divergent, `BAND [30,86]`, `ACCENT_MAX 3.0`, `COOL_MAX 1.0`) as the shape for C2. Change the constants **and the comment that argues them**.
   **C2's divergence must be recorded in THREE places** — the precedent is explicit that *"the note is duplicated in three places so it cannot be lost"*: the `PALETTES` comment block, `manifest.json → anchor_divergence`, and the campaign README. Each must carry **measured** numbers (mean luminance, accent area %) against the 150–175 / 1–2% anchor, plus a **reproduction command** so the number is re-derivable. `already-know-the-game` is the model: *"a ~100–125 point deliberate luminance divergence, not an error and not drift."* And the enforced consequence: a `shutter` frame **must never be cut in beside `take-a`**.
6. **Receipts**: `evidence/cost-ledger.jsonl`, identity QA sheet, ffprobe output per film.
**Verification per campaign:** 8 films at exactly 1080×1920/30, AAC stereo, duration inside the platform band; `python3 scripts/validate-campaign-schedule-hygiene.py` passes; every linked URL returns 200 with no `/web/`; no drop claims a statistic; no drop conflates the $199 bundle; identity QA signed off.
**Anti-patterns:** bare `drop-01` content ids; a `shutter` frame beside an `anchor-take-a` frame; more than ~10 words burned into a frame; lime as a persistent wash; the ¢ sign in a Photoshop text layer; scoring a film before its render reports success.

### T7 — Film library + video-blog SEO capture (4h, $0)
This is the half that makes the blocked channels stop costing you. `filmLibrary.js:4-10` states the reason this exists: *"Twelve films were rendered for the campaigns and none of them earned anything… A film that lives only on disk is indistinguishable from a film that was never made."*

**⚠ A Drupal blog post CANNOT embed video today.** Confirmed four independent ways: the publish pipeline emits `basic_html` only (`publish-blog-draft.py:641-721`); Drupal's `basic_html` `allowed_html` has no `<video>`, `<source>`, `<iframe>` or `<figure>`; the `blog_post` bundle has no video field (`drupalAdapter.js:246-304`); and `grep watch|film` over `BlogPostPage.jsx` returns zero. Anything claiming otherwise is wrong.

1. **Films to the library.** Add all 32 to `frontend/src/lib/filmLibrary.js` with **ffprobe-derived** duration, dimensions, bytes — re-probe, never copy from a README (`ghost-town`'s README was wrong about its own audio; the library corrected it). Command is in the file header at `:26-29`. `tagline` is the `message:` line from the film's STORYBOARD **verbatim**; `onScreen` is the film's on-screen copy verbatim, confirmed against extracted frames; **`transcript` only where a verbatim script exists in-repo and the audio was verified to match — otherwise `null`.** Inventing the words is trivial and wrong.
2. **Poster** JPEG extracted from each film into `frontend/public/video/`. `.gitignore:42` is `*.mp4` but `:52` is `!frontend/public/video/*.mp4` — this directory is the committed exception.
3. **No `.htaccess` change needed** — `^watch/?$` and `^watch/[^/]+/?$` are slug-generic with `!-f`/`!-d`. (The block is duplicated verbatim at `:47-67` and `:69-88`; harmless, worth deleting one copy.)
4. **Video-on-blog — use the shipped precedent, Route A.** `BlogPostPage.jsx:30-42` already injects `<figure>` markup into eight campaign posts client-side via `dangerouslySetInnerHTML`, bypassing `basic_html` entirely. Extend that same mechanism with a slug→film map that renders a film card / `<video>` at a chosen paragraph offset, and add the film's `VideoObject` to the post's JSON-LD. Do **not** switch the corpus to `full_html`, and do **not** add a Drupal field for this — both are larger, riskier changes than this earns.
   To make it visible to crawlers it must also land in the prerendered shell: extend `generate-seo-shells.mjs`'s body path (`:398-427`, `fieldMarkup()`), the same place the film shells are already written.
5. **Blog posts themselves**: `scripts/publish-blog-draft.py --draft <slug> --dry-run` then `--confirm`. Each draft folder needs exactly `draft.md`, `brief.md`, `seo-check.json`; `seo-check.json` must have `pass:true`, a `title`, a `meta_description`, and `internal_link_count >= 3`; a `DRAFT_CLASSIFICATION` row with `series` (exact title from the **11** in `famtastic-content-series.json`), unique `series_order`, `primary_keyword`, non-empty `secondary_keywords`. Series is a **hard pre-SSH validation failure** — three posts were once silently orphaned from the series architecture this way. Beware: `DRAFT_CLASSIFICATION` has **duplicate keys for six slugs and the later entry wins**.
6. `npm --prefix frontend run build` → confirm `dist/watch/<slug>/index.html` per film with valid JSON-LD.
**Verification:** 32 × `/watch/<slug>` shells built; JSON-LD `duration`/`width`/`height`/`contentSize` match ffprobe 32/32; the film card renders in both the SPA and the prerendered shell; **no post published without its film actually present**.
**⚠ Verify `/watch` is actually deployed in production before any campaign links to it** — the video commit previously failed to push (TLS `bad record mac` on large transfers). `curl -s -o /dev/null -w '%{http_code}' https://famtasticdesigns.com/watch/borrowed-land/` must be 200.

### T8 — Queue 32 drops (2h, $0)
Per campaign: `FAMTASTIC_MARKETING_PUBLISH=true python3 scripts/queue-campaign-drops.py --campaign <slug> --schedule` (env inline, never exported).
**Then verify from Postgres, not from the script's PASS** — the read-back query is in §B. Expect exactly 32 new `utm_content` values, all campaign-prefixed, none adopted from another campaign, spread 8/day across Sept 14–17.
**Anti-patterns:** trusting `PASS — adopted=N`; scheduling into a day that already carries backlog; letting two campaigns share a `content_id`.

### T9 — Held TikTok/YouTube records + replay (1h, $0)
Build the TikTok and YouTube drop records with correct `settings` (§B) but leave `approval.publish=false` so they cannot fire. Write `marketing/campaigns/_shared/UNBLOCK-REPLAY.md`: the exact command to flip all 64 held records live once TikTok clears app review and YouTube is re-authed, plus the pre-flight (`refreshNeeded=f`, `disabled=f`, one test post read back from the DB before the batch).
**Why this matters:** the films are already made and already earning on `/watch`; unblocking becomes a one-command replay instead of a rebuild.

### T10 — Docs, registry, closeout (1h, $0)
- `docs/CHANGELOG.md` — dated entry per campaign.
- `docs/CAPABILITY_REGISTRY.md` — **correct the stale "no post confirmed live" row** using the FB/IG/X `releaseURL`s in §A; add a muapi row **only at the evidence level the receipts support**. Never upgrade a classification without proof.
- `marketing/providers.json` — fix the muapi row (CLI present, authenticated, keychain-sourced, USD-billed) and add measured per-model costs.
- `.site-context/SITE-LEARNINGS.md` — the muapi discovery, the credits-vs-USD trap, the `utm_content ≠ content_id` idempotency rule, and the stalled-QUEUE-with-no-Temporal-workflow signature.
- Drive mirror; plan closeout packet.

---

## F. Character ethics — the rule that governs C3 and every face in this plan

C3 is testimonial-shaped: a person says a product made their business better. **It is not a testimonial and must never be able to read as one.** These characters are generated. No real customer said this. Getting this wrong is the failure mode that produces a fabricated endorsement.

**Binding rules:**
1. **No fabricated outcome, ever.** No character states or implies revenue, bookings, ranking, or growth attributable to FAMtastic. The "secret" is that the product exists and what it includes — never what it produced. Cut any line like "I doubled my clients."
2. **No numbers a customer couldn't verify.** The only figures permitted are catalog truth: $199, 55 cents a day, one page, one year of hosting, first-year domain.
3. **No implied real person.** Characters carry invented first names only, no surnames, no business names, no locations that read as a real identifiable business. No character may be described anywhere as a customer, client, or user of FAMtastic.
4. **Disclosure.** Every C3 drop's caption carries a plain line — *"Dramatization. Characters are illustrative."* — and the drop record carries `representation: "synthetic_illustrative"`. It ships in the copy, not just the metadata.
5. **Provenance.** Generated faces are recorded in the cast bible with model, prompt, anchor hash and date. If any anchor is ever derived from a real photograph, that photo's rights basis is recorded before use, and without a rights basis it is not used.
6. **Culture is specific, not decorative.** The owner asked for a young Black version and a white version in different fields. Write each as a specific person in a specific trade with their own idiom — not one script with the skin tone swapped. Same beats, genuinely different writing. No dialect performed as a costume, no trade rendered as a stereotype.
7. **The presenter/character line holds.** The HeyGen "FAMtastic Guide" avatar is the *brand presenter*. These UGC characters are not it, must not resemble it, and the two never appear in the same film (`CAMPAIGN_ART_DIRECTION_V1.md:202`).

If any drop cannot satisfy all seven, it does not ship. Say so in the run report rather than softening the rule.

**Rules 1–3 are already repo doctrine, not my invention.** `BRAND.md:130` and `docs/DEMAND_ENGINE_DOCTRINE.md:80` both forbid inventing *"statistics, testimonials, customer outcomes, urgency, scarcity, or competitive claims."* `docs/AGENT_OPERATING_CONTRACT.md:84`: *"No synthetic numbers, fake affordances, or test data strings in customer-facing views."*

**Rules 4–5 (disclosure, provenance) are new.** No rule anywhere in the repo governs likeness rights, AI-generated people, or implied endorsement — I checked `BRAND.md`, `VOICE.md`, `AGENTS.md`, `DEMAND_ENGINE_DOCTRINE.md`, `CAMPAIGN_ART_DIRECTION_V1.md`, `PLATFORM_CREATIVE_THEMES_V1.md`. This plan establishes them; T10 promotes them into `BRAND.md` so the next campaign inherits them.

**Rule 6 has strong precedent — read these two files before writing a single character prompt.** They are per-campaign JSON, so a cheaper model will never find them unaided:
- `marketing/campaigns/booked-and-losing/manifest.json:70-85` — the `representation` block: *"Genuinely multicultural, weighted toward Black professionals, showing competent people at work in their own shops. Physical description only — skin tone, hair, age, dress, posture. **No ethnic or national signifier used as shorthand.**"* And, on the Haitian request specifically: *"Nationality cannot be depicted without reaching for exactly the signifiers this brief forbids. Instead the Black professionals shown span different ages, features and hair (a fade, long locs, natural coils), and no frame relies on a costume or prop to say who anyone is."*
- `marketing/creative/campaign-assets/booked-and-losing/plate-library.json` → `representation_rules`, all four, especially: *"Competence is the baseline, never struggle"* · *"The missing revenue is carried by objects and situations… Never by a sad face"* · *"No character is depicted as pitiable, overwhelmed or failing. Posture reads as unhurried and in command in every frame."*
- **The dignity rule**, `booked-and-losing/manifest.json:20`: *"These are skilled business owners who are busy and good at their trade. They are missing revenue they earned, not failing. No drop, image or line implies otherwise."*
- **C2 additionally inherits `already-know-the-game`'s `tone_contract`**: address the skill never the past; never name the audience; respect never pity; and verify every line against those rules *before* rendering.

**Two BRAND.md rules that specifically govern C1 (the objection campaign):**
- `BRAND.md:105-108` — *"**The excuse/rebuttal series is a teaching device, not a sales device.** If the honest answer to an objection is 'you may not need this yet,' say that."* At least one C1 drop must actually do this, or the campaign is sales wearing a teaching costume.
- The editorial test: *"would this post be worth reading by someone who will never buy anything from FAMtastic? If no, it is marketing wearing an article's clothes, and it fails."*
- **There is no competitor list, internal or external.** Do not write against a rival, a category, or a tool. A reader who is postponing is not an adversary.
- **Renewal disclosure is mandatory** whenever first-year pricing appears: *"$199 first year, then $9.99/mo, plus the cost of the domain."* Not optional, not a footnote.
- Voice ceilings (`VOICE.md:19-46`): sentences **47 words hard cap, median 19**; paragraphs **66 hard cap, 33 average**; headlines **under 60 characters**; **no numbered listicles** (zero of 88 published posts use them); second person for the reader, first person *plural* for FAMtastic, third person for the product.

---

## G. Risk register
| Risk | Impact | Mitigation |
|---|---|---|
| Budget overrun on paid generation | Balance is $16.70, hard | Per-task ceilings in §C; balance checked before/after each task; every call `--dry-run`'d first; excluded-model list is explicit |
| Character identity drifts across 8 clips | The whole premise fails | T1 gate; identity-lock prompting; anchor hash; one-dimension-at-a-time; QA sheet per campaign; rejected derivatives logged |
| C3 reads as a fake testimonial | Real reputational and claims exposure | §F, all seven rules, disclosure in the caption itself |
| `content_id` collision adopts another campaign's records | Silent — the script reports PASS while queueing nothing | Campaign-prefixed ids; DB read-back is the only accepted proof |
| **HeyGen OAuth expires 2026-09-12** | Mid-build credential loss | Not on the critical path (Voicebox carries voice). Re-auth only if a HeyGen take is actually wanted |
| Postiz worker dies (OOM precedent) | Everything queues and nothing fires | Before T8: `docker ps`, colima ≥8GiB, and check for QUEUE rows past their publish time with no open Temporal workflow |
| X flakiness ("Unknown Error" ×3) | Third channel drops out | X is additive, never the only channel for a drop; failures logged, not retried blindly |
| `shutter` beside `anchor-take-a` | Visibly broken grade | C2 is isolated from C1/C4 in scheduling and never shares a carousel |
| Films rendered but never distributed | The existing failure — 14 films, zero earned | T7 lands every film on `/watch` + a blog post before T8 queues anything |
| muapi model deprecation / dynamic pricing | Cost changes under us | `dynamic_pricing: true` on most models — re-read `cost` from `/models` at the start of every paid task |
| **Doctrine contradiction on palette** | Executor freezes or picks wrong | `PLATFORM_CREATIVE_THEMES_V1.md` §0 mandates `#070907`/`#7cfc00` on every surface; `CAMPAIGN_ART_DIRECTION_V1.md` Rule 1 says that is the *site* identity only and campaigns must vary palette. **Rule 1 governs** — it is what every shipped campaign cites and `AGENTS.md:106-108` names it in the precedence list. §0 is superseded for palette only. |
| **The "48 hours" claim is still live in doctrine** | An executor copies it into new creative | `docs/DEMAND_ENGINE_DOCTRINE.md:9` still says *"3 interactive design proofs in 48 hours"* **and** lists business email inside the $199 offer. Both are false against `backend/config/famtastic-products.json`. Every campaign since 2026-09-05 excludes both in writing. **Do not use either.** Flag for correction; do not fix the doctrine file inside this plan. |
| Seedance Chinese tier refuses realistic faces | A character run fails mid-batch | Documented: realistic human faces are rejected except in `character`/`omni-train` modes. Use VIP/Global tiers, or the `muapi-freeze-effect-video` escape hatch (Global tier, looser identity matching) |
| Brand fonts absent | Photoshop text silently substitutes | Inter and Space Grotesk are **not installed**; Adobe Fonts has zero activations. Output falls back to `HelveticaNeue-CondensedBold`/`AvenirNext`. `ps_create_text` is **ASCII-only** — spell "cents", never `¢` — and takes `fontFamily` (PostScript name), not `font` |

## H. Effort
T0 0.5h · T1 1h · T2 2h · T3–T6 4–6h each (parallel) · T7 3h · T8 2h · T9 1h · T10 1h ≈ **16–22h serial, ~10h with four parallel lanes.** Provider spend ≤ $12.00.

## I. Open decisions for the owner
1. **Dates** — recommended Sept 14–17 (unbroken runway after the existing backlog). Say the word if you want it densified into Sept 7–13 instead.
2. **C4 is my pick** (`front-desk`, recurring host). Swap it for anything you'd rather have.
3. **X inclusion** — recommended additive, copy-length-gated. Say if you want X excluded until the "Unknown Error" cause is found.
