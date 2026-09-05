# Platform Creative Themes v1

**Status**: Active reference for creative production and paid/organic distribution.
**Created**: 2026-09-04
**Scope**: YouTube long-form, YouTube Shorts, TikTok, X, Facebook, Instagram.
**Origin doctrine**: `docs/architecture/SERIES_FIRST_CONTENT_ORIGIN_V1.md` — every hook
and theme below is derived from the **platform-dependency blog series**
(`marketing/blog/clusters/cluster-own-website-vs-rented-platforms/cluster-plan.json`),
whose Part 1 is the only currently published post:
`https://famtasticdesigns.com/blog/why-running-business-on-gmail-and-linktree-costs-revenue`
(curl-verified 200 on 2026-09-04). Parts A1/A2/B1/B2/C1/C2 are plan-only — nothing in
this document or in `marketing/campaigns/platform-dependency/ad-variants.json` links to
them. That is the exact class of bug this doctrine exists to prevent (see
`docs/architecture/SERIES_FIRST_CONTENT_ORIGIN_V1.md` §"Evidence this is real" — a
live post linked to an article that didn't exist yet, and 404'd for a day).

**No invented statistics. No competitor names as targets. No pricing beyond what
ships in `backend/config/famtastic-products.json`** (`FAM-FOOT-199`, $199 first
year ≈ 55¢/day, then `FAM-HOST-999` at $9.99/mo). The pillar post itself contains
two unsourced figures ("74%", "40%") that predate this stricter rule — do not carry
them into new creative. Every theme and hook below argues from **mechanism**, per
VOICE.md's documented signature move ("name the mechanism instead of citing a
statistic"), not from a number nobody can source.

---

## 0. Shared Design DNA baseline (applies to every surface below)

Pulled from `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md` and the
project's standing brand tokens. Do not restate or reinterpret these per surface —
apply them as fixed constants:

| Token | Value | Rule |
|---|---|---|
| Ground / background | `#070907` (near-black charcoal) | The base of every frame, card, and end-card. Never pure `#000`, never a stock-photo-lit background behind text. |
| Signature accent | `#7cfc00` (FAMtastic Lime) | The **only** accent color. Used for the CTA, the price callout, or one highlighted word — never for both simultaneously. |
| Single-glow rule | `box-shadow: 0 0 24px rgba(124,252,0,.35)` | **Exactly one** glowing element per frame/screen, always on the next action (the CTA button, the price card). A second glow anywhere in the same frame is a violation, not a stylistic choice. |
| Typography | Inter / system-ui sans-serif | Uppercase, letter-spaced eyebrows for labels ("WEB BASICS", "55¢/DAY"); sentence-case for body and dialogue. No script fonts, no stock "agency" serif treatments. |
| Touch targets | 44px minimum | Any tappable on-screen element (a "swipe up," a captioned button graphic, an end-card subscribe prompt) must render at or above the 44px-equivalent tap area at the target device's resolution. |

**What violates the DNA on any surface**: two glowing elements in one frame, lime
used as a decorative color rather than an action signal, stock photography of
unrelated people in suits/handshakes, a competitor name or logo anywhere in frame,
an invented percentage on screen.

---

## 1. YouTube (long-form)

**Dimensions**: 16:9, minimum 1920×1080 (upload at the source resolution the video
was produced at; 4K 3840×2160 accepted where available — do not upscale).
**Safe area**: keep all key text and the logo inside a 90% center-safe margin
(5% inset each edge). Reserve the bottom-right corner for the platform's own
progress bar and, in the final 5–20 seconds, the subscribe/end-screen module —
do not place CTA text there.
**Duration**: no platform-enforced cap. House target for this content: 3–6 minutes
for a full explainer cut (the "watch the full breakdown" variants in
`ad-variants.json` are written to this length), or 30–90 seconds for a
direct-response cut repurposed from a Short.
**Caption/text limits** (code-enforced by the posting pipeline,
`scripts/queue-campaign-drops.py`): title ≤100 characters, description ≤5,000
characters (`CONTENT_LIMITS["youtube"] = 5000`). YouTube's own on-platform caption
(closed-caption track) has no meaningful length cap; the constraint that matters
here is the code path this content actually ships through.

