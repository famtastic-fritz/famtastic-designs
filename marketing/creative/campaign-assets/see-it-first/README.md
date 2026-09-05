# See It First — campaign asset set

Eighteen stills: six arguments, six layout archetypes, one palette, three aspect
ratios each. **919 KB total, $0.00 marginal cost** — every frame composed in
Photoshop 2026 through the local MCP bridge on the Creative Cloud subscription
FAMtastic already pays for. No image API was called.

Campaign, copy, schedule, and the claim ledger:
`../../../campaigns/see-it-first/`.

| Asset | Archetype | Formats | The argument it carries |
|---|---|---|---|
| `drop-01-see-it-first` | `standard` + **`proof-sheet`** | 9:16, 4:5, 1:1 | Three proofs, one marked. The decision the reader has not made yet |
| `drop-02-promise-vs-proof` | `comparison` | 9:16, 4:5, 1:1 | Geometry carries it: a promise is thin, a proof has mass |
| `drop-03-no-guarantees` | `monument` | 9:16, 4:5, 1:1 | One word, "NO.", standing on a slab. Presence over explanation |
| `drop-04-trust-checklist` | `checklist` | 9:16, 4:5, 1:1 | The five published milestones, straight out of the product record |
| `drop-05-nothing-in-the-dark` | `split` | 9:16, 4:5, 1:1 | A held band with a diagonal cut: the statement, then the sequence |
| `drop-06-what-199-includes` | `offer-card` | 9:16, 4:5, 1:1 | The ask, with the price as hero and the renewal in the terms line |

Every file is 1080px wide: 1080×1920, 1080×1350, 1080×1080.

---

## The palette argument: `paper`

`paper` — ink `#1F6F4A` on warm paper `#F4F1EA`.

**Argued from the subject, not chosen for looks.** A proof is a physical thing
you are handed, look over, and mark before you commit — the object at the centre
of this campaign is literally a printed page under review. That already points at
paper.

The stronger reason is who the reader is. This campaign is addressed to somebody
who has been sold to before and lost money doing it. **An ad-shaped thing is the
exact object they distrust.** The palette's own documented purpose is
"anything that must read sober rather than as an ad" — proposals, documents,
contracts. The frames had to look like a document making a case, not a promotion
making a pitch, or the design would be arguing against the copy.

Black-and-lime was wrong here for the same reason: `#070907` + `#7cfc00` is the
FAMtastic *site* identity, and a campaign that looks like the seller's own brand
turned up to full volume is a campaign about the seller.

`anchor-take-a` was the other candidate and was rejected on a real distinction,
not a preference. It exists to make a still sit correctly *beside the HeyGen
take-a video*. This campaign ships no video, so its only argument would have been
"it is the measured one" — and its olive `#7FB449` is softer than the deep ink
green, which is the wrong register for a document about not being deceived.
Ink green also carries the "checked / approved" reading the proof sheet needs.

### Anchor grading — measured, and a stated divergence

Grade-to-the-anchor is a standing rule
(`marketing/creative/heygen/reference-tokens.json`: mean luminance 150–175,
olive at 1–2% of frame). Measured over all 18 rendered PNGs:

| | value |
|---|---|
| Mean luminance, campaign | **221.8** |
| Range | 207.9 (`drop-05` split, largest held band) → 232.6 (`drop-01` square) |
| Anchor band | 150–175 |

**This campaign is outside the band, upward, on purpose, and says so in writing**
— which is the escape hatch the rule itself provides for a palette argued from
its own subject. It diverges in the same direction as the anchor rather than
against it: the anchor take is a *light* frame (mean 162), and every dark palette
would have been a bigger departure than this one. These stills must never be cut
in beside `take-a` without regrading.

Contrast was checked rather than assumed. Ink `#1F6F4A` on `#F4F1EA` is 5.4:1;
body `#5A564E` on the same ground is 6.5:1; the near-black headline is far above
both.

---

## The new concept object: `proof-sheet`

Added to `marketing/creative/templates/famtastic-social-frame.jsx`, following the
existing house pattern (`business-cards`, `address-bar`) exactly: drawn crisp at
full opacity on its own layer, forced to `NORMAL` blend so it is the *subject*
of the frame rather than atmosphere behind type.

Rule 2 says read the post's claim and ask what physical object the claim is
about. The claim is "you look at real work before you pay." The object is three
page proofs with a selection box under each, **one box filled**. The reader sees a
decision that has not been made yet, which is the whole campaign in one glance —
the standard `business-cards` set.

The direction names printed under the cards are **`SAFE`, `WILD`, `OMG`** — the
product's real vocabulary from the three-direction proof system in
`docs/CAPABILITY_REGISTRY.md`, not placeholder text, exactly as `business-cards`
carries a real email address.

