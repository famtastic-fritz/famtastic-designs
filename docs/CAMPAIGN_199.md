# $199 Website Offer — Campaign Playbook

Operating guide for promoting the $199 website package across every channel at
once. The goal is that the offer reads identically everywhere, every click is
attributable, and one page owns the conversion.

---

## 1. Decide these three things first

Flooding channels amplifies whatever is already true. These three items are
currently ambiguous in the repository, and each one will be repeated thousands
of times once the campaign runs.

### 1.1 The offer says two different things today

| Where | What it promises |
|---|---|
| `frontend/src/pages/HomePage.jsx` | "$199 **discovery build** — a working proof of your system" |
| `famtastic_pipeline.settings.yml` | "$199 **FAMtastic Basic Website**" — a complete site |
| `backend/scripts/seed-storefront.php` | "Get a **Professional Website** for $199", 48-hour delivery |

These are not the same product. One is a paid proof-of-concept that precedes a
larger build; the other is a finished website. A prospect who arrives from an ad
promising a website and lands on a page describing a discovery build will bounce.

`/199` is built on the **complete website** reading, because that is what the
transactional config actually charges for and what the pipeline fulfils. If the
discovery-build framing is the real intent, `/199` and the package config must
change together before any spend starts.

### 1.2 The delivery promise

`seed-storefront.php` states 48 hours. `/199` deliberately softens this to "days"
in the hero and qualifies it in the FAQ as "a couple of days once we have your
content," because content collection is the actual bottleneck. Before promising
48 hours in an ad, confirm it can be honoured on the twentieth simultaneous order,
not the first.

### 1.3 Capacity

Decide the maximum concurrent builds that can be delivered without missing the
promise, and stop or slow paid channels at that ceiling. A late or broken $199
build generates a public review that costs more than the campaign earns. Organic
channels can stay on; paid channels are the throttle.

---

## 2. The destination

Every channel points at **`https://famtasticdesigns.com/199`**.

It is short, speakable over the phone, printable, and fits in a bio link. Do not
send campaign traffic to `/packages/199-quick-start` — it is longer, CMS-dependent,
and splits measurement.

Aliases `/deal`, `/offer`, and `/website` redirect to `/199`.

---

## 3. Tagging every link

Attribution is captured on arrival by `frontend/src/lib/attribution.js` and rides
through to the lead record. First touch is kept permanently; last touch is refreshed
per visit. Both land in the Prospect's `discovery_notes`, and the channel is written
into the queryable `source` field as `contact-form:<channel>`.

**Online links** use standard UTM parameters:

```
https://famtasticdesigns.com/199?utm_source=instagram&utm_medium=social&utm_campaign=launch-199
```

Keep `utm_campaign=launch-199` constant so the whole campaign can be totalled.
Vary `utm_source` per platform and `utm_medium` per type (`social`, `organic`,
`cpc`, `email`, `offline`, `referral`). Use `utm_content` to split creatives:
`utm_content=before-after` vs `utm_content=price-led`.

**Offline links** cannot carry that. Use the short codes instead — a flyer prints
`famtasticdesigns.com/199?src=flyer` and expands to the same structure:

| Code | Channel | Code | Channel |
|---|---|---|---|
| `?src=card` | Business card | `?src=gbp` | Google Business Profile |
| `?src=flyer` | Flyer / door hanger | `?src=ig` | Instagram |
| `?src=van` | Vehicle magnet | `?src=fb` | Facebook |
| `?src=yard` | Yard sign | `?src=li` | LinkedIn |
| `?src=ref` | Word of mouth | `?src=nd` | Nextdoor |

Codes live in `SHORT_CODES` in `frontend/src/lib/attribution.js`; add new ones there.

---

## 4. Channel playbook

Ordered by return per hour for a local services business. Work down the list —
the first three are free and outperform paid ads at this price point.

### 4.1 Google Business Profile — do this first

A complete profile is the single highest-return asset for a local business, and
the $199 offer is exactly the kind of thing GBP's offer posts are built for.

- Add "Website design" and "Web developer" as services, priced at $199.
- Post the offer as a **What's New** post weekly; they expire after 7 days.
- Put `famtasticdesigns.com/199?src=gbp` as the post link.
- Ask every completed customer for a review the day their site goes live.