**Creative theme** — what a FAMtastic long-form asset looks and sounds like:
A single presenter or screen-recording voice walks through one real mechanism from
the series (the trust check on a gmail quote, the DM that never gets answered, the
follower list that isn't yours) using an actual on-screen example — a real
`@gmail.com` address next to a real custom-domain email, a real DM thread, a real
"my account got flagged" scenario — never a stock illustration. The video ends on
a single `#070907` end-card: the price (`$199 first year · ~55¢/day · $9.99/mo
after`) inside the one glowing card, and nothing else glows. No unrelated agency
b-roll (handshakes, generic laptops-in-cafés stock footage).

**Hook formula (first 3 seconds)**: Open cold on the actual tension line from the
series, not a logo bumper and not a greeting. Examples, lifted directly from the
pillar post's three documented "leaks" (`marketing/campaigns/cost-is-not-the-reason/articles/why-running-business-on-gmail-and-linktree-costs-revenue.md`):
- Credibility: *"Would you wire a $1,500 deposit to quotes@gmail.com?"* — show the
  literal address on screen at second 0.
- DM chaos: *"How many times did someone DM you 'how much?' this week?"* — show a
  real-looking DM thread, timestamped late at night.
- Rented land: *"If Instagram disappeared tomorrow, how would you reach a single
  past customer?"* — show a follower count, then a strikethrough over it.

This is the general formula for the surface: **state the actual moment of risk
from the series in the form of a direct question to the viewer, illustrated with
a literal (not abstracted) example, before any logo or narration setup.**

**Design DNA on this surface**: lower-third labels in Inter, uppercase, small
letter-spacing. The single glow lives only on the end-card CTA. The 44px rule maps
to the minimum tap size of the end-screen subscribe/next-video element YouTube
renders on mobile — do not shrink a custom end-card button below that.

**What NOT to do**: no invented "X% of businesses" claim on screen or in narration;
no competitor name or logo (not even blurred); no second glowing element while the
price card is glowing; no burying the price past the midpoint of the video; do not
open on a logo animation before the hook line.

---

## 2. YouTube Shorts

**Dimensions**: 9:16, 1080×1920.
**Duration**: YouTube's platform-published Shorts ceiling is 3 minutes (raised from
the earlier 60-second cap). House target for this ad format: 15–45 seconds — long
enough to land the hook and the price, short enough to hold a cold scroll.
**Safe area**: reserve the top ~150px for the Shorts chrome (search/more-options
icons) and the right-side ~200px column for like/comment/share/remix icons. Reserve
the bottom ~300px for the channel name, video title overlay, and audio-track label
YouTube renders automatically. Keep the hook line and any burned-in CTA text inside
the remaining center-safe column (roughly 1080×1350, vertically centered).
**Caption/text limits**: Shorts posts through the same "youtube" integration as
long-form in this pipeline, so the description field carries the same ≤5,000
character cap. On-screen burned-in text should be far shorter by design — no more
than ~8–10 words visible on any single frame.

**Creative theme**: A vertical crop or a purpose-shot vertical version of the
long-form hook, cut to the tension line and the price only — no full explainer
inside the Short itself. Ends by pointing to "full breakdown in the description,"
which is the one place the full tracked link lives (Shorts descriptions are not
click-through the way a video card is).

**Hook formula (first 3 seconds)**: identical mechanism to long-form, compressed to
a single sentence with no wind-up: *"Would YOU send $1,500 to quotes@gmail.com?"* /
*"'How much do you charge?' — again. In the DMs. At 11pm."* / *"If Instagram
vanished tomorrow, how would you reach ONE past customer?"* Land the line by
second 2, not second 3 — Shorts autoplay in a feed with the least patience of any
surface here.

**Design DNA on this surface**: single glow reserved for the price line only, shown
once, near the end, never persistent throughout. Typography stays Inter, larger
point size than long-form because of the smaller effective viewing area.

