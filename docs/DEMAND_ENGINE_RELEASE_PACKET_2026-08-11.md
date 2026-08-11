# Demand Engine release packet — 2026-08-11

## Outcome

The FAMtastic Demand Engine is implemented as a repeatable, draft-first system. It translates evidence-backed capabilities into ordered blog series, controlled taxonomy, reusable FAQs, contextual CTAs, Drupal content, React presentation, SEO metadata, internal links, and QA evidence.

## Editorial library

The initial eight-topic pilot has been expanded into eight complete pillar-and-spoke series with 64 full article drafts:

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

- Broad publication: not approved; every generated node is a Drupal draft.
- Live price changes: not approved and not changed.
- Promotional sends: not approved and not sent.
- Recurring terms, legal promises, advertising spend, and unsupported proof upgrades: untouched.

Publication requires review of the release as a whole, explicit approval, a manifest status change, and the broad-publication flag. The seed refuses to publish from an article status alone.

## Local proof

- Manifest validation: 8 series, 64 posts, 32 FAQs, 5 categories, 14 tags.
- Article depth: 67,100 total words; 1,038-1,059 words per draft.
- Upgrade seed: 56 posts created and 8 pilot posts upgraded in place.
- Second Drupal seed: zero new posts; all 64 records updated in place.
- Draft safety: all 64 managed blog records verified unpublished after QA.
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

The system is complete through publication preparation. The next external action is staged editorial review and explicit publication approval by series or article. Do not publish all 64 at once merely because the drafts exist. After approval, update only selected item statuses and the controlled publication gate, rerun validation and seed in the intended environment, clear caches, deploy the frontend, smoke-test public URLs, and monitor content/CTA events.
