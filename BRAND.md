# Brand Context

> This file is auto-loaded by all blog sub-skills. Last updated: 2026-09-04.
> Sourced from real repo doctrine and live published content — not invented
> positioning. Every claim here traces to a file or a live page; see
> "Provenance" at the bottom.

## Audience

- **Primary**: Owner-operator of a local service business who currently has no
  independent web presence — bookings arrive through DMs, a marketplace app, or
  a link-in-bio page.
- **Secondary**: The same owner one step later, who has a site and now needs it
  to actually produce leads, quotes, and repeat customers.
- **Expertise**: Beginner on web/technical topics, expert in their own trade.
  Write to a skilled practitioner who is not a web person.
- **Active problems**:
  - Paying marketplace app fees (monthly fee plus a percentage cut on new
    clients) without owning the client relationship or data
  - Using a `@gmail.com` / `@yahoo.com` address on high-ticket quotes and
    losing credibility at the moment of decision
  - Being invisible in local search — discoverable only inside one app or by
    already knowing the owner's handle
  - Answering "how much do you charge?" manually, one DM at a time, and losing
    customers to whoever replied first
  - Assuming a real website costs thousands and takes months
- **Common misconceptions**:
  - "A cheap website means a template with my logo dropped on it"
  - "Instagram/Linktree is basically the same as having a website"
  - "SEO means someone can guarantee me a #1 Google ranking"
  - "I'll get a real website later, once the business is bigger"

## Positioning

- **Official entity name**: FAMtastic Designs
- **Homepage**: https://famtasticdesigns.com/
- **Logo**: https://famtasticdesigns.com/brand/famtastic-mark.svg
- **Business address (as published in site schema)**: 1729 NW St. Lucie West
  Blvd #1181, Port Saint Lucie, FL 34986, US
- **sameAs profiles** (connected and verified in the publishing stack):
  - Instagram: https://instagram.com/famtasticdesigns
  - TikTok: https://tiktok.com/@famtasticdesigns8
  - Facebook: FAMTastic Designs (page connected; public URL not recorded in repo)
  - X: https://x.com/FritzMedine (personal-brand account, not a company handle)
  - YouTube: connected channel currently resolves to `@nineoo1` — **flagged as
    unresolved**: this does not match the brand and should be corrected or
    replaced before being cited as an official profile.
- **Wikidata Q-ID**: none (not notable; leave blank rather than invent)
- **Mission**: Build digital business systems — websites, intake, automation,
  analytics — at whatever level the business is actually at, starting from a
  price a working owner-operator can say yes to.
- **Distinctive POV**: We invest upfront because we believe in the client's
  business before they've spent thousands proving it to an agency. The results
  are the proof, not the pitch — you see three real, working design directions
  built from your actual business before you pay.
- **What we are NOT**:
  - Not a template marketplace — every proof is built from the customer's real
    business information
  - Not a retainer agency — scope is defined and published, not open-ended
  - Not a booking app or marketplace — we build the hub the customer owns,
    we do not become the middleman
  - Not an SEO firm selling ranking promises
- **Competitive stance**: This brand argues against *categories and patterns*
  (agency retainers, marketplace fee structures, link-in-bio pages as a
  substitute for an owned site) and never against a named company. A named
  competitor is described generically and factually or not at all. This is a
  hard rule, not a stylistic preference — see Editorial Rules.

## Editorial Rules

### Always do
- Answer the reader's actual question in the first screen, before any pitch
- Take scope, price, and renewal statements only from live product pages or
  `backend/config/famtastic-products.json` — the live page is the contract
- Verify every internal link resolves (HTTP 200) *before* publishing. The real
  package URL is `/packages/199-quick-start`; the hub is `/packages`
- Classify each post into one of the five customer jobs (see Topic Scope)
- Disclose renewal terms whenever first-year pricing is mentioned
- Describe a mechanism honestly ("here is what happens when…") instead of
  reaching for a statistic we cannot source

### Never do
- Invent statistics, testimonials, customer outcomes, urgency, or scarcity
- Make a competitive claim about a named company
- Promise Google rankings, uptime, or page-speed outcomes
- Publish a capability claim not supported by `docs/CAPABILITY_REGISTRY.md`
- Use a `/web/`-prefixed URL — that is the Drupal backend path and 404s publicly
- Present AI-generated draft copy as reviewed fact without a human pass

### Taboo phrases
- "guaranteed rankings" / "#1 on Google" / "rank #1"
- "skyrocket", "explode your traffic", "10x your leads"
- "limited time" / "act now" (manufactured urgency)
- "industry-leading", "best-in-class" (unsourceable superlatives)

### Required disclosures
- First-year pricing must state what renews and at what rate ($9.99/mo basic or
  $19.99/mo business managed hosting after year one; domain renews separately)
- Any post describing proof/preview delivery must state that directions are
  reviewed by a person before a customer sees them

## Topic Scope

- **In scope** (the five customer jobs — every post maps to exactly one):
  - **Get Found** — local search, domains, discoverability, structured data
  - **Get Customers** — conversion, intake, proof-first buying, credibility
  - **Get Paid** — pricing, packages, renewals, what's included
  - **Serve Customers** — maintenance, support, hosting, care after launch
  - **Grow and Automate** — analytics, lead automation, AI agents, scheduling
- **Partial scope** (only with an original, capability-backed angle): general
  small-business operations, marketing strategy, branding
- **Out of scope**: anything requiring a capability not in the registry; legal,
  tax, or financial advice; ranking or revenue guarantees
- **Recurring formats**: none established yet. The one live series is
  `cost-web-basics-launch` (campaign-tied, not an editorial column).

## Provenance

Every section above traces to real repo/live sources:
- Audience + pains: `marketing/campaigns/cost-is-not-the-reason/manifest.json`,
  `marketing/campaigns/ghost-town-ep1/manifest.json` (`audiences[]`)
- Mission, POV, five customer jobs, claims policy: `docs/DEMAND_ENGINE_DOCTRINE.md`
- Products, prices, renewal rates: `backend/config/famtastic-products.json`
- Entity name, logo, address: live BlogPosting/Organization JSON-LD on
  famtasticdesigns.com
- Social profiles: connected integrations in the publishing stack
- Link and URL rules: `.site-context/SITE-LEARNINGS.md` (2026-09-04 entry)
