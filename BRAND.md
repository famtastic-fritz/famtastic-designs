# Brand Context

> This file is auto-loaded by all blog sub-skills. Last updated: 2026-09-04.
> **v2** — rewritten after the owner interview. v1 was inferred from repo
> evidence alone and got the audience scope, business model, and content
> mission materially wrong. See "What changed in v2" at the bottom.

## Audience

- **Primary**: A business that is **already doing business** — real customers,
  real revenue — but has **no web presence**. Any size. They are operating on
  social media, business cards, flyers, and word of mouth.
- **Secondary**: The same business one layer deeper — readers who want to
  understand the automation and AI services FAMtastic sells, how they work,
  and why they'd need them. Many are unfamiliar with AI terminology and some
  are actively afraid of it.
- **Ceiling, not the focus**: The studio serves **companies of any size**
  seeking agentic business solutions engineered and deployed. No-web-presence
  is the current content focus and entry point — it is not the limit of who
  FAMtastic builds for. Do not write as if the audience caps at solo
  operators.
- **Expertise**: Expert in their own business, **beginner on web and AI**.
  Assume zero familiarity with AI vocabulary. Never assume fear is ignorance —
  it is usually a reasonable reaction to hype.
- **Active problems**:
  - Doing real business with no website at all
  - Spending on business cards, flyers, and social media — which cost more and
    return less than an owned web presence
  - Not knowing what automation or "AI agents" actually do, in plain terms
  - Suspecting they're behind, without a clear, non-humiliating way to start
  - Believing a real website is a large, slow, expensive project
- **Common misconceptions** (these anchor information gain):
  - **The biggest one**: that social media + business cards + flyers are a
    sufficient substitute for a web presence — when they are in fact more
    expensive and less beneficial
  - That web presence is a "future" upgrade rather than the current baseline
  - That AI/automation is hype, or is for big companies only, or will replace
    them rather than work for them
  - That a website costs thousands and takes months
  - That "cheap" means a template with their logo dropped on it

## Positioning

- **Official entity name**: FAMtastic Designs
- **Homepage**: https://famtasticdesigns.com/
- **Logo**: ⚠️ **NOT FINAL.** `/brand/famtastic-mark.svg` is a working
  placeholder. Neither the master FAMtastic mark nor the finalized FAMtastic
  Designs mark is complete. Do not treat the current file as canonical brand
  identity, and do not cite it as the definitive logo in schema or press
  contexts until the real marks ship.
- **Published address (live site schema)**: 1729 NW St. Lucie West Blvd #1181,
  Port Saint Lucie, FL 34986, US
- **sameAs profiles**:
  - Instagram: https://instagram.com/famtasticdesigns
  - TikTok: https://tiktok.com/@famtasticdesigns8
  - Facebook: FAMTastic Designs
  - X: https://x.com/FritzMedine (owner's personal-brand account)
  - YouTube: `@nineoo1` — owner-confirmed as the correct channel **for now**;
    intentional, not an error. Expect this to change when brand channels
    consolidate.
- **Wikidata Q-ID**: none (not notable; leave blank rather than invent)
- **Mission**: Get a working business into the game online — then keep
  engineering what it needs as it grows.
- **Distinctive POV** (two layers, both load-bearing):
  1. **"Web presence is not the future. It is the now."** The reader is not
     early or late to a trend; they are currently absent from where business
     already happens.
  2. **We remove cost as an excuse because we believe in the business before
     it has proven anything to us.** $199 for the first year — about 55 cents
     a day — is a deliberate investment in the customer, not a discount.
- **The strategic logic behind the price** (internal context; informs tone,
  never stated as a pitch): the entry package exists to get a real business
  online, which increases that business's income, which increases its capacity
  to buy the automation, AI, and systems work FAMtastic actually specializes
  in. Content should treat the $199 customer as the beginning of a
  relationship, never as the whole relationship.
- **What we are NOT**:
  - Not a template marketplace — proofs are built from the customer's real
    business information
  - Not a retainer agency — scope is published, not open-ended
  - Not an SEO firm selling ranking promises
  - Not a booking app or marketplace middleman
  - Not an AI hype shop — automation is explained in plain terms and tied to a
    real capability, or it isn't published