It adapts to its region instead of assuming one shape: a wide bottom band
(9:16, 4:5) lays the three proofs across; a tall right-hand column (1:1) stacks
them down. A fixed row would have run the third proof off the canvas.

---

## Two template fixes this campaign forced

Both were found by **looking at the render**, and both were invisible in the
bridge's success message. Both fixes are additive and cannot make any other
campaign's output worse.

1. **A concept object clipped on the canvas edge.** Art regions deliberately
   bleed to the edge, because atmosphere is blended at low opacity and is
   *supposed* to run off. A crisp object inherits that bleed and loses its edge —
   the third proof card ended exactly on x=1080. `famApplyArt` now holds a
   concept object inside the same margin the type respects.
2. **A chip clipped its own last letter.** `famChip` estimated width at
   `length × size × 0.62` while setting 140 tracking, which is another 0.14em per
   character. "WEB BASICS" rendered as "WEB BASIC|S". The estimate is now 0.76 —
   glyph width plus tracking. The chip's width is not used for positioning
   anywhere, so a wider block only ever adds padding.

A third fix was in the same area: `renderFamtasticFrames` narrowed the type
column to the left third whenever *any* concept object was present, but in 9:16
and 4:5 the art region is a full-width band **under** the type, not a right-hand
column. Headlines were being squeezed into a 324px column beside empty canvas and
body copy shrunk to about 16pt. The narrowing is now conditional on the region
actually starting right of the text margin.

---

## Two defects fixed in the copy, not the code

1. **`drop-03` square: the CTA pill sat on top of the second body line.**
   `famLayoutMonument` clamps the pill upward to a ceiling, and on a 1080-tall
   frame that ceiling is *above* a two-line body. Fixed here by writing one body
   line for the square and 4:5 crops. **The underlying template bug is not
   fixed** — the clamp will silently overlap type for anyone who passes two body
   lines to `monument` at 1:1. Deliberately left alone: three other campaign
   agents were rendering against this file at the same time, and changing shared
   layout maths under them was the larger risk. It is reported as a known gap.
2. `drop-01`'s square crop carries a shorter two-line body than its 9:16 and 4:5
   crops, because the concept object legitimately owns the right column there and
   the type column is genuinely narrower. Same argument, fewer words.

---

## Spend: $0.00, and why no anchor image was bought

The ceiling was $1.50; a gpt-image-2 flagship runs about $0.18 and Gemini Flash
Lite about $0.034. Neither was called. This is a decision, not an omission:

- **The anchor's job in the doctrine is to be the style reference cheap
  generated tiers are graded against.** This campaign generates zero cheap tiers.
  All 18 assets are deterministic Photoshop renders at zero marginal cost, so
  there is nothing downstream for an anchor to grade.
- **The template can only composite a plate in the `standard` layout**
  (`famApplyArt` is called from `renderFamtasticFrames` and from nothing else),
  and `plateFull` returns before any concept object is drawn. The one frame that
  could have carried a photographic plate is exactly the frame the proof-sheet
  object owns. A bought anchor would have had no place to land.
- **A photograph would argue against the campaign.** The reader distrusts things
  that look like advertising; warm stock-style photography is the most
  ad-shaped thing available.

The capability was proven present rather than assumed absent:
`swift website-delivery-swarm/openai_image_worker.swift --preflight` returned
`OPENAI_IMAGE_PREFLIGHT_AUTHENTICATED`. Preflight makes no generation request and
bills nothing. The route is available; it was not needed.

---

## Copy accuracy

Checked against `backend/config/famtastic-products.json`. `FAM-FOOT-199` is
**one focused landing-page website, one year of managed hosting, and first-year
domain registration or connecting a domain the customer already owns.** Business
email is a separate $99 SKU (`FAM-BUSINESS-EMAIL`); maintenance is a $49.99/mo
upsell (`FAM-MAINTENANCE`). Renewal is `FAM-HOST-999` at $9.99/mo after the
included year, with the domain renewing separately.

Both drops that state the first-year price also state the renewal, per BRAND.md.
Both name the exclusions rather than omitting them — in a campaign about not
being burned, a quiet exclusion would be the campaign contradicting itself.

No invented statistics. No testimonials or customer counts. No delivery-time
promise. No competitor named or priced. Nothing here is scheduled or published —
these are produced assets awaiting a human decision.

## Known gaps

- `famLayoutMonument`'s CTA-pill ceiling can overlap body copy at 1:1 (above).
- `docs/marketing/CAMPAIGN_ART_DIRECTION_V1.md` and the creative-studio SKILL.md
  both list the available concept objects; neither yet mentions `proof-sheet`.
  Those files are outside this campaign's write lane and were left untouched.
- Brand faces Inter and Space Grotesk are still not installed, so these render in
  the documented stand-ins (HelveticaNeue-CondensedBold / AvenirNext) like every
  other Photoshop-tier asset in the repo.
- The palette is argued, not audience-tested.