**What NOT to do**: don't place the hook text in the reserved top/right/bottom
zones — it will be clipped or obscured by platform chrome on most devices; don't
try to fit the full explainer into a Short (that is the long-form video's job);
don't loop back to a logo card before the hook.

---

## 3. TikTok

**Dimensions**: 9:16, 1080×1920.
**Duration**: platform upload ceiling is up to 10 minutes for most accounts; house
target for this ad format is 15–34 seconds, matching the existing
`cost-is-not-the-reason` video scripts already produced at this length
(`marketing/campaigns/cost-is-not-the-reason/video-scripts/commercial-scripts.md`).
**Safe area**: reserve the top ~150px (profile photo, follow button, live
indicator) and the right-side ~150–250px column (like/comment/share/sound-title
icons — TikTok's heaviest UI overlay of any surface here). Reserve the bottom
~250px for the caption text, username, and sound title TikTok renders under every
video. Center-safe text zone is roughly the middle 60% of the frame width.
**Caption/text limits**: 2,200 characters (`CONTENT_LIMITS["tiktok"] = 2200`,
code-enforced). All 3 TikTok variants in `ad-variants.json` land between 597–615
characters — plenty of headroom; the constraint that actually matters on this
surface is the ~8-word on-screen burned-in text limit, not the caption field.

**Creative theme**: lower-case, conversational captions (see the `ad-variants.json`
TikTok bodies — they're intentionally lower-case, first person, a little wry,
never the corporate-register full sentences long-form uses). The video itself
should feel shot-not-produced: phone-camera framing, a real person or a real
screen-recording, not a polished motion-graphics package. TikTok's audience
penalizes anything that reads as a traditional ad more than any other surface here.

**Hook formula (first 3 seconds)**: the same mechanism as YouTube Shorts, but
rewritten in TikTok's register — lower-case, a direct address, often ending in a
soft self-implicating tag: *"would you send $1,500 to quotes@gmail.com? be
honest"* / *"'hey how much do you charge?' — 11pm, in the DMs, again"* / *"if IG
disappeared tomorrow, how would you reach ONE past customer?"* The tag ("be
honest", an emoji reaction) is a TikTok-specific register choice — it should not
be carried onto YouTube or X, where it reads as filler.

**Design DNA on this surface**: the lime accent appears only on the price text
overlay near the end (`$199 first year (~55¢/day)`), never as a persistent color
wash across the whole video. No competing on-screen glow effects beyond the single
price callout.

**What NOT to do**: no polished stock-agency visual package (this surface punishes
that harder than any other); no invented statistic; no hashtag stuffing beyond the
4–5 tags already used per variant; do not place burned-in text in the reserved
top/right/bottom UI zones.

---

## 4. X

**Video specs**: any aspect ratio is accepted, but 9:16 or 1:1 perform best in the
in-feed player; practical length ceiling for organic posting is roughly 140
seconds. Autoplay is muted by default — the first frame and any burned-in text
must carry the hook without audio.
**Caption/text limits**: 280 characters (`CONTENT_LIMITS["x"] = 280`,
code-enforced) for the account tier this pipeline posts through. All 3 X variants
in `ad-variants.json` land at 232–266 characters, including the compact tracked
link (`utm_campaign` + `utm_content` only — the full multi-parameter link does not
fit).

**Creative theme**: the tightest, plainest register of any surface — a single
sharp question, one line of mechanism, the price, and a link. No emoji tag, no
hashtags (X's own engagement data has long penalized in-post hashtags; none are
used in any X variant here). This is closer to a headline than a caption.

**Hook formula (first line)**: state the exact same tension line as the video
surfaces, as text, with no lead-in: *"Would you send $1,500 to
quotes@gmail.com?"* / *"'How much do you charge?' — in the DMs. Again. At 11pm."*
/ *"If Instagram vanished tomorrow, how would you reach ONE past customer?"*
Every word before the mechanism costs character budget X does not have to spare.

**Design DNA on this surface**: not applicable to color/glow (X is text-and-link
first in this pipeline) — if a video is attached, the same single-glow-on-CTA rule
applies to its end-card.

**What NOT to do**: don't try to fit the full four-parameter tracked link (append
only `utm_campaign` + `utm_content`, per `tracked_link(..., compact=True)` in
`scripts/queue-campaign-drops.py`); don't add hashtags; don't write past the limit
and rely on truncation — over-limit copy is silently excluded from the channel at
publish time by this pipeline's own validation, not gracefully shortened.

---

## 5. Facebook

**Dimensions**: Facebook's own feed accepts 1:1, 4:5, or 9:16. **In practice, on
this pipeline, every video post becomes a 9:16 Reel regardless of the aspect ratio
uploaded** — see §6 below. Plan video creative as 9:16, 1080×1920, to match what
actually gets published.
**Duration**: no house-enforced cap; Facebook Reels historically favor under ~90
seconds, but the provider code path here has no duration check of its own (see §6).
**Caption/text limits**: the Facebook provider's own code declares a hard cap of
63,206 characters (`FacebookProvider.maxLength()` returns `63206`,
`tools/postiz-app-src/libraries/nestjs-libraries/src/integrations/social/facebook.provider.ts:41-43`)
— effectively no constraint for this content. The **practical** limit is Facebook's
UI truncation: only the first ~477 characters show before "See More." All 3
Facebook variants in `ad-variants.json` run 981–1,037 characters total — write the
hook and the mechanism paragraph to land inside that first ~477-character window;
the price, the offer detail, and the link can trail after the fold.

**Creative theme**: the most "explain it like a person, not an ad" register of any
surface here — full sentences, a concession before the mechanism ("A gmail address
got the business started, and that's fine" — implicit in tone even where not
spelled out), then the fix, then the price with the renewal disclosed
($199 first year, then $9.99/month — never buried). This is the surface where the
full pillar-post link belongs alongside the tracked onboarding link, since Facebook
readers tolerate — and this audience specifically benefits from — the longest
read of any surface here.

**Hook formula (first line, before the fold)**: identical mechanism-first
questions as the other surfaces, written in full sentences: *"Would you send a
$1,500 deposit to quotes@gmail.com?"* / *"How many times did someone DM you 'how
much do you charge?' this week? Now count how many of them actually booked."* /
*"If Instagram disappeared tomorrow, how would you reach a single past
customer?"*

**Design DNA on this surface**: if the post carries a video (see §6), the single
glow rule applies to that video's end-card exactly as on YouTube/TikTok. If the
post is text-plus-link only, there is no glow to apply — the DNA constraint here is
that any attached still image still follows the `#070907` ground / `#7cfc00`
single-accent rule.

**What NOT to do**: don't rely on Facebook.com links inside the post body — the
provider's own error-handling table rejects them (`Cannot post Facebook.com
links`, code `1609008`); don't attach more than one video per post (the provider
publishes only `firstPost.media[0]`, so a second video attached to the same post
entry is silently ignored, not queued as a second post); don't assume "feed video"
stays a feed video — see §6, it becomes a Reel.

---

## 6. Instagram

**Dimensions**: feed 1:1 (1080×1080) or 4:5 (1080×1350); Reels/Stories 9:16
(1080×1920).
**Duration**: Reels favor under ~90 seconds for this ad format; Stories are
single-frame or short-clip.
**Safe area**: same UI-overlay pattern as TikTok — reserve the top ~150px
(profile/following UI) and right-side ~150–200px column (like/comment/share/save
icons), and the bottom ~250px (caption, audio attribution, "Reels" chrome). This
pipeline posts through the `instagram-standalone` integration.
**Caption/text limits**: 2,200 characters (`CONTENT_LIMITS["instagram-standalone"]
= 2200`, code-enforced). All 3 Instagram variants in `ad-variants.json` land at
534–599 characters.

**Creative theme**: a register between TikTok's lower-case conversational voice and
Facebook's full-sentence explainer — mixed case, still direct, an emoji reaction
allowed but sparingly (one per post, matching the pattern already in the shipped
`cost-is-not-the-reason` copy). Visual content can reuse the same still/video
assets produced for Facebook feed, since both post through the Meta family, but
crop for Instagram's own safe area rather than assuming Facebook's crop survives.

**Hook formula (first line)**: same mechanism, mixed-case register: *"Would you
send $1,500 to quotes@gmail.com? 👀"* / *"'hey how much do you charge?' — 11pm, in
the DMs, again 😅"* / *"if IG disappeared tomorrow, how would you reach ONE past
customer? 😬"*

**Design DNA on this surface**: identical single-glow-on-CTA rule for any attached
video; the lime accent on a still image should mark the price callout only, never
used as a background wash.

**What NOT to do**: don't post the same exact crop used for Facebook without
checking Instagram's own safe-area overlay (Instagram's UI chrome differs from
Facebook's); don't stack more than one emoji per hook line; don't drop the "link
in bio" instruction — Instagram captions are not click-through, unlike X or
Facebook.

---

## 7. Facebook video capability check (read-only — Deliverable 3)

**Question**: can the local Postiz stack technically attach a VIDEO to a Facebook
post, and if so, through what code path, with what format/size/duration limits,
and what would need to change to make it reliable in production?

**Answer: yes — and it has already happened successfully in production, once,
under real conditions.**

### 7.1 Live evidence (read-only; nothing was posted for this check)

`marketing/campaigns/cost-is-not-the-reason/scorecard.json` — generated by a
**read-only** query against the Postiz Postgres container (`docker exec
postiz-postgres psql ...`, per the scorecard's own `provider_query_note`, no
mutation performed) — shows **drop-01** of the `cost-is-not-the-reason` campaign
(`postiz_post_id: cmtlhfb8w000jrtdm9a027bkn`, `integration_identifier: facebook`,
`integration_name: "FAMTastic Designs"`) in state **`PUBLISHED`**, `has_error:
false`, published `2026-09-03T13:00:00Z`. Its `posting-schedule.json` entry
declares `media_type: video_9x16` with `primary_media:
videos/00-hyperframes-branded-recut-commercial-9x16.mp4`. Inspecting that exact
file directly (`ffprobe`, read-only): H.264 video / AAC audio, **1080×1920, 31.3
seconds, ~15.2 MB**. This is a real, already-shipped Facebook video post, not a
theoretical capability.

### 7.2 The code path (traced, not guessed)

1. **Upload**: `scripts/queue-campaign-drops.py`'s `upload()` posts the local
   `.mp4` file to Postiz's own `/upload` endpoint
   (`apps/backend/.../public.integrations.controller.ts:80-98`). That endpoint
   accepts `video/mp4` as one of eight allowed MIME types
   (`PUBLIC_API_ALLOWED_MIME`, same file, line 44-53) and enforces a **1 GB** size
   ceiling for any `video/*` MIME type (`getMaxSize()`,
   `libraries/.../custom.upload.validation.ts:60-68`; images are capped at 10 MB
   by the same function).
2. **Storage → public URL**: with `STORAGE_PROVIDER=local` (confirmed live on the
   running container via `docker exec postiz printenv`), `LocalStorage.uploadFile()`
   (`libraries/.../local.storage.ts:76-114`) writes the file to `/uploads/YYYY/MM/DD/<random>.mp4`
   inside the container and returns
   `process.env.FRONTEND_URL + '/uploads' + publicPath` — confirmed live as
   `https://designate-vacation-shadiness.ngrok-free.dev/uploads/...`. **This is
   the load-bearing fact**: the Facebook Graph API needs a publicly fetchable
   HTTPS URL for `file_url`, and the ngrok tunnel (`scripts/restart-postiz-tunnel.sh`)
   is what turns a file on the operator's laptop into something Meta's servers can
   download. No tunnel up ⇒ no video post can succeed, regardless of everything
   else being correct.
3. **Post creation**: `queue-campaign-drops.py` builds each post entry as
   `{"integration": {...}, "value": [{"content": body, "image": uploaded}], ...}`.
   The key is literally named `image` in Postiz's own DTO regardless of media
   type — confirmed by reading `public.integrations.controller.ts:200-216`, which
   iterates `a.image.some(...)` over the same array for a domain-restriction check
   that applies equally to images and videos. This is a naming artifact in
   Postiz's schema, not a restriction against video.
4. **Facebook provider dispatch**: `FacebookProvider.post()`
   (`libraries/.../facebook.provider.ts:460-593`) checks
   `hasExtension(firstPost.media[0].path, 'mp4')` (a case-insensitive substring
   check on the URL, `helpers/utils/has.extension.ts`). If true, it `POST`s to
   `https://graph.facebook.com/v20.0/{page-id}/videos` with `file_url` = that
   public URL, `description` = the post's text, `published: true` — and returns
   `finalUrl = 'https://www.facebook.com/reel/' + videoId`.

### 7.3 What this means concretely

- **A video post through this pipeline always becomes a Facebook Reel**, never a
  traditional feed video, regardless of the aspect ratio or duration uploaded —
  because the code always routes any `.mp4`-suffixed first media item to the
  `/videos` endpoint, and Meta's own API classifies that as Reels content. Plan
  creative as 9:16 accordingly (§5 above), even for a square or landscape source
  cut.
- **Only the first media item is used for video.** `firstPost.media[0]` — a
  second video (or any additional media) attached to the same post entry is
  silently not sent; there is no video-carousel path in this provider.
- **Format**: `.mp4` only is detected by the dispatch check; Postiz's own upload
  validators (`ALLOWED_MIME_TYPES` / `PUBLIC_API_ALLOWED_MIME` /
  `LOCAL_STORAGE_ALLOWED_MIME`, all three lists identical) accept `video/mp4` and
  no other video container — do not hand this pipeline a `.mov` or `.webm` source
  file and expect it to pass through unconverted.
- **Size**: 1 GB ceiling, enforced at upload (`getMaxSize`), independent of
  whatever Facebook's own Graph API limit is for `/videos`.
- **Duration**: no duration check exists anywhere in this code path. Facebook's
  own Reels-classification limits (not this codebase's business) are the only
  constraint on how long a video may run and still succeed.
- **Reliability caveat, already documented and already proven true once**:
  `docs/marketing/POSTIZ_SERVER_MIGRATION.md` records that Postiz's publishing
  worker was OOM-killed continuously from 2026-08-25 inside a resource-starved
  colima VM, silently destroying nine days of scheduled posts while every layer
  above the worker reported success. The Facebook video path above is real and
  has worked, but it depends on: the operator laptop being awake, colima/Docker
  healthy, the ngrok tunnel up, and enough memory for the container not to be
  killed mid-publish. None of that is specific to video — it is the same
  standing risk `POSTIZ_SERVER_MIGRATION.md` already proposes fixing by moving
  Postiz to a small always-on VPS with Caddy-terminated TLS in place of the ngrok
  tunnel.

### 7.4 What would need to change for this to be a reliable, standing capability

1. **Nothing in the code.** The video-attach path to Facebook already works, is
   already proven live, and needs no new development.
2. **Availability, not capability, is the open gap** — resolve
   `docs/marketing/POSTIZ_SERVER_MIGRATION.md` (move Postiz off the operator
   workstation to the always-on VPS + Caddy stack already scaffolded there) so
   the public URL a Facebook video post depends on doesn't go down whenever the
   laptop sleeps or the ngrok process dies.
3. If a genuine **feed video** (not a Reel) is ever required instead, that is a
   new code path — `FacebookProvider.post()` would need an explicit branch for
   the Graph API's non-Reels video-feed behavior, which does not exist today.
4. If a **multi-video carousel** is ever required, that is also new code —
   today's provider only ever reads `media[0]`.

**No posting was performed for this check.** Every fact above came from reading
already-shipped source (`tools/postiz-app-src`), a read-only container
`printenv`/`ps` inspection, a read-only Postgres scorecard query already run for
an unrelated purpose, and a read-only `ffprobe` on an existing file.

---

## 8. Source files this document is derived from

- `BRAND.md`, `VOICE.md` — audience, claims policy, voice fingerprint, signature
  moves.
- `docs/architecture/SERIES_FIRST_CONTENT_ORIGIN_V1.md` — series-first doctrine.
- `marketing/blog/clusters/cluster-own-website-vs-rented-platforms/cluster-plan.json`
  — the 7-post series plan; only the pillar is published.
- `marketing/campaigns/cost-is-not-the-reason/articles/why-running-business-on-gmail-and-linktree-costs-revenue.md`
  — the published pillar post; source of the three angles used in
  `ad-variants.json` and the hook formulas above.
- `marketing/campaigns/cost-is-not-the-reason/manifest.json`,
  `posting-schedule.json`, `scorecard.json` — real drop structure, real
  channel/media pairing, real publish-state evidence (§7.1).
- `marketing/campaigns/ghost-town-ep1/` — confirmed its linked blog post is not
  yet live (404, curl-verified 2026-09-04), used here as the cautionary
  comparison in §"Scope" above.
- `scripts/queue-campaign-drops.py` — `CHANNEL_TO_INTEGRATION`, `CONTENT_LIMITS`,
  `DEFAULT_LANDING`, `COPY_PREFERENCE`, `tracked_link()`, `upload()`, `copy_for()`
  — the exact runtime constraints every spec and limit above is checked against.
- `docs/architecture/FAMTASTIC_CLIENT_PORTAL_DESIGN_DNA_V1.md` — Design DNA tokens
  (§0).
- `tools/postiz-app-src/libraries/nestjs-libraries/src/integrations/social/facebook.provider.ts`,
  `tools/postiz-app-src/apps/backend/src/public-api/routes/v1/public.integrations.controller.ts`,
  `tools/postiz-app-src/libraries/nestjs-libraries/src/upload/{upload.factory,local.storage,custom.upload.validation}.ts`
  — Facebook video capability trace (§7).
- `docs/marketing/POSTIZ_SERVER_MIGRATION.md`,
  `scripts/restart-postiz-tunnel.sh` — public-URL/tunnel dependency and the
  known workstation-reliability gap (§7.3–7.4).