- **Competitive stance** (internal only — published posts never attack a named
  company; see Editorial Rules):
  - **Inertia / doing nothing** — the real primary competitor. Most readers
    are not choosing a rival vendor; they are choosing to keep postponing.
    Most content should be written against the excuse, not against a brand.
  - **DIY site builders** — the reader still has to design, write, and
    maintain it themselves, and usually stalls. FAMtastic delivers three real
    working directions built from their business before they pay.
  - **Cheap freelance marketplaces** — price-comparable, but no defined scope,
    no proof-first step, no hosting/domain/email included, no path into
    automation afterward.

## Editorial Rules

### Always do
- Answer the reader's actual question in the first screen, before any pitch
- **Define every AI or web term in plain language the first time it appears.**
  The reader is an expert in their trade and a beginner here. Undefined jargon
  is the fastest way to confirm their fear.
- **Treat every excuse as a legitimate objection with a real rebuttal.** The
  reader has 1001 reasons to postpone. Name the reason honestly, then answer
  it. Cost is the one already fully answered: 55 cents a day.
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
- Condescend to a reader who is behind, afraid, or skeptical of AI
- Use AI terminology as a credential or a flex

### Taboo phrases
- "guaranteed rankings" / "#1 on Google" / "rank #1"
- "skyrocket", "explode your traffic", "10x your leads"
- "limited time" / "act now" (manufactured urgency)
- "industry-leading", "best-in-class" (unsourceable superlatives)
- Undefined AI jargon used as decoration ("leveraging agentic LLM workflows")

### Required disclosures
- First-year pricing must state what renews and at what rate: **$199 first
  year, then $9.99/mo, plus the cost of the domain**
- Any post describing proof/preview delivery must state that directions are
  reviewed by a person before a customer sees them

## Topic Scope

- **In scope** — the five customer jobs, every post maps to exactly one:
  - **Get Found** — local search, domains, discoverability, structured data
  - **Get Customers** — conversion, intake, proof-first buying, credibility
  - **Get Paid** — pricing, packages, renewals, what's included
  - **Serve Customers** — maintenance, support, hosting, care after launch
  - **Grow and Automate** — **the destination pillar.** Automation, AI agents,
    lead automation, analytics, scheduling. This is what the studio actually
    sells; the blog should function as a genuine **resource, tutorial, and
    reference library** for it, not just a marketing surface.
- **Required content architecture**: progressive **zero-to-expert series** —
  a reader who wants to go from "afraid of AI" to genuinely capable should be
  able to follow an ordered path through the blog and get there. Standalone
  posts are fine; series are the backbone.
- **Recurring formats worth building**:
  - **The excuse/rebuttal series** — one honest objection per post
  - **Zero-to-expert automation tracks** — ordered, progressive
  - **Tutorials and reference explainers** — how a thing actually works
- **Partial scope** (only with an original, capability-backed angle): general
  small-business operations, marketing strategy, branding
- **Out of scope**: capabilities not in the registry; legal, tax, or financial
  advice; ranking or revenue guarantees

## Provenance

- Audience scope, business model, AI-fear framing, content mission, POV,
  excuse-rebuttal engine, pricing logic: **owner interview, 2026-09-04**
- Claims policy, five customer jobs, CTA doctrine: `docs/DEMAND_ENGINE_DOCTRINE.md`
- Prices and renewal rates: `backend/config/famtastic-products.json`
- Entity name and address: live Organization JSON-LD on famtasticdesigns.com
- Link/URL rules: `.site-context/SITE-LEARNINGS.md` (2026-09-04 entry)

## What changed in v2

v1 was inferred from repo evidence with no owner input. It was wrong in four
material ways, all corrected above:

1. **Audience scoped too narrow** — v1 said "owner-operator of a local service
   business," inferred from campaign manifests. A campaign audience is not the
   brand audience. FAMtastic serves any size company.
2. **Business model missing** — v1 had no concept of the entry-package →
   customer growth → expanded engagement logic that explains why $199 exists.
3. **AI fear and literacy missing** — v1 said nothing about readers being
   unfamiliar with or afraid of AI terms, which is a defining audience trait.
4. **Content mission wrong** — v1 treated "Grow and Automate" as one of five
   equal buckets. It is the destination, and the blog is meant to be a real
   automation resource/tutorial/reference library.

Also corrected: the logo is **not final** (v1 cited a placeholder as
canonical), and the `@nineoo1` YouTube channel is **intentional** (v1 flagged
it as an error).
