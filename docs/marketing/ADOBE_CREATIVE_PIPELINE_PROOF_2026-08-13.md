# Adobe-enabled creative production pipeline

## Purpose

This pipeline turns a campaign or customer outcome into repeatable, branded
creative without making Adobe the source of truth for marketing operations.
FAMtastic owns the brief, approved claims, CTA, assets, review state, publishing,
UTMs, analytics, and evidence. Adobe is the premium generation and finishing
layer.

## Canonical flow

1. Select an approved offer, capability, article, or customer outcome.
2. Generate a structured brief: audience, problem, promise, evidence, CTA,
   channel, aspect ratio, and brand constraints.
3. Produce copy, script, storyboard, shot list, alt text, metadata, and UTM plan.
4. Generate or finish visual ingredients in Firefly, Express, Photoshop,
   Premiere, or Acrobat.
5. Export deterministic variants and store them beside the campaign manifest.
6. Run content, logic, brand, accessibility, link, and technical-media QA.
7. Require explicit content, media, and publish approvals.
8. Publish through the social provider layer; never through the creative tool.
9. Record provider IDs, URLs, screenshots, delivery state, and analytics.
10. Feed results and lessons back into the capability and campaign records.

## Three proof scenarios

### 1. Offer launch: 55 cents a day

Proves a multi-format acquisition campaign. This scenario already has local
Facebook, Instagram, Story/Reel, and MP4 evidence, so its current classification
is **locally proven**. Adobe can improve photoreal source scenes and finishing;
Remotion remains the repeatable compositor.

### 2. Service education: AI site agent

Proves that a capability can become a teaching article, carousel, FAQ graphic,
captioned short, and CTA—not merely a product listing. The contract is proven;
scenario-specific assets remain intentionally pending production and approval.

### 3. Customer retention: growth review

Proves post-sale value: analytics and project outcomes become a branded PDF,
portal summary, case-study asset, short recap, and relevant next-step offer. The
contract is proven; test-customer data and assets must be supplied through a
sanitized fixture before visual production.

## Evidence classifications

- `contract_proven_assets_pending`: required inputs, outputs, checks, channels,
  and CTA are machine-readable and validated; finished scenario assets do not
  yet exist.
- `locally_proven`: the contract passes and all declared local proof assets exist.
- `provider_test_proven`: a separately authorized Adobe API or UI test produced
  and retained a provider artifact plus execution evidence.
- `publish_proven`: approved content was scheduled, published, URL-verified, and
  measured through the distribution layer.

## Safety and access boundary

The local proof consumes no credits and publishes nothing. Firefly web use is a
manual-assisted provider step until Adobe grants Firefly Services or Express
Embed/API access. Credentials belong in environment configuration, never in the
manifest or repository. Each external generation and each public publication
is recorded as a separate authorized run.

## Run the proof

```bash
python3 scripts/prove-adobe-marketing-pipeline.py
```

Canonical scenario data:
`marketing/adobe-pipeline/use-cases.json`

Latest machine-readable evidence:
`marketing/adobe-pipeline/evidence/latest.json`
