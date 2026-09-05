# I've Managed Fine — six objections, six films

A video-heavy campaign for the $199 Web Basics package (`FAM-FOOT-199`), aimed at
an audience the other campaigns do not reach: an established business owner,
usually older, who has run a good business for years without a website and
genuinely does not believe they need one.

They are not lazy and they are not stupid. They are pattern-matching on decades
of evidence that word of mouth worked — because it did. Every asset here starts
by conceding that out loud.

**Six films. One objection each. Every one under 25 seconds, because the owner
sends them one-to-one to individual prospects as well as posting them.**

---

## The films

Delivered at `marketing/campaigns/ive-managed-fine/videos/`. Source projects at
`marketing/hyperframes/ive-managed-fine/f<N>-<slug>/`.

| # | Objection | The answer | File | Length |
|---|---|---|---|---|
| 01 | "I've managed fine for thirty years." | True — and the customers who found you did it differently each decade | `videos/01-thirty-years-9x16.mp4` | 21.2s |
| 02 | "My customers know where I am." | The ones you have, yes. The question is the ones who moved in last year | `videos/02-know-where-9x16.mp4` | 21.1s |
| 03 | "I'm not technical." | You will not touch it. Neither does anyone else who has one | `videos/03-not-technical-9x16.mp4` | 22.2s |
| 04 | "I tried before and got burned." | Paid up front, no proof, then silence. We run it the other way around | `videos/04-got-burned-9x16.mp4` | 19.8s |
| 05 | "It's too expensive." | 55 cents a day, and every line of the bill on one screen | `videos/05-too-expensive-9x16.mp4` | 23.8s |
| 06 | "I'm too close to retiring." | A buyer buys what transfers, and a domain transfers | `videos/06-retiring-9x16.mp4` | 21.5s |

Which film to send to which prospect: **`SEND-DIRECT.md`**.

---

## Measured luminance, per film

The grading contract is `marketing/creative/heygen/reference-tokens.json`: the
films must cut against the HeyGen anchor take, which measures **162.3** mean
luminance. Contract band: **150-175**.

Measured on the **delivered** file, every frame, with the command in the brief:

```bash
ffmpeg -v info -i OUT.mp4 -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG:file=-" -f null /dev/null
```

| Film | Frames | **Mean YAVG** | Min | Max | In band |
|---|---|---|---|---|---|
| 01 thirty-years | 635 | **152.19** | 141.0 | 177.0 | ✅ |
| 02 know-where | 633 | **155.90** | 143.4 | 168.5 | ✅ |
| 03 not-technical | 665 | **163.78** | 152.3 | 177.0 | ✅ |
| 04 got-burned | 594 | **159.70** | 138.2 | 168.5 | ✅ |
| 05 too-expensive | 715 | **163.37** | 160.1 | 177.0 | ✅ |
| 06 retiring | 645 | **161.20** | 151.7 | 168.5 | ✅ |

Campaign mean: **159.36**, against the anchor's 162.3 — a gap of 2.9.

Per-frame minima dip below 150 in the photograph-heavy beats (f4's cash-drawer
card bottoms at 138.2). That is by design: a photograph placed on the paper is
darker than the paper, and flattening it to the ground would destroy the
picture. The contract is the **film mean**, and all six are inside it.

### Olive accent (`#7FB449`)

Budget: 1-2 % of frame, "a single small accent incident … never a lime field."
Measured by `marketing/hyperframes/ive-managed-fine/scripts/verify-render.mjs`,
sampling one frame per second:

| Film | Mean | Peak |
|---|---|---|
| 01 thirty-years | 0.37 % | 1.49 % |
| 02 know-where | 0.33 % | 1.49 % |
| 03 not-technical | 0.30 % | 1.49 % |
| 04 got-burned | 0.38 % | 1.49 % |
| 05 too-expensive | 0.22 % | 1.33 % |
| 06 retiring | 0.41 % | 1.49 % |

The peak is the frame carrying the signature bar above the URL, and it lands in
the 1-2 % band. The mean is lower because the accent is an *incident* — a rule,
a marker, a bar — not a constant wash. Zero seconds in any film exceed 2 %.

### Delivered specs

All six: **1080 × 1920**, **30 fps**, h264 High / yuv420p, AAC 48 kHz stereo,
`+faststart`. Audio measures **mean −20.6 to −20.7 dB, peak −1.1 to −2.6 dB** —
in line with the HeyGen presenter these films cut against (−19.6 dB). **No film
is silent.**

---

## Spend

**$0.00.**

