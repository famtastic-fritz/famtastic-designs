# Services — HyperFrames films for the FAMtastic add-on products

Two 9:16 films, each its own HyperFrames project, both authored to the same
grading contract as the accepted `platform-dependency` film and both about the
same uncomfortable subject: **what the $199 bundle is not.**

| Project | Film | Product(s) | Duration | Sound |
|---|---|---|---|---|
| `business-email/` | Two Different Jobs | `FAM-BUSINESS-EMAIL` $99 one time | 31.5 s | approved HeyGen narration |
| `local-seo/` | Found, Then Kept | `FAM-LOCAL-SEO` $299 · `FAM-MAINTENANCE` $49.99/mo | 28.0 s | silent |

**They rendered.** Both verified with `ffprobe`, with a per-second grading
measurement, and by extracting frames and looking at them:

| File | Dimensions | Duration | Frames | Video | Audio | Size | Render time |
|---|---|---|---|---|---|---|---|
| `business-email/renders/two-different-jobs-1080x1920.mp4` | 1080 × 1920 | 31.500 s | 945 @ 30 fps | h264 High, yuv420p | aac LC, 48 kHz stereo | 17.7 MB | **52.4 s** |
| `local-seo/renders/found-then-kept-1080x1920.mp4` | 1080 × 1920 | 28.000 s | 840 @ 30 fps | h264 High, yuv420p | none | 14.8 MB | **25.8 s** |

Renders and staged media are gitignored; both are reproducible from the repo.

## Cost

**$0 for both films.** No provider call of any kind — no image, no voice, no
video, no model. Every input already existed on disk: the HeyGen take-b render,
two platform-dependency plates and the campaign anchor. All processing is local
(ffmpeg for the key and the grade, Chrome + ffmpeg for the render). HyperFrames
is free to run locally; its optional hosted `cloud render` path costs money and
was not used.

## Re-render

Identical for either project — `cd` into it first:

```bash
cd marketing/hyperframes/services/business-email    # or services/local-seo

./scripts/stage-assets.sh                            # ~5 s / ~2 s, $0
npx hyperframes@0.8.29 check                         # lint + runtime + layout + motion + contrast
npx hyperframes@0.8.29 render --quality high \
  --output renders/two-different-jobs-1080x1920.mp4  # or found-then-kept-1080x1920.mp4
node scripts/verify-render.mjs renders/<file>.mp4 verify
```

Review frames without a full render:

```bash
npx hyperframes@0.8.29 snapshot --at 3.0,6.2,8.5,13.0,21.0,25.5,28.5   # business-email
npx hyperframes@0.8.29 snapshot --at 4.5,11.0,17.0,22.5,26.5           # local-seo
npx hyperframes@0.8.29 preview --background
npx hyperframes@0.8.29 preview --stop
```

Machine: Apple M5, hardware GPU (`ANGLE Metal Renderer: Apple M5`), 4 render
workers, `hyperframes@0.8.29` (checked against `npx hyperframes@latest
--version` — 0.8.29 is current, no pin bump needed), Node v24.19.0, ffmpeg 9.0.

## Did the grade land?

Both films have to cut against
`marketing/creative/heygen/renders/take-a-platform-dependency.mp4`, so both are
graded to that take's **measured** appearance
(`marketing/creative/heygen/reference-tokens.json`), not to the brand spec.

Same command across five files — `ffmpeg -vf "signalstats,metadata=print:key=lavfi.signalstats.YAVG"`,
mean over every frame:

| File | mean YAVG | Δ vs anchor |
|---|---|---|
| HeyGen anchor take (the thing being matched) | **155.4** | — |
| Accepted `platform-dependency` film | 160.1 | +4.7 |
| **Two Different Jobs** | **159.8** | **+4.4** |
| **Found, Then Kept** | **163.1** | **+7.7** |
| Existing Remotion 9:16 for the same campaign | 212.1 | +56.7 |

Per-second, via `scripts/verify-render.mjs`:

