# FAMtastic demand-engine doctrine

## Purpose

FAMtastic Designs creates demand by teaching businesses what a connected digital system can do, proving the capability through its own operations, and offering the smallest useful next step. Content must help first and sell second.

## Governing model

```text
Proven capability
  → customer problem
  → useful explanation or demonstration
  → related content series
  → relevant CTA
  → assessment, configured purchase, or scoped conversation
  → delivery and proof
  → support, retention, and contextual growth
```

## Sources of truth

- Capability claims: `docs/CAPABILITY_REGISTRY.md`
- Strategic capability map: `docs/CURRENT_CAPABILITIES.md`
- Revenue translation: `docs/CAPABILITY_TO_REVENUE_STRATEGY.md`
- Products and terms: `backend/config/famtastic-products.json` and `backend/config/famtastic-deal-terms.json`
- Content series: `backend/config/famtastic-content-series.json`
- Customer and operational architecture: `docs/AGENT_OPERATING_CONTRACT.md`

## Portfolio architecture

Market through five customer jobs: Get Found, Get Customers, Get Paid, Serve Customers, and Grow and Automate. These are customer-facing categories, not claims that every capability is a separate product.

## Series-first publishing

A strong topic becomes a pillar-and-spoke series when the reader must understand more than one decision. Each series needs one durable thesis, one pillar article, ordered supporting articles, a controlled category and tag set, an internal-link map, reader-stage CTAs, canonical FAQs, a proof boundary, and a measurement plan.

Standalone posts are allowed for time-sensitive updates, announcements, or narrow questions that do not deserve a cluster.

## Draft-first rule

New generated content defaults to unpublished Drupal drafts. Draft creation, local QA, and test-environment validation may run autonomously. Broad publication remains an explicit approval gate until Fritz approves a proven auto-publishing policy.

## Pricing doctrine

- Explain outcomes and scope before price.
- Use fixed prices for bounded work.
- Use paid discovery when the correct solution cannot be known in advance.
- Use recurring billing only for continuing service or access.
- Use usage pricing where cost and customer value scale with consumption.
- Keep complex or risky work behind scope review.
- Treat generated prices as recommendations until approved in the canonical product files.
- Never route every website request to Web Basics merely because a campaign mentions $199.

## CTA doctrine

The CTA is the next useful action, not a generic request for contact: awareness readers learn or assess; consideration readers compare, receive a recommendation, see proof, or review a workflow; decision readers purchase an eligible configured offer or start a scoped request; customers get support, documentation, relevant additions, renewal help, or referral options.

Every CTA identifies its content source, destination, lifecycle stage, analytics event, and approval state.

## Taxonomy doctrine

- Category: one broad primary customer job.
- Series: one ordered learning journey.
- Tag: a controlled reusable topic.
- FAQ category: the customer's question domain.
- Product and capability references: stable machine keys, not free-form labels.

Taxonomy changes require deduplication, singular/plural normalization, and review for overlap.

## FAQ doctrine

FAQs answer genuine questions clearly and may appear in the public FAQ hub, relevant articles, product explanations, and entitled portal learning surfaces. Reuse canonical answers where possible. FAQPage schema is applied only when the visible page meets eligibility rules; no rich-result promise is made.

## Evidence and claims

Every substantive claim is supported by FAMtastic's capability registry and proof, sourced to an authoritative external reference, or clearly labeled as a recommendation, interpretation, or future possibility. Do not invent statistics, testimonials, customer outcomes, urgency, scarcity, or competitive claims.

## Approval gates

Explicit Fritz approval remains required for live prices and discount policy, recurring charges, contractual promises and final legal wording, real promotional sends, advertising spend, broad content publication, and upgrades to provider- or production-proof classifications. The engine completes the release package before stopping at a gate.

## Measurement

Track article views, series progress, FAQ expansions, CTA clicks, assessment starts and completions, lead submissions, account creation, checkout starts and completions, attributed purchases, and portal recommendation actions. Report conversion paths by series, article, CTA, category, and acquisition source rather than optimizing pageviews alone.

## Maintenance

Review content when product terms, capability proof, regulations, platform behavior, or customer questions change. Change publication dates only after substantive updates. Archive or redirect content that is no longer accurate.