| Item | Cost | Why |
|---|---|---|
| Plates (7 photographs, 12 graded crops) | $0 | Already on disk. Generated for the platform-dependency drop before this campaign existed; this campaign copies, crops and grades them with local ffmpeg and generates nothing. |
| Narration (18 audio blocks, 6 beds) | $0 | Local Voicebox / kokoro `af_heart` on Apple Silicon, per `.agents/skills/famtastic-voice/SKILL.md`. No API key, no metered credits. |
| Grading, rendering, muxing | $0 | Local ffmpeg and Chrome. HyperFrames renders locally; its paid hosted `cloud render` path was not used. |
| Stills | $0 | Extracted from the delivered renders, not generated. |
| **Total** | **$0.00** | No provider call of any kind was made. |

---

## Every claim, and where it comes from

| Claim on screen / in copy | Source |
|---|---|
| `$199` | `backend/config/famtastic-products.json` → `FAM-FOOT-199`, `price: "199.00"`, `published: true` |
| "55 cents a day" | 199 ÷ 365 = $0.545 |
| "One page. One year of hosting. A domain that is yours." | `FAM-FOOT-199.summary`: "One focused landing-page website with one year of FAMtastic-managed hosting. Includes first-year new-domain registration when needed or connection of an existing customer-owned domain." |
| "First year $199. Then $9.99 a month, plus the domain." | `FAM-FOOT-199.billing.renewal_sku` = `FAM-HOST-999`; that SKU is `price: "9.99"`, `kind: recurring`, `interval: month`, `activation: after_included_period`. `domain_renewal_separate: true`. This is BRAND.md's **required** renewal disclosure and it is on screen in every film that names the price. |
| "Business email — a separate product." | `FAM-BUSINESS-EMAIL` is its own published SKU |
| "Maintenance — a separate product." | `FAM-MAINTENANCE` is its own published SKU |
| "Three real designs … and a person reviews them" | Live post `/blog/proof-first-website-see-before-you-pay/` ("Three real proofs get built… You pick a direction. Checkout follows your selection."), plus BRAND.md's required reviewer disclosure |
| "The phone book. Then a neighbor. Then a phone." | A description of how discovery changed, not a statistic. No number is attached to it. |
| "A domain transfers. A page transfers." | A statement about what is assignable, not a valuation claim. The film never says a business with a website sells for more — that would be an unsourceable statistic. |
| Every blog URL | Curled before use; all return HTTP 200 (see below) |

### Claims deliberately NOT made

- **No statistic and no percentage appears anywhere** in this campaign.
- **No delivery-time promise.** The "within 48 hours" claim live on five older
  posts has no SLA behind it in the catalog and is not repeated here.
- **No ranking promise.**
- **No competitor named**, and no competitor's fees described.
- **No urgency or scarcity.** No countdown, no "limited time".
- **The words "still", "finally" and "even you"** appear nowhere in the copy,
  narration, or on-screen type. They are the words that turn a fair objection
  into an accusation.

---

## The drops

Six drops across eight days, **2026-09-06 → 2026-09-13**, in
`posting-schedule.json` (schema v2, validates clean against
`marketing/engine/schemas/posting-schedule.schema.json`).

