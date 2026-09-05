# See It First — proof before payment

**Slug**: `see-it-first` · **Offer**: `FAM-FOOT-199` · **Built**: 2026-09-05
**Channels**: Facebook + Instagram only · **Window**: 2026-09-06 → 2026-09-13 (8 days, 6 drops)
**Measured spend: $0.00** against a $1.50 ceiling. Zero image-API calls.
**Nothing is queued.** The owner holds the publish gate.

---

## Why this campaign exists

Every other FAMtastic campaign argues that the reader needs a website. This one
starts *after* they already agree, and answers the sentence that actually ends
the conversation:

> "I have been burned before."

That objection cannot be answered with enthusiasm, a discount, or a louder
promise, because the reader has already heard all three. It can only be answered
by changing the **order of operations**: the customer looks at real work before
any money moves.

That differentiator is not a slogan. It is two rows in
`docs/CAPABILITY_REGISTRY.md`, and the campaign is built to the exact width of
what those rows say — no wider.

---

## What the registry actually says, and what the copy therefore claims

| Registry row | Registry status | What this campaign is allowed to say |
|---|---|---|
| Owner-gated three-direction website proofs | **Production-deployed; customer delivery not yet proven** | The mechanism exists and runs: three directions, generated from the customer's real information, privately reviewed by a person before the customer sees them. |
| Anonymous view-only proof sharing | **Production smoke-tested** | A proof opens in a private, view-only link — no account, no login — and an invalid signature returns no data. |

**The line this campaign does not cross.** "Deployed" is not "many customers have
used it." So there are **no testimonials, no customer counts, no satisfaction
figures, no case studies, and no before/after stories** anywhere in these six
drops. Every claim is a description of a mechanism that exists, which is the
house style and needs no citation (BRAND.md, "Describe a mechanism honestly").

**No timing promise of any kind.** A "within 48 hours" claim is still live on
five older posts with no SLA anywhere in `backend/config/famtastic-products.json`.
It is not repeated here, and no substitute timing language was invented.

**No competitor is named, and no competitor's prices are stated.** Drop 3 says
that nobody controls a Google ranking. That is a statement about control, not an
attack on a company (BRAND.md: educate, never compete).

Full claim-by-claim tracing lives in `manifest.json` → `claims_ledger` and
`claims_deliberately_excluded`.

---

## The six drops

Each links to a blog post that was **already live and curled to HTTP 200 on
2026-09-05** before it was written into the schedule. The trailing slash is the
canonical form; the slashless URL 301s to it.

| # | When (ET) | Argument | Archetype | Live source post |
|---|---|---|---|---|
| 1 | Sun 09-06, 10:20 | The objection, answered by the order of operations | `standard` + **`proof-sheet`** concept object | `/blog/proof-first-website-see-before-you-pay/` |
| 2 | Tue 09-08, 12:50 | A promise is a sentence; a proof is your site on your screen | `comparison` | `/blog/199-website-inclusions-and-boundaries/` |
| 3 | Wed 09-09, 08:20 | The promise we refuse to make | `monument` | `/blog/do-you-guarantee-google-rankings/` |
| 4 | Thu 09-10, 17:50 | Trust is a sequence you can see in advance | `checklist` | `/blog/what-makes-business-website-trustworthy/` |
| 5 | Sat 09-12, 11:20 | There is no silence after you pay | `split` | `/blog/after-buying-199-website/` |
| 6 | Sun 09-13, 15:50 | You have seen the work; now the number | `offer-card` | `/blog/what-does-199-website-include/` |

Six drops, six different layout archetypes. Rotating them is the doctrine
(CAMPAIGN_ART_DIRECTION_V1 Rule 3); one layout used six times is the
cookie-cutter failure the doctrine exists to prevent.

**Drop 4's checklist is not written prose.** Its five rows are
`FAM-FOOT-199.fulfillment.milestones` copied out of
`backend/config/famtastic-products.json` verbatim: intake, proof, revision,
approval, launch. The product record already contains the campaign's argument.

---

## Channels, and why only two

Facebook and `instagram-standalone`. YouTube's OAuth token is expired and TikTok
is not approved for public posting; both need the owner. Requesting them here
would only manufacture `BLOCKED` rows for channels that cannot publish today.

One `facebook_instagram` copy key serves both, because
`scripts/queue-campaign-drops.py` lists that key first in `COPY_PREFERENCE` for
each integration.

### The seconds offset is deliberate

Every `scheduled_time` ends `:07`. When `--schedule` converts drafts to a live
schedule, the queue script groups Postiz sibling records **by exact publishDate
string**, and converts every record it finds in that group. Three other campaigns
were authored in parallel on 2026-09-05. A shared timestamp to the second would
let this campaign's conversion pull another campaign's records with it. The
seconds offset makes that collision impossible rather than merely unlikely.

---

## Landing URL

`https://famtasticdesigns.com/buy/?sku=FAM-FOOT-199` — verified 200.

The queue script's `DEFAULT_LANDING`
(`https://famtasticdesigns.com/onboarding?sku=FAM-FOOT-199`) 301s to it with the
query preserved, so `landing_url` is set to the verified destination rather than
the hop. It already carries a `?`, which the script's `tracked_link()` requires
because it appends `&utm_source=...`.

No `/web/`-prefixed URL appears anywhere. That is the Drupal admin prefix; it was
used only to *query* the JSON:API for the live post list.

---

## Verification actually performed

Six automated checks written earlier in this session returned false results,
including one that reported 15 passes when zero had passed. So:

- **All 18 stills were opened and looked at.** Not `ls`, not a success message.
- Two defects were caught that way and fixed. Both were invisible in the tool's
  return value: a proof card clipped on the canvas edge, and a CTA pill sitting
  on top of a body line in the square monument. See the asset-set README.
- `posting-schedule.json` validated against
  `marketing/engine/schemas/posting-schedule.schema.json` with the **real
  `jsonschema` library** in a scratch venv, not only the hand-rolled fallback
  (`campaign_schema_validate.py` warns that the fallback can pass a manifest a
  real validator would reject). Result: no errors.
- `python3 scripts/queue-campaign-drops.py --campaign see-it-first --dry-run`
  → `PASS — queued=0 adopted=0 blocked=0 failures=0`, all six drops resolving to
  `facebook, instagram-standalone` with one asset each, and correct UTC
  conversion. **No Postiz contact was made and nothing was queued.**
- Composed post length (body + tracked link + tags) computed per channel against
  `CONTENT_LIMITS`: 749–951 characters against Instagram's 2,200. No channel is
  near its limit, so none can be silently excluded at queue time.
- Every copy string is pure ASCII, checked programmatically.

## Not verified

- **No human has approved this copy or the send.** `approval.publish` is `false`
  on all six drops.
- The campaign has never touched Postiz, so there is no read-back of a real
  queue state — only the dry run above.
- The palette has not been tested against a real audience. It is argued, not
  validated (a known gap CAMPAIGN_ART_DIRECTION_V1 records for every palette).

---

Creative details — palette argument, archetype rationale, the new concept
object, spend, and the two defects — are in
`../../creative/campaign-assets/see-it-first/README.md`.
