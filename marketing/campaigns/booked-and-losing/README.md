# Booked and Losing

**Campaign**: `booked-and-losing` · **Offer**: `FAM-FOOT-199` · **Built**: 2026-09-05
**Lane**: C1 of `plans/four-campaign-flood/plan.md`
**State**: assets complete, schema-valid, **nothing queued**

Six drops across the eight days beginning 2026-09-06, Facebook and Instagram only.
Every drop links to a blog post that was already live before this campaign existed.

---

## The argument

The audience is independent beauty and grooming professionals who take bookings
through a marketplace app — barbers, nail techs, braiders, loctitians,
estheticians, lash artists. They are good at their craft and they are busy.

**The argument is not that the app is bad.** That version loses the reader three
paragraphs in, and it is not true: a channel that fills a calendar is doing its
job. The argument is that a booking app is a *lead source*, not an *address*.
An app is good at demand you did not create. A page of your own is good at
demand you did create. Most of this audience has both kinds of demand and only
serves one of them — so the customers they already earned keep arriving through
somebody else's front door, and they keep paying for the privilege.

**Dignity is the rule.** Nobody in this campaign is depicted as struggling,
overwhelmed or pitiable. They are missing revenue they earned. The missing money
is carried by objects — an empty chair in a busy shop, an unlettered storefront,
a face-down phone, a full appointment book beside an empty drawer — never by a
sad face.

---

## The palette argument

**`salon`** — ground `#1A1013`, accent `#E8B4B8`.

The palette is named "salon" in `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md`, and
picking it because of that name would be exactly the cookie-cutter failure that
document was written to stop. Here is the argument from the subject.

**The room decides the ground.** A barber's station, a nail table, a braiding
chair and a lash bed are all lit the same way in real life: one warm practical
source pointed at the work, and the rest of the room falling off dark. You point
light at the head, the hand, the eye, and the room goes black behind it. A deep
plum-black ground with a single warm accent is not a mood applied to these
rooms — it is a photograph of them. Every plate in this campaign was generated
with that as its only light source, and it is why the frames look like the same
evening rather than like a set.

**The skin decides the accent.** The work in this campaign *is* skin, hair and
nails, so the accent grades every arm, cheek and cuticle in frame. A dusty rose
sits inside the range of warm light falling on skin across the whole range this
campaign depicts: it warms deep brown skin without pushing it orange, and warms
pale skin without going clinical pink. That is the representation requirement
expressed as a colour decision rather than as a caption.

**What was rejected, and why:**

| Palette | Why not |
|---|---|
| `trades` | Safety orange on blue-black. Grades every arm and cuticle toward sodium and cold steel — wrong for work whose subject is skin. |
| `ghost-town` | Amber dust and weathering argues abandonment. These shops are **full**; the premise of the campaign is that the reader is booked. It would read as pity, which the brief forbids. |
| `paper` | The sober document register. Right for a proposal or LinkedIn, wrong for a room you can smell. |
| `famtastic` | Black and lime is the *site* identity. FAMtastic is not the subject here; the professional is. |

**Declared divergence from the anchor.** `marketing/creative/heygen/reference-tokens.json`
sets the house grading anchor at mean luminance 150-175 with olive `#7FB449` at
1-2% of frame. This campaign diverges deliberately: the ground is a dark room and
the accent is rose, because the rooms being photographed are genuinely dark and
the accent has to be skin-adjacent. `CAMPAIGN_ART_DIRECTION_V1` Rule 1 permits an
argued divergence and requires it to be stated in writing. This paragraph is that
statement, and the same note is recorded in `plate-library.json` and `manifest.json`.

---

## Composition — what varies, not just the colour

Rule 3 of the art direction: rotating the palette is the same poster in a
different shirt. Three archetypes are in rotation here, and within the
photographic ones the type column moves.

| Drop | Archetype | Type column |
|---|---|---|
| 01 | `standard` over a photographic plate | left (story, feed), right (square) |
| 02 | `standard` over a photographic plate | left (story, square), right (feed) |
| 03 | `split` — held-colour band, diagonal cut, CTA pill, no photography | full width |
| 04 | `standard` over a photographic plate | left |
| 05 | `standard` over a photographic plate | left (story, square), right (feed) |
| 06 | `checklist` — marker rows, hairlines, CTA pill, no photography | full width |

Each plate reserves its empty area on the side the type actually lands on
(`negative_space` is recorded per variant in `plate-library.json`), which is why
the headline never sits on a busy half.