| Film | seconds outside 150–175 | mean olive accent | accent peak |
|---|---|---|---|
| Two Different Jobs | **0 / 32** | 0.58 % | 1.54 % (the presenter's jacket) |
| Found, Then Kept | **0 / 28** | 0.30 % | 0.57 % |

Both are inside the 1–2 % accent budget and neither ever has a green field,
gradient or wash.

### Two grading corrections, both caught by measurement rather than by eye

**Two Different Jobs dipped below the floor for four seconds.** Seconds 6–9
measured 148.4–149.6 — the window where the inverted `IT DOES NOT.` block is on
screen alongside the presenter's dark jacket. The fix was not to shrink the
block (it is the point of the film) but to lift the paper ground, which in this
project cannot be done in CSS alone: `--paper` must equal the measured
background of the graded presenter composite or her cut-out stops being
invisible. The base colour fed to ffmpeg went `#CDC6BB → #D5CEC3`, the graded
result was re-measured at `#D2C9BE`, and the token was set to that. 0 / 32 now.

**Found, Then Kept measured 165.6, the brightest of the three films.** Rather
than deepen the ground — which would have cost the olive accent its separation
— the vignette was deepened from 0.10/0.30 to 0.14/0.40, which pulls the frame
mean down while leaving the centre, where the type and the accent live,
untouched. 163.1 now.

## Film 1 — Two Different Jobs (`business-email/`)

### Why it exists

`marketing/campaigns/cost-is-not-the-reason/posting-schedule.json` carries live
scheduled drops stating the $199 bundle includes "a branded business email
address" (drop-01 `facebook_instagram`; "hosting, domain, email" in drop-01
`x_post` and `tiktok_reels_shorts`, drop-03 and drop-04).
`backend/config/famtastic-products.json` says otherwise: `FAM-BUSINESS-EMAIL`
is its own SKU at $99. The claim is under owner review. **This film is the
correction**, and the correction is the whole piece rather than a footnote on
it.

### The narration was already approved and already says it

`marketing/creative/heygen/renders/take-b-business-email-scope.mp4` is an
existing HeyGen take, recorded before this project, whose script says:

> "Does the one hundred ninety-nine dollar website come with business email? It
> does not. We would rather say that plainly than let you find out later."

and goes on to disclose the $9.99/month hosting renewal and the separate domain
renewal, and to close on "Two different jobs. Ask us which one you need first."
Nothing had to be written; the film had to be cut to it.

Word timings came from `hyperframes transcribe` (87 words, whisper). **Every
scene boundary sits in a measured gap between sentences** — 1.66–1.92,
9.60–9.79, 18.12–18.31, 23.20–23.39, 26.71–26.99 s — so no cut lands
mid-sentence, and the `IT DOES NOT.` block wipes open at 5.50 so the words land
into a block that is already there when the voice reaches them at 5.69.

### Palette — `anchor-take-a`, argued

`prompt-library.json` files the business-email topic (`fbe`) under `paper`.
That is defensible for a typographic film; **this one is not typographic** — it
puts the campaign's presenter on screen for its first nine seconds, and
`anchor-take-a` is the palette that exists for exactly that case. Its own note
in `marketing/creative/templates/famtastic-social-frame.jsx`: *"Use this
palette when a still must sit next to THIS take."* The palette is chosen from
what is physically in the frame, not from the topic label.

### The presenter was cut out here, not delivered cut out

take-b was rendered with `remove_background: true`. **That did not produce an
alpha channel** — it produced a figure on a perfectly uniform `#F4F5FA` field
(verified: all four corners and mid-frame sample identically). So the key is
done once, locally, in `scripts/stage-assets.sh`, and she is laid onto this
film's own paper ground rather than onto transparency.

`colorkey` similarity is `0.06`, and that number was not chosen conservatively
for its own sake: at `0.12` a patch of her forehead highlight keys out, and at
`0.20` it becomes a hole the size of an eyebrow. Both were found by rendering
three candidates and looking at them side by side. No error, no warning, no
gate would have caught it.

### Where the presenter's accent actually landed

`reference-tokens.json` describes the anchor's accent as *"trim on the
presenter's jacket, not a field of colour"* at 1.31 % of frame. That is exactly
what carries beat 1 here. But take-b's trim is **not** the same value as
take-a's: measured after grading it averages `#90AD43` against the target
`#7FB449`. The cause is the background removal itself — take-b has none of
take-a's ambient office light, so its trim reads brighter and more chartreuse
at source. Pushing it the rest of the way to olive means desaturating her skin
and hair with it, which looked worse. `#90AD43` is where the two halves of the
brief stop fighting; the value is recorded rather than rounded off.