Post copy:

> **Get your business online for $199.** A custom, mobile-ready website — built
> for your business, not a template. Contact form, SEO basics, and launch support
> included. One fixed price, no monthly surprises.

### 4.2 Facebook & Instagram — organic

Post to your own page, then share into local business and community groups where
self-promotion is permitted. Read each group's rules first; being removed from
the ten best local groups costs more than the posts earn.

Price-led post:

> Most small businesses I talk to don't have a website because every quote came
> back at $3,000+.
>
> I build them for $199. One time. Custom, works on phones, has a contact form
> so customers can actually reach you, and I get it live for you.
>
> Not a template you have to finish yourself. Not a monthly subscription.
>
> famtasticdesigns.com/199

Problem-led post:

> Your customers are Googling what you do right now. If you don't have a website,
> they're finding your competitor instead.
>
> A professional site, built for your business, for $199 — launch support included.
>
> famtasticdesigns.com/199

Social-page-only post:

> A Facebook page is rented ground. The algorithm decides who sees you, and the
> rules can change tomorrow.
>
> A website is the one thing online that's actually yours. $199, custom-built,
> live in days.
>
> famtasticdesigns.com/199

Best-performing format at this price: a **before/after screenshot** of a real
site you built. Show the old or absent site, then the new one. Get the customer's
permission first, and only use work you actually did.

### 4.3 Nextdoor

Nextdoor converts unusually well for local services and is often uncontested.
Post from a business page in the Local Deals or general feed:

> Neighbors — I build websites for local businesses for $199, one time.
>
> Custom-built, works properly on phones, and includes a contact form so customers
> can reach you. If you've been putting this off because of the price, this is
> what I do.
>
> famtasticdesigns.com/199

### 4.4 Free directory listings

Each is a permanent backlink plus its own search surface. One session, lasting value.

Bing Places · Apple Business Connect · Yelp for Business · Nextdoor Business ·
Yellow Pages · Better Business Bureau · Alignable · Chamber of Commerce ·
Angi · Thumbtack · Clutch · Manta

Use one consistent blurb everywhere — identical name, address, and phone across
every listing is what makes local SEO work:

> FAMtastic Designs builds custom, mobile-ready websites for small businesses
> starting at $199 — including lead capture, SEO foundations, and launch support.
> Engineering-led studio with 22+ years of systems experience.

### 4.5 Referrals

Cheapest acquisition you have, and the only one that compounds. Every delivered
customer gets asked, once, at the moment the site goes live:

> Glad you're happy with it. If you know another business owner still without a
> site, send them famtasticdesigns.com/199 — I'll take good care of them.

Consider a standing offer: a free revision or a service credit for any referral
that converts. Track with `?src=ref`.

### 4.6 Offline

Every physical surface should carry the short URL, not a QR code alone — people
type URLs they remember, and `famtasticdesigns.com/199` is memorable.

- Business cards: the URL on the back, nothing else competing with it.
- Vehicle magnet or decal: business name, "Websites $199", the URL.
- Flyers on community boards: grocery stores, hardware stores, coffee shops,
  churches, barbershops, laundromats.
- Yard sign at each delivered customer's business, with their permission.

Print `famtasticdesigns.com/199` — the `?src=` code can be added to a QR code on
the same asset so scans and typed visits both attribute.

### 4.7 Paid ads — only after capacity is confirmed

Start small, one channel at a time, and only once section 1.3 is settled.

**Google Search** is the highest-intent option. Target the phrases people actually
type, in your service area:

`website design near me` · `small business website` · `affordable web designer` ·
`cheap website design` · `local website designer`

Responsive search ad copy:

- Headlines: `Business Website — $199` · `Custom Site, Not a Template` ·
  `Live in Days, Not Months` · `One Fixed Price, No Surprises`
- Descriptions: `Custom, mobile-ready website built for your business. Contact
  form, SEO basics, and launch support included. One-time $199.`

**Meta ads** work better with a creative-led approach — a before/after image and
a short video of you talking about the offer will beat static text. Target local
business owners in your service radius.

Tag every ad URL: `?utm_source=google&utm_medium=cpc&utm_campaign=launch-199&utm_content=<ad-name>`.

