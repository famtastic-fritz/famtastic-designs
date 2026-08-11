# Demand Engine release packet — 2026-08-11

## Outcome

The FAMtastic Demand Engine is implemented as a repeatable, draft-first system. It translates evidence-backed capabilities into ordered blog series, controlled taxonomy, reusable FAQs, contextual CTAs, Drupal content, React presentation, SEO metadata, internal links, and QA evidence.

## Initial editorial release

Series: **Your Website Should Do More Than Exist**

1. What Should a Small-Business Website Actually Do? (pillar)
2. How a Website Turns a Visitor Into a Real Lead
3. What Should Happen After a Website Lead Arrives?
4. What Happens After an Online Purchase?
5. How a Customer Portal Helps a Small Business
6. How Automation Prevents Lost Opportunities
7. The Website Numbers a Small Business Should Watch
8. When Is an AI Website Agent Actually Useful?

The release also contains seven canonical FAQs, five customer-job categories, fourteen controlled tags, capability provenance, search intent, reader problem, promised outcome, evidence boundary, contextual CTA, internal-link map, metadata, and sequence for every article.

## Approval state

- Broad publication: not approved; every generated node is a Drupal draft.
- Live price changes: not approved and not changed.
- Promotional sends: not approved and not sent.
- Recurring terms, legal promises, advertising spend, and unsupported proof upgrades: untouched.

Publication requires review of the release as a whole, explicit approval, a manifest status change, and the broad-publication flag. The seed refuses to publish from an article status alone.

## Local proof

- Manifest validation: 1 series, 8 posts, 7 FAQs, 5 categories, 14 tags.
- First Drupal seed: 25 terms, 7 FAQs, and 8 posts created.
- Second Drupal seed: zero new records; the same 25 terms, 7 FAQs, and 8 posts updated.
- Draft safety: all 15 generated nodes verified unpublished after QA.
- Production frontend build: passed with Vite 8.2.0.
- PHP syntax: seed script passed.
- Skill validation: repository and installed Codex copies passed the official validator.
- Shared installation: repository-owned skill plus 31 selected specialists installed for Codex, Claude, and Shay from pinned commits.

## Rendered browser proof

At a mobile viewport request of 390×844 (375 CSS-pixel layout viewport):

- All 8 cards and 6 category controls rendered.
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
- Shared skill installer: `scripts/install-demand-agent-skills.sh`
- Skill: `agent-skills/famtastic-demand-engine/`

## Next gate

The system is complete through publication preparation. The next external action is an editorial review and explicit broad-publication approval. After approval, update the selected manifest item statuses and `approval.broad_publish_approved`, rerun validation and seed in the intended environment, clear caches, deploy the frontend, smoke-test public URLs, and monitor content/CTA events.