## Film 2 — Found, Then Kept (`local-seo/`)

### Why both products share one film

Local SEO Setup and Website Maintenance are the two halves of the same job —
being found, and staying found — and they are the two things a $199 customer is
most likely to assume are already included. One film that names both, with the
separateness on screen, is safer than two films that each leave the other
unmentioned. The close states it: *"Neither one is included in the $199
bundle."*

### Palette — `ghost-town`, turned to its daylight half

`prompt-library.json` files the local-SEO topic (`flseo`) under `trades` —
blue-black and safety orange, a dusk street with one lit shopfront. Good
argument, unusable ground: `trades` is `#0D1117`.

The palette that fits the subject is `ghost-town`, argued in the art direction
as *"a business that exists but cannot be found — heat, absence, weathering"*.
That is this film's claim word for word. `ghost-town` as shipped is also dark
(`#17120D` earth, `#D9A441` amber), so it is used **at its other end**: its own
prompt clause describes *"everything else bleached, dry and desaturated: bone,
sand, faded paint, grey weathered timber"*, and a sun-bleached wall at the top
of the day is as faithful to that argument as a dark one — and it is a light
frame. The ground is bone (`#CCC2AC`), the ink is ghost-town's earth lifted off
black, **the amber stays in the photograph** as the low sun on the timber, and
the graphic accent is the contract's measured olive.

The plate is `pd-a2-vertical`: a wrought-iron sign bracket with a hook, a chain
and no sign, with the faded rectangle where the sign used to hang still on the
wall. `prompt-library.json` files that plate under `ghost-town` for this exact
claim, which is the strongest available evidence that the palette argument is
the plate's own and not retro-fitted.

### No ranking promise, said out loud

`BRAND.md` forbids promising rankings. This film does more than avoid one — it
says so on screen: *"Nobody can promise you a ranking. This is the part that
can be done."*, over four things a machine can actually read.

**The campaign's own H1 was not used.**
`marketing/campaigns/fam-local-seo/landing-copy.md` reads *"Be the business
local search finds first."* "Finds first" reads as a ranking claim, which
`BRAND.md`'s Never-do list and taboo-phrase list both cover. Flagged here for
the copy owner; it is live landing copy, not just a draft.

## Where the doctrines collide

This is the most consequential thing this batch turned up, and it is an owner
decision, not a design one.

`docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` ships six palettes; four of them
(`famtastic`, `ghost-town`, `salon`, `trades`) have near-black grounds.
`marketing/creative/heygen/reference-tokens.json` requires anything cutting
against the campaign anchor to sit at 150–175 mean luminance. **Only `paper`
and `anchor-take-a` satisfy both.** `prompt-library.json` assigns a dark
palette to most of its topics, so this is not a corner case.

The same constraint disqualifies most of the plate library: of every plate on
disk, only `pd-a1`, `pd-a2`, `pd-b2`, `pd-p`, the anchor and one OpenArt image
measure above 127; everything else sits between 14 and 61. The full table is in
`../cost-is-not-the-reason/README.md`.

## Accuracy

Every claim traces to `backend/config/famtastic-products.json`, to the approved
take-b script, or to the campaigns' own landing and email copy. URLs were
curled before being set in type: `https://famtasticdesigns.com`,
`https://www.famtasticdesigns.com` and `https://famtasticdesigns.com/packages`
all HTTP 200.

- `$199` / one landing-page website / one year of managed hosting / domain
  registered new or connected — `FAM-FOOT-199`.
- `$9.99 a month` — `FAM-HOST-999`, the SKU's `renewal_sku`.
- `Your domain renews separately` — `billing.domain_renewal_separate: true`.
- `$99, one time` — `FAM-BUSINESS-EMAIL`.
- `$299, one time` — `FAM-LOCAL-SEO`, `billing.kind: one_time`.
- `$49.99 a month` — `FAM-MAINTENANCE`, `billing.kind: recurring`, monthly.
- "Neither one is included in the $199 bundle" — both are `upsells` of
  `FAM-FOOT-199`, not `entitlements` of it.