---

## Representation

Weighted toward Black professionals, and multicultural without leaning on
signifiers. People are described physically — skin tone, hair, age, dress,
posture — never by an ethnic or national label the model would caricature.

- **Drop 01** — a Black barber in his late thirties, deep brown skin, close fade,
  in his own shop; a second barber with pale freckled skin and an auburn beard
  working the next chair. Two competent professionals in one honest frame.
- **Drop 02** — a braider in her forties, deep brown skin, long locs, working a
  client in her own studio, seen through her own blank storefront glass.
- **Drop 04** — a nail technician in her thirties, warm medium-brown skin, dark
  hair in a low bun, gloved and precise under her lamp.
- **Drop 05** — a lash and brow studio owner in her late twenties, deep brown
  skin, natural coils, standing at ease behind her own counter.

**What was requested and not done, honestly:** Haitian representation is not
encoded visually anywhere in this campaign. Nationality cannot be depicted
without reaching for exactly the ethnic shorthand the brief forbids — a flag, a
food, a costume — so it was not attempted. What was done instead: the Black
professionals depicted differ from each other in age, features and hair texture
(a fade, long locs, natural coils), and no frame relies on a prop to say who
anyone is.

---

## Measured spend

**$0.504 total.** Ceiling was $1.50.

| Item | Provider | Count | Rate | Cost |
|---|---|---|---|---|
| Plates (kept) | Gemini 3.1 Flash Lite Image | 12 | $0.0336 | $0.4032 |
| Plates (retaken and discarded) | Gemini 3.1 Flash Lite Image | 3 | $0.0336 | $0.1008 |
| Stills, all 18 | Photoshop 2026 bridge, existing Adobe subscription | 18 | $0 | $0 |
| **Total** | | **15 billed generations** | | **$0.504** |

**The receipt under-reports, and that is worth knowing.**
`generation-receipt.json` says `total_cost_usd: 0.4032`, which counts kept images
only. `generate-plates.mjs` merges results by `id`, so a retake overwrites the
first take's row and its cost silently disappears from the total. Three plates
were regenerated after visual review, so 15 generations were billed while the
receipt shows 12. The correction is recorded in the receipt itself under
`campaign_spend_correction`, with the discarded ids named. The upstream merge
behaviour is a real reporting gap in a shared script — it is not fixed here
because that script is outside this campaign's lane.

Basis for "measured": the Gemini Developer API's `generateContent` response
carries no invoiced dollar amount, so measured spend is
`successful generations x published per-image rate for one 1K output`. This is
the same basis every prior Gemini receipt in this repo uses. Per-call
`usage_metadata` is preserved in the receipt as corroborating evidence.

No other paid provider was touched: no HeyGen credits, no OpenAI image call, no
MuAPI, no Firefly.

---

## Every claim and where it comes from

Nothing in this campaign's copy is invented, and no statistic appears anywhere.
The house rule is to name the mechanism instead of reaching for a number.

| Claim in copy | Source |
|---|---|
| "An app is good at demand you did not create... a page of your own is good at demand you did create" | Live post: `/blog/do-you-have-to-leave-the-booking-app/` |
| "The one thing worth moving is not your bookings. It is the ability to reach the people who already like your work." | Same post |
| "You are not missing bookings because you are bad at your job" | Live post: `/blog/why-independent-stylists-are-invisible-outside-the-app/` |
| Crawling / indexing / ranking as three distinct steps | Live post: `/blog/does-google-index-your-booking-app-profile/` |
| "The domain is the unit search engines treat as an identity" | Same post |
| The shop-with-no-price-tags parable | Live post: `/blog/how-much-do-you-charge-dms-costs-bookings/` |
| "Access is a permission... a permission is not a possession" | Live post: `/blog/who-owns-your-client-list-booking-app/` |
| The four parts of a client list (identity, contact, history, reach) | Same post |
| The six things a bookable page needs | Live post: `/blog/what-a-bookable-page-actually-needs/` |
| "$199 for the first year... hosting is $9.99 a month if you keep it, plus the cost of renewing the domain" | `backend/config/famtastic-products.json` — `FAM-FOOT-199` price `199.00`, `renewal_sku: FAM-HOST-999` price `9.99`, `domain_renewal_separate: true` |
| Scope: one focused landing-page website, one year of managed hosting, first-year domain registration or connecting an owned domain | Same file, `FAM-FOOT-199.summary` |
| Palette hex values | `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` and `PALETTES.salon` in `marketing/creative/templates/famtastic-social-frame.jsx` |

