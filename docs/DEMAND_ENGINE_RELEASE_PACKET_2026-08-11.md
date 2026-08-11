# Demand Engine release packet — 2026-08-11

## Outcome

The FAMtastic Demand Engine is implemented as a repeatable, approval-gated system. It translates evidence-backed capabilities into ordered blog series, controlled taxonomy, reusable FAQs, contextual CTAs, Drupal content, React presentation, SEO metadata, internal links, and QA evidence.

## Editorial library

The initial eight-topic pilot has been expanded into eight complete pillar-and-spoke series with 64 full articles:

1. Small-Business Website Strategy
2. Website Lead Capture
3. Lead Response and Follow-Up
4. Ecommerce and Post-Purchase
5. Customer Portal Experience
6. Small-Business Automation
7. Website Analytics and Decisions
8. AI Website Agents

Each series contains one pillar and seven focused supporting articles. The library contains 67,100 validated article words, 32 canonical FAQs, five customer-job categories, fourteen controlled tags, unique primary keywords, secondary keywords, search intent, content template, audience, reader problem, promised outcome, evidence boundary, contextual CTA, bidirectional internal-link plans, source records, Open Graph metadata, canonical URLs, and Article/Breadcrumb schema declarations. The full inventory is in `docs/DEMAND_LIBRARY_INVENTORY_2026-08-11.md`.

## Approval state

- Broad publication: explicitly approved by Fritz on 2026-08-11 for all 64
  articles and 32 supporting FAQs.
- Live price changes: not approved and not changed.
- Promotional sends: not approved and not sent.
- Recurring terms, legal promises, advertising spend, and unsupported proof upgrades: untouched.

The manifest and every included item now record the approval. The seed still
refuses to publish from an item status alone, preserving the two-part gate for
future libraries.

## Visual system

- Eight original series visuals were generated with the built-in GPT Image
  workflow in a consistent black, charcoal, white, and FAMtastic-lime system.
- Assets were optimized to 1,600-pixel WebP files; the complete set is roughly
  600 KB rather than shipping the original multi-megabyte PNG sources.
- Thirty-two articles use visuals selectively. Each visible figure carries
  descriptive alternative text, a caption, and the repository-owned FAMtastic
  SVG mark. Illustrated cards use empty alt text to avoid repeating the linked
  title to screen-reader users.

## Local proof

- Manifest validation: 8 series, 64 posts, 32 FAQs, 5 categories, 14 tags.
- Article depth: 67,100 total words; 1,038-1,059 words per article.
- Upgrade seed: 56 posts created and 8 pilot posts upgraded in place.
- Second Drupal seed: zero new posts; all 64 records updated in place.
- Publication safety: the two-part item and library approval gate was verified
  locally before all 64 managed articles and 32 FAQs were approved.
- Production frontend build: passed with Vite 8.2.0.
- PHP syntax: seed script passed.
- Skill validation: repository and installed Codex copies passed the official validator.
- Shared installation: repository-owned skill plus 31 selected specialists installed for Codex, Claude, and Shay from pinned commits.

## Rendered browser proof

At a mobile viewport request of 390×844 (375 CSS-pixel layout viewport):

- All series, cards, category controls, and the mobile series selector rendered.
- Hub body width equaled viewport width; no horizontal document overflow.
- Pillar article body width equaled viewport width; no horizontal document overflow.
- Contextual CTA rendered as “Find out what your website needs.”
- Series navigation, one related FAQ, canonical URL, and one structured-data block rendered.

The local fixture lacks unrelated service/package content types and the menu-items endpoint, so those existing clients emitted fallback warnings. The demand-series and article endpoints rendered successfully; no article UI error was observed.

## Operating files

- Doctrine: `docs/DEMAND_ENGINE_DOCTRINE.md`
- Agent context: `.claude/product-marketing-context.md`
- Canonical manifest: `backend/config/famtastic-content-series.json`
- Drupal seed: `backend/scripts/seed-demand-content.php`
- Validator: `scripts/validate-demand-content.py`
- Library builder: `scripts/build-demand-library.py`
- Inventory and scorecard: `scripts/audit-demand-library.py`
- Shared skill installer: `scripts/install-demand-agent-skills.sh`
- Skill: `agent-skills/famtastic-demand-engine/`

## Next gate

Publication is approved. Deploy the exact committed release, seed Drupal,
regenerate the sitemap and route-specific shells, smoke-test representative
visual and text-only articles on mobile and desktop, and monitor crawl,
content-view, series-navigation, and CTA events. Price changes and promotional
sends remain separate approval decisions.