| Drop | When (ET) | Film | Live blog post it links to |
|---|---|---|---|
| drop-01 | Sat 06 Sep, 09:30 | Thirty Years | [What Should a Small-Business Website Actually Do?](https://famtasticdesigns.com/blog/what-should-a-small-business-website-do/) |
| drop-02 | Sun 07 Sep, 16:00 | Know Where | [How Local Customers Actually Find Your Business Online](https://famtasticdesigns.com/blog/how-local-customers-find-your-business-online/) |
| drop-03 | Tue 09 Sep, 08:30 | Not Technical | [What Happens After You Buy the $199 Website?](https://famtasticdesigns.com/blog/after-buying-199-website/) |
| drop-04 | Wed 10 Sep, 18:30 | Got Burned | [Proof-First Websites: See Three Real Designs Before You Pay](https://famtasticdesigns.com/blog/proof-first-website-see-before-you-pay/) |
| drop-05 | Thu 11 Sep, 12:00 | Too Expensive | [A Professional Website for About 55 Cents a Day](https://famtasticdesigns.com/blog/professional-website-55-cents-a-day/) |
| drop-06 | Sun 13 Sep, 10:00 | Retiring | [What Is a Domain Name and Why Does Your Business Need One?](https://famtasticdesigns.com/blog/what-is-a-domain-name/) |

All six blog posts **already exist and are published** — they came from the live
`node/blog_post` collection (98 posts, all `status: true`), not from anything
written for this campaign. Each was curled: every one returns **HTTP 200** on the
trailing-slash URL used above (the bare form 301s to it, so the trailing slash is
in the schedule to avoid a redirect hop). No `/web/` path appears anywhere.

**Channels: `facebook` and `instagram` only.** They map through
`CHANNEL_TO_INTEGRATION` in `scripts/queue-campaign-drops.py` to `facebook` and
`instagram-standalone`. YouTube and TikTok are excluded on purpose — YouTube's
OAuth token is expired and TikTok is not approved for public posting, and
queueing to a broken channel manufactures a failure rather than reporting one.

Assembled post length (copy + tracked link + tags) runs **865-1,074 characters**,
comfortably inside the 2,200-character `instagram-standalone` limit in
`CONTENT_LIMITS`.

Timing is walked around the clock — two weekend mornings, a weekday morning, a
weekday evening, a weekday midday, a Sunday morning — so the same objection is
never shown to the same daypart twice. This audience is behind a counter during
business hours.

### Not queued

**Nothing here has been sent to Postiz.** Every drop carries
`approval.publish: false` and `state: media_ready`. Queueing is a later,
separate step:

```bash
python3 scripts/queue-campaign-drops.py --campaign ive-managed-fine            # drafts
FAMTASTIC_MARKETING_PUBLISH=true \
python3 scripts/queue-campaign-drops.py --campaign ive-managed-fine --schedule # live
```

---

## How the films were made, and how to remake them

```bash
cd marketing/hyperframes/ive-managed-fine

./scripts/stage-assets.sh          # copy + crop + grade the existing plates ($0)
python3 scripts/build-narration.py # local TTS; writes narration/timing.json ($0)

for d in f1-thirty-years f2-know-where f3-not-technical \
         f4-got-burned f5-too-expensive f6-retiring; do
  (cd $d && npx hyperframes@0.8.29 check)                       # must pass
  (cd $d && npx hyperframes@0.8.29 render --quality high \
      --output renders/$d-silent.mp4)
done

./scripts/mux-narration.sh         # narration + stereo, video stream-copied
./scripts/export-stills.sh         # stills + campaign video copies

for d in f1-* f2-* f3-* f4-* f5-* f6-*; do node scripts/verify-render.mjs $d; done
```

Voicebox must be running for `build-narration.py` (see
`.agents/skills/famtastic-voice/SKILL.md`); the generated beds are on disk, so a
re-render does not need it.

`npx hyperframes@0.8.29 check` passes **0 errors, 0 warnings** on all six —
lint, runtime, layout, motion, and WCAG AA contrast (17-35 text checks per film,
all passing).

---

## Two false results caught during this build

Both are recorded because a check that lies is worse than no check.

1. **`verify-render.mjs` reported all six films SILENT.** It read
   `volumedetect` from stdout; ffmpeg writes it to stderr. The regex matched
   nothing, `meanDb` came back `NaN`, and six perfectly good soundtracks were
   labelled dead. Fixed by capturing stderr. The films were never silent.
2. **The first grade passed a script and failed the spec.** The internal
   RGB-luma check said every film was inside 150-175, while the
   `signalstats` YAVG command named in the brief put f1 at 149.93 and f2 at
   146.18 — below the floor. The two measure different things (full-range RGB
   luma vs limited-range YUV Y). The brief's command is the contract, so the
   dark plates were lifted from ~78 to ~118 YAVG and the vignette softened, and
   both films came back at 152.19 and 155.90.

Neither would have been caught by a green exit code. Every film in this campaign
was also looked at frame by frame.

---

## Known gaps

- **9:16 only.** HyperFrames bakes the aspect ratio into the composition;
  `--resolution` scales within an aspect but cannot re-flow to 1:1 or 16:9.
  A square or landscape cut means authoring the beats again, or moving the
  campaign to the Remotion system in `marketing/video/`.
- **No music bed.** The repo holds no licensed track and this task authorised no
  provider spend. The films carry narration over silence between blocks. A bed
  would fill the gaps and is roughly ten minutes of work once a track exists.
- **The narration is synthetic and reads as synthetic** on close listening.
  It is a house TTS voice, not the owner. Do not present it as a recording of
  a person.
- **Not queued and not published.** By design; see above.
- **Per-second luminance dips below 150** in the photograph-heavy beats. The
  film mean is inside the band in all six, which is the contract, but a
  per-second YAVG gate would flag f1 (9 of 21 seconds), f2 (3 of 21) and f4
  (3 of 20). Those are the seconds a photograph fills most of the frame. f3,
  f5 and f6 have none.