**What the copy deliberately does not say:**

- **No competitor is named anywhere.** Market patterns are described generically
  ("a marketplace profile", "the platform", "a booking app"). No competitor's
  fees, commissions or terms are stated as fact — drop 05 explicitly says the
  only reliable way to know yours is to read your own agreement.
- **No business email and no maintenance.** Both are separate SKUs
  (`FAM-BUSINESS-EMAIL` $99, `FAM-MAINTENANCE` $49.99/mo) and neither is implied
  to be included. Only drop 06 mentions price at all, and it discloses the
  renewal in the same paragraph.
- **No delivery-time promise.** The "within 48 hours" claim that appears on some
  older posts has no SLA behind it in the catalog and is not repeated here.
- **No ranking, traffic or revenue promise.**
- **No `/web/` URL.** That is the Drupal admin prefix and 404s publicly.

---

## URLs, all curled

Verified 2026-09-05. The canonical form of a blog URL on this site carries a
**trailing slash** — without it the server returns a 301 to the slashed form, so
every link in the copy uses the slashed form and hits 200 on the first request.

| URL | HTTP |
|---|---|
| `https://famtasticdesigns.com/blog/do-you-have-to-leave-the-booking-app/` | 200 |
| `https://famtasticdesigns.com/blog/why-independent-stylists-are-invisible-outside-the-app/` | 200 |
| `https://famtasticdesigns.com/blog/does-google-index-your-booking-app-profile/` | 200 |
| `https://famtasticdesigns.com/blog/how-much-do-you-charge-dms-costs-bookings/` | 200 |
| `https://famtasticdesigns.com/blog/who-owns-your-client-list-booking-app/` | 200 |
| `https://famtasticdesigns.com/blog/what-a-bookable-page-actually-needs/` | 200 |
| `https://famtasticdesigns.com/packages/199-quick-start/?sku=FAM-FOOT-199` (landing) | 200 |
| landing + the full UTM string the runner appends | 200 |

Note for whoever touches the schedule next: the default landing in
`scripts/queue-campaign-drops.py` (`/onboarding?sku=FAM-FOOT-199`) 301s to
`/buy?sku=FAM-FOOT-199`, which itself 301s to the slashed form. This campaign
sets its own `landing_url` to the package page, which resolves 200 directly.

---

## The schedule

| Drop | When (ET) | Argument | Blog post |
|---|---|---|---|
| 01 | Sat 2026-09-06, 10:00 | The app is a lead source, not an address | `do-you-have-to-leave-the-booking-app` |
| 02 | Sun 2026-09-07, 18:00 | Booked solid, impossible to find | `why-independent-stylists-are-invisible-outside-the-app` |
| 03 | Tue 2026-09-09, 09:00 | Indexed is not the same as owned | `does-google-index-your-booking-app-profile` |
| 04 | Wed 2026-09-10, 19:00 | It is not the ninety seconds | `how-much-do-you-charge-dms-costs-bookings` |
| 05 | Fri 2026-09-12, 11:00 | A permission is not a possession | `who-owns-your-client-list-booking-app` |
| 06 | Sun 2026-09-13, 10:00 | What a bookable page actually needs | `what-a-bookable-page-actually-needs` |

Ordered as an argument — problem, cost, mechanism, action — not on a fixed
interval. Times sit where this audience is between clients.

**Channels: Facebook and Instagram only.** YouTube's OAuth token is expired and
TikTok is not approved for public posting; both need the owner. They are not
listed in the schedule at all, because requesting a dead channel manufactures a
failure the runner then has to report.

**Media.** `primary_media` on every drop is the 4:5 still — natively supported by
both Facebook and Instagram feeds with no crop. The 9:16 and 1:1 renders exist
for every drop and are recorded per drop under `surface_assets`; they are **not**
attached as carousel slides, because Instagram crops later slides to the first
slide's aspect ratio and that would cut the signature block off the square. Use
the 9:16 for stories and the 1:1 anywhere a square is wanted, manually.

---

## Files

