# RECIPE: Blog Factory

**Outcome**: A repeatable pipeline that ships 2 SEO-checked, fact-grounded posts per week feeding campaigns and AI-citation visibility.
**Trigger**: Weekly content slot; or campaign needs a supporting article (NEW_PRODUCT step 8).
**Owner**: Content Engine / CMO agent (hire pending)
**Grounded in**: 64-article demand library live since 2026-08-11 (`build-demand-library.py`, `backend/config/famtastic-content-series.json`), SEO QA contracts in `docs/marketing/SEO-DISCOVERY-QA-AGENT-CONTRACT.md`, editorial audit `55_CENT_SERIES_EDITORIAL_AUDIT_2026-08-11.md`.

## Steps

| # | Step | Owner | Definition of done | Evidence | Status |
|---|------|-------|--------------------|----------|--------|
| 1 | Pick topic from demand map | Content Engine | Topic tied to: campaign need OR keyword gap in existing cluster; not duplicating the 64 | Line in content log w/ rationale | ✅ DONE 2026-08-23 — two topics chosen for 55¢ campaign support, verified non-duplicative against all 80 series titles: (1) what-does-199-website-include (objection handling), (2) proof-first-website-see-before-you-pay (positioning differentiator). Briefs committed under `marketing/blog/drafts/<slug>/`. |
| 2 | Brief before writing | Content Engine | Target reader, search intent, key takeaway, internal links (≥3 to services/packages), evidence list | Brief committed with draft | ✅ DONE 2026-08-23 — both briefs carry reader/intent/takeaway/links/evidence; claims policy set to scope-statements-only. |
| 3 | Draft w/ real substance | Content Engine | Answers intent in first screen; original explanation/examples — no filler rewrites of existing 64 | Draft PR/file | ✅ DONE 2026-08-23 — drafts at `marketing/blog/drafts/<slug>/draft.md`; answer-first openings; zero stats invented; renewal terms disclosed plainly. |
| 4 | Fact-check claims | Content Engine | Every stat/claim has source link verified live | Source list in brief | ✅ DONE 2026-08-23 — no statistics used anywhere; every scope statement sourced to live package pages listed in each brief's evidence list. Nothing to cut. |
| 5 | SEO + GEO check | Content Engine | Title/meta/headers per SEO contract; question-formatted sections for AI citations; schema present | Checklist output | ✅ DONE 2026-08-23 — machine-readable `seo-check.json` per post (titles 42/55 chars, metas 155/137, single H1, ≥3 internal links, FAQPage+BlogPosting recommended, GEO answer-first sections tagged). All checks pass. |
| 6 | Publish via Drupal blog system | Content Engine | URL live, sitemap includes it, menu order honored, renders on mobile | Prod URL + sitemap grep | Script exists: `scripts/publish-blog-draft.py --draft <slug> [--dry-run\|--confirm]`. Proven end-to-end against production with a throwaway self-test node (create → idempotent update → delete). GATE→☐ still applies to the two real drafts — publishing them live is a human decision, not automatic. |
| 7 | Measure at day 7 and day 30 | CEO | GA4 visits, query coverage noted in content log | Metrics line in weekly report | ☐ |

## Rules
- The 64-article library is inventory, not quota: link to it, don't paraphrase it into new posts.
- One post that answers a real buyer question beats five keyword stuffed ones.

## Failure paths

| Where | If it fails | Fallback |
|---|---|---|
| Fact-check fails | Claim unverifiable | Cut the claim; never "close enough" |
| Week slot missed | Pipeline stalled | Report BLOCKED with reason in standup; never silently skip |

## Post inventory (board-vs-disk reconciliation, 2026-08-24 CEO heartbeat)

| Slug | Steps 1–5 state | Evidence check |
|---|---|---|
| `what-does-199-website-include` | ✅ steps 1–5 DONE | 374-word draft; brief + seo-check.json verified 2026-08-23 |
| `proof-first-website-see-before-you-pay` | ✅ steps 1–5 DONE | 311-word draft; brief + seo-check.json verified 2026-08-23 |
| `business-email-on-your-own-domain` | ◐ steps 1–2 SKELETON only | 90-word template stub; brief is boilerplate ("defined work with a plain boundary"); **steps 3–5 NOT done** |
| `how-local-customers-find-your-business-online` | ◐ steps 1–2 SKELETON only | 99-word template stub; same defect |
| `what-website-maintenance-actually-covers` | ◐ steps 1–2 SKELETON only | 105-word template stub; same defect |
| `do-you-guarantee-google-rankings` | ☐ topic staged | empty dir only (untracked), created 2026-08-24 01:02 |
| `what-happens-when-first-year-hosting-ends` | ☐ topic staged | empty dir only (untracked), created 2026-08-24 01:02 |

The three wave-1-supporting stubs came from the 2026-08-23 launch session (commit `5cb1759e`) and were over-counted as "blog drafts ×5" in the 05:03Z heartbeat verification. They pass seo-check.json schema shape but fail step 3's definition of done (substantive draft). Content Engine must rebuild them through steps 3–5 before any publish staging.

## Change log
- 2026-09-04 — Step 6 gap closed: `scripts/publish-blog-draft.py` + companion
  `backend/scripts/publish-single-blog-post.php` validate a draft folder
  against every required blog_post field, fail loud on anything missing
  (confirmed against `business-email-on-your-own-domain`, a known-incomplete
  stub, which correctly refuses), and publish via SSH + the same Drush
  mechanism already used for the 64-article seed — no new credential.
  Idempotent by `field_content_key`. Proven live against production with a
  throwaway self-test node (create, then an in-place update on a second run,
  then delete) — nothing was left behind. The two real drafts pass validation
  and dry-run cleanly but were deliberately NOT published; that decision is
  left to Fritz. See `docs/CAPABILITY_REGISTRY.md` for the evidence entry.
- 2026-08-22 — Created; converts one-time library build into an ongoing factory.
- 2026-08-23 — Content Engine hired; steps 1–5 complete for first two posts (`marketing/blog/drafts/what-does-199-website-include/`, `proof-first-website-see-before-you-pay/`). Step 6 publish remains GATE→☐ per recipe (first 3 factory posts need Fritz); step 7 measurement pending publish.
- 2026-08-24 (heartbeat) — Board-vs-disk audit: added Post Inventory table. Found 3 additional draft kits from launch session `5cb1759e` that fail step 3 DoD (90–105-word boilerplate stubs) and 2 empty topic dirs; recorded as ◐/☐, never counted toward "2 posts/week". Dispatch hold still honored — rebuild queued for @fam-content-engine when spawns resume.