### 4.8 Existing contacts

People who already know you convert far better than strangers. Anyone whose email
you legitimately hold — past customers, enquiries who never bought, your own
network — can be told about the offer directly:

> Subject: I'm building business websites for $199
>
> Hi {{name}},
>
> Quick note in case it's useful to you or someone you know. I'm building custom
> websites for small businesses at a fixed $199 — mobile-ready, contact form,
> SEO basics, and I handle getting it live.
>
> If your site is dated, or you never got around to one, this is an easy fix:
> famtasticdesigns.com/199
>
> Either way, hope business is good.
>
> — Fritz, FAMtastic Designs

Include a working unsubscribe link and honour it immediately.

### 4.9 Cold outreach — through the pipeline only

The repository already has a cold outreach path: `outreach.prepare` stages one
message per qualified lead, an operator approves an exact campaign key, and every
send rechecks approval and suppression. Real sending additionally requires
`FAMTASTIC_ALLOW_REAL_OUTREACH=true`. See `docs/EMAIL_AUTOMATION.md`.

Use that path and leave the gates in place. The reason is practical, not
procedural: bulk unsolicited email to purchased or scraped lists gets the sending
domain blocklisted, and `famtasticdesigns.com` is the same domain your customer
notifications, Stripe receipts, and proof links go out on. Losing its reputation
takes the whole pipeline down with it, and recovery takes months. CAN-SPAM also
requires accurate headers, a physical postal address, and a working opt-out on
every commercial message.

Cold outreach that works at this price point is small-batch and specific: a real
proof built for a named business, sent to that business, referencing their actual
situation. That is exactly what the proof campaign system already does. Scale it
by improving the proofs, not by widening the list.

---

## 5. Reading the results

Every lead arrives tagged. To see which channels are producing, group Prospect
records by `source` — the value is `contact-form:<channel>` for tagged visits and
plain `contact-form` for untagged ones. Full first- and last-touch detail sits in
`discovery_notes`.

Watch three numbers per channel:

1. **Leads** — how many enquiries it produced.
2. **Conversion** — how many of those paid. A channel with many leads and no sales
   is attracting the wrong people; check the promise in that channel's copy.
3. **Effort** — a free channel producing two customers a month beats a paid one
   producing three at a loss.

A high share of untagged leads means links are being shared without tags. That is
normal for word of mouth, but if it dominates, check that the links you posted
actually carry their parameters.

Review after two weeks. Kill what produced nothing, double the two best, and leave
the rest running.

---

## 6. What ships with this playbook

| Change | File |
|---|---|
| `/199` campaign landing page | `frontend/src/pages/OfferPage.jsx` |
| Route plus `/deal`, `/offer`, `/website` aliases | `frontend/src/App.jsx` |
| Attribution capture (UTM, click IDs, `?src=` codes) | `frontend/src/lib/attribution.js` |
| Attribution attached to lead submissions | `frontend/src/components/v1/ContactForm.jsx` |
| Page metadata and `Product`/`Offer` schema | `frontend/src/seo.js` |
| Schema injected into the prerendered shell | `frontend/scripts/generate-seo-shells.mjs` |
| Site-wide mobile header overflow fix | `frontend/src/index.css`, `frontend/src/components/v1/SiteNavbar.jsx` |

The header fix is included because it blocks the campaign rather than because it
belongs to it. Every page overflowed its viewport on a phone — 102px past the
edge at 320px wide — so the whole site scrolled sideways. Campaign traffic from
social and Google Business Profile is overwhelmingly mobile, and that behaviour
reads as broken. The header CTA now collapses into the burger menu below 960px,
matching the breakpoint the burger already used; the desktop CTA is unchanged
and Contact remains reachable from the mobile menu.

Offer wording, inclusions, and price live in the `OFFER` constant at the top of
`OfferPage.jsx`. They must stay in agreement with the `package` block in
`backend/web/modules/custom/famtastic_pipeline/config/install/famtastic_pipeline.settings.yml`,
which is what actually charges the customer.

Deployment follows `docs/FRONTEND_DEPLOYMENT.md` — a clean worktree, a committed
SHA, and explicit authorization before `--apply`.