```
marketing/campaigns/booked-and-losing/
  manifest.json           campaign record: argument, offer truth, audiences, palette,
                          representation, asset index, article index, gates
  posting-schedule.json   the only file the queue runner reads (schema v2)
  README.md               this file

marketing/creative/campaign-assets/booked-and-losing/
  plate-library.json      4 topics x 3 aspect variants, shared clauses and the salon
                          palette copied verbatim from marketing/creative/plates/
  generation-receipt.json per-image receipt + campaign_spend_correction
  plates/                 12 generated plates, no baked text (768x1376, 928x1152, 1024x1024)
  stills/                 18 composed stills, 1080x1920 / 1080x1350 / 1080x1080
```

Regenerate the plates:

```bash
cd marketing/creative/plates
node generate-plates.mjs \
  --library ../campaign-assets/booked-and-losing/plate-library.json \
  --out-root ../campaign-assets/booked-and-losing \
  --receipt ../campaign-assets/booked-and-losing/generation-receipt.json \
  --max-cost-usd 0.45
```

Stills are composed through the Photoshop bridge with
`marketing/creative/templates/famtastic-social-frame.jsx` (`famRender`), one to
two documents per `ps_run_script` call — more than that trips the AppleEvent
timeout.

---

## Verification

Every claim below was checked by looking, not by reading a tool's exit code.

- **All 12 plates were opened and viewed.** Three failed on sight and were
  regenerated: `bl-01-square-1x1` came back with a white polaroid border and a
  hard-edged panel on the right; `bl-04-story-9x16` had a dead-straight
  horizontal seam across the lower third; `bl-05-square-1x1` had a vertical seam
  down the left. All three are the same defect — the model producing the
  reserved negative space as a pasted rectangle instead of real room depth — and
  all three passed every automated check (valid JPEG, correct dimensions,
  non-zero bytes) while being unusable. The prompts were rewritten to make the
  emptiness an actual receding surface with an explicit no-seam / no-border
  clause, and the retakes were viewed and passed.
- **All 18 stills were opened and viewed at full size.** Four were re-rendered
  after review: `bl-drop-01-square-1x1` (eyebrow ran off the right edge, and a
  headline edit left it reading ungrammatically), `bl-drop-02-story-9x16` and
  `bl-drop-02-feed-4x5` (body type washed out against a bright daylight
  interior — scrim raised to 66 and 48), and `bl-drop-05-story-9x16` (body type
  was illegible over the white pages of the appointment book — the body line was
  dropped from that frame and the headline carries it alone).
- **Contrast was measured, not eyeballed, where it was in doubt.** On
  `bl-drop-02-story-9x16` the body region's 90th-percentile luminance dropped
  from 206 to 113 between scrim passes, which is what confirmed the scrim was
  actually applying before the frame was accepted.
- **Dimensions confirmed with `sips`** on all 18 files: six at 1080x1920, six at
  1080x1350, six at 1080x1080.
- **Schema validated**: `python3 scripts/new-campaign.py --validate booked-and-losing`
  → `READY booked-and-losing — 6 drops, schema v2, all checks pass`.
- **Runner dry-run**: `python3 scripts/queue-campaign-drops.py --campaign booked-and-losing --dry-run`
  → all 6 drops resolve to `facebook, instagram-standalone` with media found on
  disk, 0 blocked, 0 failures. Dry-run makes no Postiz contact and writes
  nothing.
- **Character limits asserted before writing the file.** The runner appends the
  tracked link and the hashtag block to the approved copy and then drops any
  channel whose assembled body exceeds its limit. Assembled Instagram bodies
  measure 1315-1851 characters against the 2200 limit, so no channel gets
  silently excluded at queue time.

### What could not be verified

- **Nothing was queued, so no Postiz read-back exists.** The brief said another
  step queues; `approval.publish` is `false` on every drop and the campaign
  status is `ready_for_evaluation`. The dry-run proves the schedule is
  *queueable*, not that it is queued.
- **Live rendering on Facebook and Instagram was not observed.** Crop behaviour
  for a 4:5 still on each surface is taken from the platforms' documented feed
  ratios, not from a posted test.
- **The typeface is a substitute.** Photoshop falls back to
  HelveticaNeue-Condensed because Inter and Space Grotesk are not installed as
  system fonts on this machine. Every still in this campaign carries that
  substitution, which is the same known gap recorded in
  `docs/playbook/RECIPES/CAMPAIGN_PRODUCTION.md`.
- **Photoshop was shared.** Another document was already open when this run
  started and other campaign agents were working concurrently. Every frame was
  viewed after rendering and none showed cross-contamination, but the bridge
  drives whatever application instance is running and that is not isolated.