No statistic, no percentage, no named competitor, no delivery promise, no
ranking promise. No `/web/` path appears anywhere — note that
`marketing/campaigns/fam-business-email/email-draft.md`,
`fam-local-seo/email-draft.md` and `fam-maintenance/email-draft.md` all contain
`https://famtasticdesigns.com/packages?...`, which `BRAND.md` says 404s
publicly. Those drafts need fixing; nothing from them was copied here.

## Limitations hit

Everything below is a real thing that happened.

1. **Found, Then Kept is completely silent.** There is no approved narration in
   the repo for either product and no licensed music, and this task authorised
   no provider spend. The pacing was rebuilt around it — row stagger is ~0.6 s
   instead of the 0.16 s a narrated film uses — but for a social cut, 28 seconds
   of silence is a real weakness. The fix is a short approved VO (the take-b
   pattern works: one script, one HeyGen take, cut to its measured word timings)
   or a bed sourced through `/media-use`.
2. **The ground plane cut the film's own subject in half.** In the first cut of
   `local-seo`, beat 1's ground sat at y 1180 and the sign bracket — the entire
   argument — was two-thirds hidden behind it. `check` passed; it has no opinion
   about what a photograph contains. Found by looking at a snapshot. Fixed by
   lifting the plate to the top of its 1.10× headroom (`top: -208px` instead of
   `-104px`) and dropping the ground to y 1420, which cost beat 1 its accent
   rule — it now carries a 16 px accent square beside the eyebrow instead.
3. **A second plate was staged and cut for softness.** `pd-a2-hero-16x9` (an
   unmarked door in a sun-bleached alley) was going to carry beats 3–5. A 9:16
   full-bleed crop of a 1376 × 768 source is a 432 × 768 slice enlarged 2.75×;
   it survived a 1:1 detail inspection but read mushy at frame size in the
   render. **The 9:16 plates are 768 × 1376, which supports exactly one
   full-bleed framing at 1.55× and no tighter reframe.** The film holds one
   photograph instead, which is a better piece anyway.
4. **A contrast failure of 0.04.** The gate reported `#b5-ft` at 2.96:1 against
   WCAG AA's 3:1 — a footnote sitting over the vignetted lower ground, where the
   effective background is 14 points darker than `--paper`. `--muted` went
   `#6B6167 → #625860`. The eye would not have caught this; the gate did.
5. **A scene-owned vignette would have sat under the presenter.** She is
   host-owned at `z-index: 20` so her timing reads against the film clock, which
   puts her above every scene slot. A vignette inside beat 1 rendered under her
   and she read as pasted on rather than lit by the same light. The vignette is
   host-owned at `z-index: 40` for the whole film instead.
6. **The verify script's first version gated on the wrong luminance scale** and
   failed 16 of 28 seconds on a good film. Full write-up in
   `../cost-is-not-the-reason/README.md`.
7. **Aspect ratio is baked into the composition.** `--resolution` only scales by
   an integer factor within the same aspect; 1:1 or 16:9 cuts mean authoring
   every scene again. Known HyperFrames gap versus the Remotion system in
   `marketing/video/`, recorded in the `platform-dependency` README and
   unchanged.
8. **Telemetry feedback was deliberately not sent.** The CLI asks for a
   `hyperframes feedback` report after a verified render; that transmits to a
   public channel, which this task did not authorise.

## What is in here

```
README.md                          this file — covers both projects
business-email/
  BRIEF.md STORYBOARD.md frame.md  intent, beats, design truth
  index.html                       host: 5 slots + keyed presenter + narration
  compositions/0{1..5}-*.html      one beat each, self-contained
  scripts/stage-assets.sh          key + grade the take, crop the plates ($0)
  scripts/verify-render.mjs        measures a render against the contract
  verify/sheet.jpg                 12 frames from the delivered MP4
  snapshots/contact-sheet.jpg      scene-mount smoke test
local-seo/
  (same shape; no audio, one plate)
```

Nothing under `marketing/video/`, `marketing/creative/templates/`,
`marketing/creative/plates/`, `marketing/hyperframes/platform-dependency/`,
`marketing/hyperframes/ghost-town/` or
`marketing/hyperframes/campus-entrepreneurs/` was modified.
