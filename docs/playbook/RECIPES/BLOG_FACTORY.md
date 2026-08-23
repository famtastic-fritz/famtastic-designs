# RECIPE: Blog Factory

**Outcome**: A repeatable pipeline that ships 2 SEO-checked, fact-grounded posts per week feeding campaigns and AI-citation visibility.
**Trigger**: Weekly content slot; or campaign needs a supporting article (NEW_PRODUCT step 8).
**Owner**: Content Engine / CMO agent (hire pending)
**Grounded in**: 64-article demand library live since 2026-08-11 (`build-demand-library.py`, `backend/config/famtastic-content-series.json`), SEO QA contracts in `docs/marketing/SEO-DISCOVERY-QA-AGENT-CONTRACT.md`, editorial audit `55_CENT_SERIES_EDITORIAL_AUDIT_2026-08-11.md`.

## Steps

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| 1 | Pick topic from demand map | Content Engine | Topic tied to: campaign need OR keyword gap in existing cluster; not duplicating the 64 | Line in content log w/ rationale | ☐ |
| 2 | Brief before writing | Content Engine | Target reader, search intent, key takeaway, internal links (≥3 to services/packages), evidence list | Brief committed with draft | ☐ |
| 3 | Draft w/ real substance | Content Engine | Answers intent in first screen; original explanation/examples — no filler rewrites of existing 64 | Draft PR/file | ☐ |
| 4 | Fact-check claims | Content Engine | Every stat/claim has source link verified live | Source list in brief | ☐ |
| 5 | SEO + GEO check | Content Engine | Title/meta/headers per SEO contract; question-formatted sections for AI citations; schema present | Checklist output | ☐ |
| 6 | Publish via Drupal blog system | Content Engine | URL live, sitemap includes it, menu order honored, renders on mobile | Prod URL + sitemap grep | GATE→☐ (first 3 posts) |
| 7 | Measure at day 7 and day 30 | CEO | GA4 visits, query coverage noted in content log | Metrics line in weekly report | ☐ |

## Rules
- The 64-article library is inventory, not quota: link to it, don't paraphrase it into new posts.
- One post that answers a real buyer question beats five keyword stuffed ones.

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Fact-check fails | Claim unverifiable | Cut the claim; never "close enough" |
| Week slot missed | Pipeline stalled | Report BLOCKED with reason in standup; never silently skip |

## Change log
- 2026-08-22 — Created; converts one-time library build into an ongoing factory.
