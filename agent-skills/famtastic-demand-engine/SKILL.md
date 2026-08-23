---
name: famtastic-demand-engine
description: Turn FAMtastic Designs' proven capabilities into evidence-led content series, blog drafts, FAQs, taxonomy, contextual calls to action, offer explanations, pricing recommendations, Drupal content, SEO metadata, internal links, and QA evidence. Use for FAMtastic marketing strategy, demand creation, editorial calendars, blog clusters, product or service positioning, CTA design, pricing analysis, content publishing preparation, and content-performance improvement.
---

# FAMtastic Demand Engine

Create demand from capabilities FAMtastic can prove. Keep live business changes behind the approved gates.

## Required context

Read these repository sources before work:

1. `AGENTS.md`
2. `docs/AGENT_OPERATING_CONTRACT.md`
3. `docs/CAPABILITY_REGISTRY.md`
4. `docs/CURRENT_CAPABILITIES.md`
5. `docs/CAPABILITY_TO_REVENUE_STRATEGY.md`
6. `docs/DEMAND_ENGINE_DOCTRINE.md`
7. `backend/config/famtastic-content-series.json`

Read `references/content-contract.md` before authoring or changing a series. Read `references/approval-gates.md` before any external write, publication, price change, campaign send, or Commerce mutation.

## Supporting skills

Read `.agents/product-marketing.md` before starting a marketing task. Use the
project-shared `blog-*`, `seo-*`, `content-strategy`, `copywriting`,
`pricing-strategy`, `product-marketing-context`, `free-tool-strategy`,
`email-sequence`, `analytics-tracking`, and `launch-strategy` skills as
specialist references. The current shared marketing core additionally provides
`product-marketing`, `cro`, `signup`, `onboarding`, `popups`, `revops`,
`ad-creative`, `marketing-ideas`, `marketing-loops`, `sales-enablement`,
`ai-seo`, `analytics`, `ab-testing`, `social`, `site-architecture`, `schema`,
and `offers`. Load the narrowest applicable specialist; this skill and the
repository doctrine override generic advice.

## Workflow

1. Select only capabilities supported by `docs/CAPABILITY_REGISTRY.md`.
2. Translate the capability into a customer problem, outcome, audience, proof, and safe claim boundary.
3. Build a pillar-and-spoke series. Give every post one search intent, one reader job, one primary CTA, relevant FAQs, tags, and internal links.
4. Distinguish demand creation from demand capture: create demand with original lessons, demonstrations, opinions, and proof; capture demand with direct answers to real buyer questions.
5. Draft in the canonical content manifest. Default every new item to `draft`.
6. Validate with `python3 scripts/validate-demand-content.py`.
7. Seed Drupal with `drush php:script backend/scripts/seed-demand-content.php` only in the intended environment. Seeding must remain idempotent and preserve the manifest status.
8. Run frontend build and applicable automated/browser checks.
9. Produce evidence separating locally proven, provider-proven, production smoke-tested, approval-gated, and blocked behavior.
10. Update doctrine, learnings, and changelog when the reusable process changes.

## Non-negotiable rules

- Never fabricate statistics, testimonials, results, customer quotes, scarcity, or proof.
- Never market an implementation-level capability as production-proven.
- Never force every website lead into the $199 Web Basics Bundle.
- Never publish a generic CTA when a more relevant next step exists.
- Never create an isolated post when a useful series or internal-link relationship exists.
- Never duplicate near-identical tags or categories.
- Never treat FAQ schema as a promise of a Google rich result.
- Never automatically change live prices, recurring charges, customer promises, legal language, real campaign recipients, ad spend, or broad publication state.
- Keep transactional and promotional communication separate.
- Keep Drupal as the content and operational source of truth; React remains the branded presentation layer.

## Completion contract

A content item is complete only when it has capability and evidence provenance; audience, problem, intent, and outcome; series, category, controlled tags, and sequence; unique slug, title, excerpt, body, and metadata; one primary CTA with a valid destination and lifecycle stage; useful FAQs; internal links; structured-data eligibility notes; factual review; mobile-readable presentation; analytics coverage; and an explicit publication state.

Run `agent-skills/famtastic-demand-engine/scripts/run-demand-checks.sh` before reporting completion.
