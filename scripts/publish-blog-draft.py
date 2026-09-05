#!/usr/bin/env python3
"""Publish one drafted blog article into production Drupal.

Why this exists: docs/playbook/RECIPES/BLOG_FACTORY.md step 6 ("Publish via
Drupal blog system") has been a manual gate since the recipe was written —
two real, SEO-checked drafts (marketing/blog/drafts/what-does-199-website-include/
and marketing/blog/drafts/proof-first-website-see-before-you-pay/) have sat
ready since 2026-08-23 with no script behind that step. This closes that gap.

Auth mechanism — read before assuming JSON:API OAuth:
  This repo's only proven write path for blog_post nodes is SSH + Drush
  `php:script` against production (backend/scripts/seed-demand-content.php,
  invoked by scripts/deploy-backend-godaddy.sh step 6). Production's public
  JSON:API endpoint returned empty/unreachable for read-only GETs when this
  script was built, and there is no service-account OAuth credential anywhere
  in this codebase for scripted content writes — the only OAuth consumer
  (`famtastic_spa` / simple_oauth password grant, frontend/src/api/drupal.js)
  is a customer-identity login flow for the client portal, not a content
  publishing credential, and fabricating a service account for it would
  violate the no-invented-credentials rule. So this script reuses the exact
  mechanism already trusted for all 64 existing posts: the same SSH target
  scripts/deploy-backend-godaddy.sh uses (FAMTASTIC_SSH_TARGET, default
  xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net), running the new
  companion script backend/scripts/publish-single-blog-post.php via
  `vendor/bin/drush php:script`. No new credential is introduced.

Usage:
    python3 scripts/publish-blog-draft.py --draft <slug>              # dry-run (default)
    python3 scripts/publish-blog-draft.py --draft <slug> --dry-run    # explicit, identical
    python3 scripts/publish-blog-draft.py --draft <slug> --confirm    # real write + publish

Flags:
    --draft <slug>   Required. Folder name under marketing/blog/drafts/<slug>/.
    --dry-run        Default behavior. Validates everything, builds the exact
                      Drupal payload, checks (read-only) whether a node with
                      this content_key already exists, and prints it all.
                      Writes nothing, contacts nothing but a read-only SSH
                      lookup.
    --confirm        Required for any real write. SCPs the payload to the
                      remote deploy tmp dir and runs the Drush publish script
                      against production. Publishes (status=1) directly —
                      there is no unpublished-draft mode, matching the task
                      contract. A second run with the same slug updates the
                      existing node instead of duplicating it (idempotent by
                      field_content_key).
    --unpublish-after-confirm
                      Test-only. Immediately deletes the node this run just
                      created/updated, via the same Drush script's delete
                      action. Used to prove the pipeline end-to-end without
                      leaving a test artifact live. Refuses to run against
                      the two real drafts (see REAL_DRAFT_SLUGS below) —
                      deleting real editorial work is never automatic.

Safety:
    - Never fabricates credentials. See the auth note above.
    - Defaults to --dry-run; --confirm is required for any write, matching
      queue-campaign-drops.py's --confirm contract.
    - Never publishes a half-filled node: every required field is validated
      BEFORE any SSH contact is made.
    - Never publishes a series orphan. This blog is series-first; a draft with
      no `series`/`series_order` in DRAFT_CLASSIFICATION fails validation
      before any SSH contact, and the remote script refuses the write too.
      See the DRAFT_CLASSIFICATION comment for the full rationale.
    - Never publishes a node with fewer fields than the corpus. All 19 fields a
      blog_post can carry are accounted for: 17 are written, and the two the
      corpus has never used (field_featured_image, field_published_date) are
      documented as unused rather than silently forgotten. See the
      series_facts() comment for the audit that established the difference.
"""

from __future__ import annotations

import json
import pathlib
import re
import subprocess
import sys
import time

REPO_ROOT = pathlib.Path(__file__).resolve().parent.parent
DRAFTS_ROOT = REPO_ROOT / "marketing/blog/drafts"
BACKEND_DIR = REPO_ROOT / "backend"
PUBLISH_SCRIPT_REL = "scripts/publish-single-blog-post.php"

SSH_TARGET_DEFAULT = "xrdj7j99xhzt@p3plzcpnl497512.prod.phx3.secureserver.net"
REMOTE_ROOT_DEFAULT = "public_html"
REMOTE_DEPLOY_BASE_DEFAULT = "deploy/famtastic-designs"

# The two drafts this session's brief names as ready-to-publish. Used only to
# refuse --unpublish-after-confirm against them (a test cleanup step must
# never delete real editorial work), never to skip validation for anyone else.
REAL_DRAFT_SLUGS = {
    "what-does-199-website-include",
    "proof-first-website-see-before-you-pay",
}

# Category + tag + SERIES assignment. The Drupal blog_categories vocabulary
# already has a fixed 5-value taxonomy (backend/config/famtastic-content-series.json
# categories list: get-found, get-customers, get-paid, serve-customers,
# grow-and-automate). Rather than guess a category for a slug this script has
# never seen, every draft must be classified explicitly here — fail loud
# instead of inventing a mapping. Add a new slug's row before publishing it.
#
# SERIES IS MANDATORY. This blog is series-first: the blog_post content type
# carries field_blog_series ("Ordered learning journey containing this post")
# and field_series_order ("Position of this post inside its series"), and
# frontend/src/pages/BlogPostPage.jsx depends on both — it filters siblings by
# matching series, sorts them by seriesOrder to render the prev/next
# `blog-series-nav`, and inserts the series as a level in the BreadcrumbList
# JSON-LD. Every one of the 80 originally-seeded posts is in a series.
#
# Between 2026-09-04 (when this script and its PHP companion were written) and
# the fix in this file, NEITHER script set either field, so all three posts
# published through this pipeline (nid 156/157/158) were silently orphaned from
# the series architecture: no series nav, and a two-level breadcrumb where every
# other post has three. A missing series is now a hard validation failure BEFORE
# any SSH contact, exactly like a missing category — this script never guesses a
# taxonomy assignment, and "no series" is not a valid answer.
#
# `series` must be the exact term name in the blog_series vocabulary (see
# KNOWN_SERIES, read from the same manifest that seeded them). `series_order`
# must be a positive integer that no *other* post in that series already
# occupies — the remote script re-checks this against live data and refuses a
# collision, because two posts sharing an order makes the prev/next nav
# ordering non-deterministic. Run --dry-run first: it prints the orders that
# series currently occupies.
#
# Naming a series that does not exist yet requires an explicit
# "new_series": True opt-in. Without it, an unrecognized name fails validation
# rather than quietly creating a duplicate term (e.g. a stray "The Website
# Lead Capture Series" alongside the real "The Website Lead-Capture Series").
DRAFT_CLASSIFICATION = {
    # Live, backfilled 2026-09-04. "What Does the $199 Website Actually
    # Include?" is a package-scope article; its nearest sibling is order 2 of
    # this series, "What Is Included in the $199 Web Basics Bundle?".
    "what-does-199-website-include": {
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Pricing", "Web Basics", "Website Packages"],
        "series": "The FAMtastic Website Packages Explained Series",
        "series_order": 9,
        # Verbatim from this draft's own brief.md "Search intent" line:
        # 'Transactional-investigation: "what does a $199 website include" /
        # "cheap website what do you get"'. Only the two queries the author
        # actually named — the seeded posts carry three, but a third invented
        # here would be exactly the guess this pipeline refuses.
        "primary_keyword": "what does a $199 website include",
        "secondary_keywords": ["cheap website what do you get"],
        "search_intent": "commercial-investigation",
    },
    # Live, backfilled 2026-09-04. The proof-first intake -> three proofs ->
    # selection -> checkout flow is how the FAMtastic packaged offer is bought,
    # which is what this series documents package by package.
    "proof-first-website-see-before-you-pay": {
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Proof-First", "Website Design", "Customer Experience"],
        "series": "The FAMtastic Website Packages Explained Series",
        "series_order": 10,
        # From this draft's brief.md: 'Investigative: "website design see before
        # you buy" / "pay after you see website design"', plus the author's own
        # phrase in the following sentence, "try-before-pay web design".
        "primary_keyword": "website design see before you buy",
        "secondary_keywords": [
            "pay after you see website design",
            "try-before-pay web design",
        ],
        "search_intent": "commercial-investigation",
    },
    # Live, backfilled 2026-09-04. Owned domain vs rented platforms is the
    # upstream "why have your own site at all" argument, which sits directly
    # in front of this series' order 1, "What Should a Small-Business Website
    # Actually Do?". Vendor-neutral strategy, not a FAMtastic package article.
    "why-running-business-on-gmail-and-linktree-costs-revenue": {
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Owned Domain", "Booking", "Small Business"],
        "series": "The Small-Business Website Strategy Series",
        "series_order": 9,
        # From this draft's brief.md: 'Investigative/comparison: "do I need a
        # website if I have Instagram" / "linktree vs website for small
        # business" / "professional email vs gmail for business"'. The Linktree
        # comparison is primary because it is the one the title and key
        # takeaway are built on.
        "primary_keyword": "linktree vs website for small business",
        "secondary_keywords": [
            "do I need a website if I have Instagram",
            "professional email vs gmail for business",
        ],
        "search_intent": "commercial-investigation",
    },
    # The six rows below are drafted but NOT published. Their local briefs,
    # structural SEO checks, categories, tags, and keywords are restored from
    # the approved platform-dependency cluster plan. They deliberately carry
    # no series/series_order: the plan calls for a new ordered arc, but its
    # final manifest facts and production taxonomy term have not been decided.
    # Publishing any of them must therefore stop at that explicit decision;
    # it must not be blocked by missing local paperwork or guessed into an
    # unrelated existing series.
    "business-email-on-your-own-domain": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 9,
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Business Email", "Add-Ons", "Domain"],
    },
    "how-local-customers-find-your-business-online": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 3,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["Local SEO", "Structured Data", "Small Business"],
    },
    "what-website-maintenance-actually-covers": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 11,
        "category": "serve-customers",
        "category_label": "Serve Customers",
        "tags": ["Maintenance", "Add-Ons", "Website Care"],
    },
    "do-you-guarantee-google-rankings": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 4,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["SEO", "Local SEO", "Honest Marketing"],
    },
    "what-happens-when-first-year-hosting-ends": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 10,
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Hosting", "Domain Renewal", "Web Basics"],
    },
    "linktree-vs-real-website-what-you-trade-away": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 2,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["Owned Domain", "SEO", "Small Business"],
        "primary_keyword": "linktree vs website",
        "secondary_keywords": [
            "linktree for business pros and cons",
            "is linktree enough for a business",
        ],
        "search_intent": "informational-comparison",
    },
    "why-link-in-bio-page-doesnt-show-up-in-google": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 1,
        # Creates the term. Only this post carries the opt-in; every sibling
        # resolves the existing name, so a typo downstream fails loudly instead
        # of silently spawning a second series.
        "new_series": True,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["SEO", "Owned Domain", "Small Business"],
        "primary_keyword": "why doesn't my linktree show up on google",
        "secondary_keywords": [
            "link in bio seo",
            "does linktree help seo",
        ],
        "search_intent": "informational",
    },
    "booking-app-commissions-cost-per-year": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 8,
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Booking", "Pricing", "Small Business"],
        "primary_keyword": "booking app commission fees",
        "secondary_keywords": [
            "salon booking app fees",
            "booking commission vs subscription",
        ],
        "search_intent": "commercial-investigation",
    },
    "who-owns-your-client-list-booking-app": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 7,
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Booking", "Owned Domain", "Small Business"],
        "primary_keyword": "who owns client data booking app",
        "secondary_keywords": [
            "salon client list ownership",
            "marketplace app customer data",
        ],
        "search_intent": "informational",
    },
    "how-much-do-you-charge-dms-costs-bookings": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 5,
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Pricing", "Customer Experience", "Website Design"],
        "primary_keyword": "answering price questions in dms",
        "secondary_keywords": [
            "should I post prices on my website",
            "customers asking how much instagram",
        ],
        "search_intent": "informational",
    },
    "website-answers-price-questions-before-asked": {
        # Series assigned 2026-09-05 (owner-approved). None of the ten existing
        # series covered the platform-dependency arc, so this one is created.
        # The arc runs symptom -> comparison -> how being found works -> honest
        # limits -> friction -> the fix -> ownership -> cost -> your own address
        # -> keeping it -> upkeep.
        "series": "The Own Your Online Presence Series",
        "series_order": 6,
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Website Design", "Customer Experience", "Lead Intake"],
        "primary_keyword": "website quote request automation small business",
        "secondary_keywords": [
            "automatic quote estimate website",
            "intake form for service business",
        ],
        "search_intent": "informational-how-to",
    },
    # ------------------------------------------------------------------
    # Orders 10-13 of "The Small-Business Website Strategy Series", drafted
    # 2026-09-04, NOT yet published. This is the editorial series the
    # ghost-town-ep1 campaign will distribute, written first under
    # docs/architecture/SERIES_FIRST_CONTENT_ORIGIN_V1.md. Publish 10 -> 13.
    #
    # Why this series rather than a new "Ghost Town" term. Order 9 of this
    # series is why-running-business-on-gmail-and-linktree-costs-revenue —
    # the general "your presence is on rented land" argument. These four are
    # the booking-app case of exactly that argument: the felt problem (10),
    # the indexing mechanism behind it (11), the page that answers it (12),
    # and how an app and an owned page coexist (13). They continue order 9
    # rather than restating it, so the prev/next nav reads as one journey.
    #
    # A new "Ghost Town" term was considered and rejected twice over. It is a
    # campaign codename, not an editorial journey, and every live series term
    # is descriptive. More concretely, this pipeline cannot create a new
    # series at all right now: series_facts() derives capabilities, FAQ keys,
    # hero visual, audience, evidence boundary and sources from the seeded
    # posts of an existing series in famtastic-content-series.json, so a
    # series with no seeded posts returns None and validation refuses it —
    # regardless of "new_series": True. See the report accompanying this work.
    # ------------------------------------------------------------------
    "why-independent-stylists-are-invisible-outside-the-app": {
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["Booking", "Small Business", "Owned Domain"],
        "series": "The Small-Business Website Strategy Series",
        "series_order": 10,
        "primary_keyword": "why independent stylists are invisible online",
        "secondary_keywords": [
            "do I need a website if I have a booking app",
            "how new clients find a hair stylist",
            "hair stylist website vs booking app",
        ],
    },
    "does-google-index-your-booking-app-profile": {
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["SEO", "Structured Data", "Owned Domain"],
        "series": "The Small-Business Website Strategy Series",
        "series_order": 11,
        "primary_keyword": "does google index booking app profiles",
        "secondary_keywords": [
            "will my marketplace listing show up in search",
            "why my business does not show up on google",
            "booking app profile search visibility",
        ],
    },
    "what-a-bookable-page-actually-needs": {
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Website Design", "Booking", "Customer Experience"],
        "series": "The Small-Business Website Strategy Series",
        "series_order": 12,
        "primary_keyword": "what to put on a bookable business page",
        "secondary_keywords": [
            "what should be on a stylist website",
            "one page business website for bookings",
            "should I put prices on my website",
        ],
    },
    "do-you-have-to-leave-the-booking-app": {
        "category": "get-customers",
        "category_label": "Get Customers",
        "tags": ["Booking", "Owned Domain", "Small Business"],
        "series": "The Small-Business Website Strategy Series",
        "series_order": 13,
        "primary_keyword": "do I need a website if I use a booking app",
        "secondary_keywords": [
            "booking app versus own website",
            "should I leave my booking app",
            "keep booking app and have a website",
        ],
    },
    "business-email-on-your-own-domain": {
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Business Email", "Add-Ons", "Domain"],
    },
    "how-local-customers-find-your-business-online": {
        # Series assigned 2026-09-05. This slug has TWO entries in this dict and
        # the later one wins at import; the first patch landed on the earlier
        # copy and silently did nothing. Patched here deliberately.
        "series": "The Own Your Online Presence Series",
        "series_order": 3,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["Local SEO", "Structured Data", "Small Business"],
    },
    "what-website-maintenance-actually-covers": {
        "category": "serve-customers",
        "category_label": "Serve Customers",
        "tags": ["Maintenance", "Add-Ons", "Website Care"],
    },
    "do-you-guarantee-google-rankings": {
        # Series assigned 2026-09-05. This slug has TWO entries in this dict and
        # the later one wins at import; the first patch landed on the earlier
        # copy and silently did nothing. Patched here deliberately.
        "series": "The Own Your Online Presence Series",
        "series_order": 4,
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["SEO", "Local SEO", "Honest Marketing"],
    },
    "what-happens-when-first-year-hosting-ends": {
        # Series assigned 2026-09-05. This slug has TWO entries in this dict and
        # the later one wins at import; the first patch landed on the earlier
        # copy and silently did nothing. Patched here deliberately.
        "series": "The Own Your Online Presence Series",
        "series_order": 10,
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Hosting", "Domain Renewal", "Web Basics"],
    },
}

# The blog_series vocabulary was seeded from this manifest by
# backend/scripts/seed-demand-content.php (term name == series "title"), so it
# is the local source of truth for "does this series already exist". Used only
# to reject a typo'd series name at validation time; the remote script still
# resolves the real term by name and is the final authority.
SERIES_MANIFEST_PATH = BACKEND_DIR / "config/famtastic-content-series.json"


def load_series_manifest() -> dict:
    try:
        return json.loads(SERIES_MANIFEST_PATH.read_text())
    except (OSError, json.JSONDecodeError) as exc:
        raise ValidationError(
            f"Could not read the series manifest at {SERIES_MANIFEST_PATH}: {exc}"
        )


def known_series_titles() -> list[str]:
    return [item["title"] for item in load_series_manifest().get("series", []) if item.get("title")]


# --- Series-derived fields (added 2026-09-04 after the second field-loss audit) ---
#
# The first version of this pipeline set 14 of the 19 fields a blog_post can
# carry. A corpus audit of all 83 published posts found FIVE fields that the
# 80 seeded posts populate and this pipeline never did:
#
#   field_seo_brief        80/83   hero image, JSON-LD Article.image, keywords
#   field_related_faqs     80/83   the on-page FAQ section + FAQPage JSON-LD
#   field_cta_link         80/83   the article's attributed next-step link
#   field_cta_text         80/83   that link's label
#   field_capability_keys  80/83   capability-registry evidence keys
#
# The three missing posts were exactly nid 156/157/158 — the three this
# pipeline published. Same root cause as the series defect: the field list was
# assembled from what a draft folder happens to provide, not from what the
# content type requires.
#
# The important discovery is that four of the five are NOT per-post editorial
# judgement at all — they are SERIES-LEVEL facts, verified across all 80 seeded
# posts against backend/config/famtastic-content-series.json:
#
#   - capabilities        identical to the series' own `capabilities`   80/80
#   - cta.label           the constant "Find the right next step"       80/80
#   - cta.href            /start?source=blog&series=<key>&article=<slug> 80/80
#   - faqs                the series' own 4 FAQ keys                    10/10 series
#   - visual, target_audience, evidence_boundary, sources
#                         one value per series                          10/10 series
#
# So they are DERIVED from the manifest here rather than re-declared per draft.
# Re-typing a series' capability list into every row would just be a new place
# for the two to drift apart — the manifest is the thing that seeded the live
# terms, so it stays the single source of truth.
#
# What is NOT derivable, and is therefore required per draft:
#   - primary_keyword / secondary_keywords. These are the post's own editorial
#     SEO truth, they differ for all 80 posts, and BlogPostPage.jsx puts them
#     straight into the Article JSON-LD `keywords`. Guessing one would be
#     exactly the invention this pipeline exists to refuse.
#
# Deliberately NOT made mandatory (see the session report):
#   - field_featured_image and field_published_date. 0/83 posts set either.
#     The hero is field_seo_brief.visual, NOT field_featured_image — the name
#     is a trap, and the image field has never been used on this bundle.
#
# The series slug is NOT derivable from the series title: "The Lead Response
# and Follow-Up Series" is keyed `lead-response-operations`, "The Ecommerce and
# Post-Purchase Series" is `commerce-customer-lifecycle`. It is an arbitrary
# manifest key, which is why it is looked up rather than slugified.

CTA_LABEL = "Find the right next step"

# Non-pillar defaults, verified against the 70 non-pillar seeded posts:
# content_template is 'how-to-guide' 70/70 and schema_types is
# ["Article", "BreadcrumbList"] 70/70. search_intent splits 60 informational /
# 10 commercial-investigation, so it defaults to the majority and a draft row
# may override it. A new post appended to an existing series is never the
# pillar (the pillar is order 1 and already exists).
DEFAULT_SEARCH_INTENT = "informational"
DEFAULT_CONTENT_TEMPLATE = "how-to-guide"
DEFAULT_SCHEMA_TYPES = ["Article", "BreadcrumbList"]
DEFAULT_REVIEW_STATUS = "editorial-review-required"  # 80/80 seeded posts.


def series_facts(series_title: str) -> dict | None:
    """Everything a new post inherits from its series, read from the manifest
    that seeded the live taxonomy. Returns None for a series the manifest does
    not know (i.e. a deliberate new_series), so the caller can demand the
    values explicitly instead."""
    manifest = load_series_manifest()
    series = next(
        (s for s in manifest.get("series", []) if s.get("title") == series_title), None
    )
    if not series:
        return None

    members = [p for p in manifest.get("posts", []) if p.get("series") == series["key"]]
    if not members:
        return None

    def unique(field: str):
        """The single value all members of this series share, or None if they
        disagree — an ambiguous field must be declared, never picked at random."""
        values = {json.dumps(p.get(field), sort_keys=True) for p in members}
        return json.loads(values.pop()) if len(values) == 1 else None

    return {
        "key": series["key"],
        "capabilities": series.get("capabilities") or [],
        "faq_keys": unique("faqs"),
        # One visual per series for 9 of 10 series. The 55-cents campaign
        # series deliberately uses four different pieces of commissioned art
        # across its eight posts, so unique() returns None there and the draft
        # row has to name the artwork — the script will not pick one.
        "visual": unique("visual"),
        "target_audience": unique("target_audience") or series.get("audience"),
        "evidence_boundary": unique("evidence_boundary"),
        "sources": unique("sources"),
    }

DEFAULT_AUTHOR_UID = 1  # fritz.medine@gmail.com — the account of record for all existing content.

ARGS = sys.argv[1:]


def flag(name: str) -> bool:
    return name in ARGS


def value_of(name: str) -> str | None:
    if name in ARGS:
        idx = ARGS.index(name)
        if idx + 1 < len(ARGS):
            return ARGS[idx + 1]
    return None


class ValidationError(Exception):
    pass


def word_count(markdown_body: str) -> int:
    stripped = re.sub(r"`[^`]*`", " ", markdown_body)
    stripped = re.sub(r"!\[[^\]]*\]\([^)]*\)", " ", stripped)
    stripped = re.sub(r"\[([^\]]*)\]\([^)]*\)", r"\1", stripped)
    stripped = re.sub(r"[#*_>-]", " ", stripped)
    return len(re.findall(r"\b[\w'-]+\b", stripped))


def markdown_to_basic_html(markdown_body: str) -> str:
    """Minimal markdown -> basic_html conversion covering exactly what the
    two known drafts use: H1/H2 headings, bold, links, and a numbered list.
    Deliberately narrow rather than pulling in a markdown dependency this
    repo doesn't already have — anything the drafts don't use is left as-is
    and will show up plainly in --dry-run output for a human to catch.
    """
    lines = markdown_body.strip("\n").split("\n")
    html_lines: list[str] = []
    in_list = None
    paragraph: list[str] = []

    def flush_paragraph():
        nonlocal paragraph
        if paragraph:
            text = " ".join(paragraph).strip()
            if text:
                html_lines.append(f"<p>{inline(text)}</p>")
            paragraph = []

    def inline(text: str) -> str:
        text = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", text)
        text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r'<a href="\2">\1</a>', text)
        return text

    bullet_re = re.compile(r"^-\s+(.*)")
    numbered_bold_re = re.compile(r"^\d+\.\s+\*\*([^*]+)\*\*\s+(.*)")
    numbered_re = re.compile(r"^\d+\.\s+(.*)")
    h1_re = re.compile(r"^#\s+(.*)")
    h2_re = re.compile(r"^##\s+(.*)")

    def close_list():
        nonlocal in_list
        if in_list == "ul":
            html_lines.append("</ul>")
        elif in_list == "ol":
            html_lines.append("</ol>")
        in_list = None

    for raw_line in lines:
        line = raw_line.rstrip()
        if not line.strip():
            flush_paragraph()
            close_list()
            continue
        h1 = h1_re.match(line)
        h2 = h2_re.match(line)
        bullet = bullet_re.match(line)
        numbered_bold = numbered_bold_re.match(line)
        numbered = numbered_re.match(line)
        if h1:
            flush_paragraph()
            close_list()
            html_lines.append(f"<h1>{inline(h1.group(1))}</h1>")
        elif h2:
            flush_paragraph()
            close_list()
            html_lines.append(f"<h2>{inline(h2.group(1))}</h2>")
        elif numbered_bold or numbered:
            flush_paragraph()
            if in_list != "ol":
                close_list()
                html_lines.append("<ol>")
                in_list = "ol"
            if numbered_bold:
                html_lines.append(f"<li><strong>{inline(numbered_bold.group(1))}</strong> {inline(numbered_bold.group(2))}</li>")
            else:
                html_lines.append(f"<li>{inline(numbered.group(1))}</li>")
        elif bullet:
            flush_paragraph()
            if in_list != "ul":
                close_list()
                html_lines.append("<ul>")
                in_list = "ul"
            html_lines.append(f"<li>{inline(bullet.group(1))}</li>")
        else:
            close_list()
            paragraph.append(line)
    flush_paragraph()
    close_list()
    return "\n".join(html_lines)


def extract_title_and_body(draft_md: str) -> tuple[str, str]:
    lines = draft_md.strip("\n").split("\n")
    if not lines or not lines[0].startswith("# "):
        raise ValidationError("draft.md must start with a single H1 title line ('# ...').")
    title = lines[0][2:].strip()
    rest = "\n".join(lines[1:]).strip("\n")
    return title, rest


def extract_key_takeaway(brief_md: str) -> str:
    match = re.search(r"##\s*Key takeaway\s*\n(.+?)(?:\n##|\Z)", brief_md, re.S)
    if not match:
        raise ValidationError("brief.md missing a '## Key takeaway' section (used for field_excerpt).")
    return " ".join(match.group(1).strip().split())


def load_draft(slug: str) -> dict:
    draft_dir = DRAFTS_ROOT / slug
    errors: list[str] = []

    if not draft_dir.is_dir():
        raise ValidationError(f"No draft folder at {draft_dir}")

    draft_path = draft_dir / "draft.md"
    brief_path = draft_dir / "brief.md"
    seo_path = draft_dir / "seo-check.json"

    for path in (draft_path, brief_path, seo_path):
        if not path.is_file():
            errors.append(f"missing required file: {path.relative_to(REPO_ROOT)}")
    if errors:
        raise ValidationError("Draft folder is incomplete:\n  " + "\n  ".join(errors))

    draft_md = draft_path.read_text()
    brief_md = brief_path.read_text()
    seo_check = json.loads(seo_path.read_text())

    if not seo_check.get("pass"):
        errors.append(
            "seo-check.json reports pass=false (or missing) — fix SEO issues "
            "before publishing, do not publish a post that failed its own check."
        )

    title, body_md = extract_title_and_body(draft_md)
    if len(body_md.strip()) < 200:
        errors.append("draft.md body is under 200 characters — looks like a stub, not a substantive draft.")

    excerpt = extract_key_takeaway(brief_md)
    if not excerpt:
        errors.append("brief.md 'Key takeaway' section is empty.")

    meta_title = seo_check.get("title")
    meta_description = seo_check.get("meta_description")
    if not meta_title:
        errors.append("seo-check.json missing 'title' (used for field_meta_title).")
    if not meta_description:
        errors.append("seo-check.json missing 'meta_description' (used for field_meta_description).")

    internal_link_count = seo_check.get("internal_link_count", 0)
    if internal_link_count < 3:
        errors.append(f"seo-check.json internal_link_count is {internal_link_count}, needs >= 3 per recipe.")

    classification = DRAFT_CLASSIFICATION.get(slug)
    if not classification:
        errors.append(
            f"No category/tag/series classification registered for slug '{slug}' in "
            "DRAFT_CLASSIFICATION (scripts/publish-blog-draft.py). Add one — "
            "this script never guesses a taxonomy assignment."
        )

    # Series is mandatory and is validated here, before any SSH contact, so an
    # unclassified draft can never reach production as an orphan. See the
    # DRAFT_CLASSIFICATION docstring for why this exists.
    series_name = None
    series_order = None
    new_series = False
    if classification:
        series_name = classification.get("series")
        series_order = classification.get("series_order")
        new_series = bool(classification.get("new_series"))
        titles = known_series_titles()
        title_list = "\n      ".join(sorted(titles))

        if not series_name:
            errors.append(
                f"No series registered for slug '{slug}'. This blog is series-first: "
                "field_blog_series and field_series_order drive the on-page series "
                "nav and the BreadcrumbList JSON-LD, and a post without them is "
                "orphaned from the whole blog architecture. Add \"series\" and "
                f"\"series_order\" to the '{slug}' row in DRAFT_CLASSIFICATION.\n"
                f"    Existing series (use one of these names exactly, or set "
                f"\"new_series\": True to create a new one):\n      {title_list}"
            )
        elif series_name not in titles and not new_series:
            errors.append(
                f"Series '{series_name}' for slug '{slug}' is not one of the existing "
                "blog_series terms. Fix the spelling, or set \"new_series\": True on "
                "the row to deliberately create a new series term.\n"
                f"    Existing series:\n      {title_list}"
            )

        if series_order is None:
            errors.append(
                f"No series_order registered for slug '{slug}'. Every post needs an "
                "explicit position inside its series — the frontend sorts siblings by "
                "it. Run --dry-run to see which orders that series already occupies."
            )
        elif not isinstance(series_order, int) or isinstance(series_order, bool) or series_order < 1:
            errors.append(
                f"series_order for slug '{slug}' must be a positive integer, got "
                f"{series_order!r}."
            )

    # --- The five fields the corpus populates and this pipeline used to drop. ---
    # Four are inherited from the series; only the keywords are per-post
    # editorial input. See the series_facts() comment block for the audit that
    # established which is which.
    seo_brief: dict = {}
    capability_keys: list = []
    related_faq_keys: list = []
    cta_href = ""
    primary_keyword = classification.get("primary_keyword") if classification else None
    secondary_keywords = classification.get("secondary_keywords") if classification else None
    if classification:
        # Checked independently of the series so a single --dry-run reports
        # every gap in the row at once, rather than one per run.
        if not isinstance(primary_keyword, str) or not primary_keyword.strip():
            errors.append(
                f"No primary_keyword registered for slug '{slug}'. Every one of the 80 "
                "seeded posts carries its own primary and secondary keywords in "
                "field_seo_brief, and BlogPostPage.jsx writes them straight into the "
                "Article JSON-LD `keywords`. This script will not invent a keyword for "
                "an article it did not write — take it from the draft's own brief.md "
                "\"Search intent\" section and add \"primary_keyword\" to the "
                f"'{slug}' row in DRAFT_CLASSIFICATION."
            )
        if (
            not isinstance(secondary_keywords, list)
            or not secondary_keywords
            or not all(isinstance(k, str) and k.strip() for k in secondary_keywords)
        ):
            errors.append(
                f"No usable secondary_keywords for slug '{slug}' (got "
                f"{secondary_keywords!r}). Provide a non-empty list of strings — the "
                "seeded posts carry three each. Same source as primary_keyword: the "
                "draft's own brief, never invented here."
            )

    # Everything else a post carries is inherited from its series, so this
    # block only runs once a series is actually named.
    if classification and series_name:
        facts = series_facts(series_name)

        if not facts:
            # A deliberate new_series has no manifest row, so none of the
            # series-level facts exist yet. Refuse rather than publish a post
            # with an empty brief, no FAQs and no CTA.
            errors.append(
                f"Series '{series_name}' has no entry in "
                f"{SERIES_MANIFEST_PATH.relative_to(REPO_ROOT)}, so this script cannot "
                "derive the capability keys, related FAQs, CTA link, hero visual, "
                "audience, evidence boundary or sources that every other post carries. "
                "Creating a brand-new series needs those decided and added to the "
                "manifest first — a post published without them is the same kind of "
                "orphan the series fix was written to prevent."
            )
        else:
            capability_keys = facts["capabilities"]
            related_faq_keys = facts["faq_keys"] or []
            cta_href = (
                f"/start?source=blog&series={facts['key']}&article={slug}"
            )

            # The hero. Its absence is what shipped nid 156/157/158 with no
            # image on the blog hub and no `image` in their Article JSON-LD.
            visual = classification.get("visual") or facts["visual"]
            if not isinstance(visual, dict) or not visual.get("src") or not visual.get("alt"):
                errors.append(
                    f"No usable hero visual for slug '{slug}'. The series "
                    f"'{series_name}' does not resolve to a single shared visual "
                    "(the 55-cents campaign series uses four different pieces of art "
                    "across its posts), so the artwork has to be named explicitly. Add "
                    '"visual": {"src": "/blog-images/<file>.webp", "alt": "..."} to the '
                    f"'{slug}' row. field_featured_image is NOT the hero — no post on "
                    "this bundle has ever used it; the hero lives in "
                    "field_seo_brief.visual."
                )
                visual = None

            if not facts["evidence_boundary"] or not facts["sources"]:
                errors.append(
                    f"Series '{series_name}' has no single shared evidence_boundary or "
                    "sources in the manifest. Both are claims-safety records; this "
                    "script will not guess one."
                )

            if not related_faq_keys:
                errors.append(
                    f"Series '{series_name}' has no single shared FAQ set in the "
                    "manifest, so this script cannot derive field_related_faqs. That "
                    "field renders the on-page FAQ section and the FAQPage JSON-LD "
                    "node; publishing without it silently drops both."
                )

            if visual and not errors:
                seo_brief = {
                    "primary_keyword": primary_keyword.strip(),
                    "secondary_keywords": [k.strip() for k in secondary_keywords],
                    "search_intent": classification.get(
                        "search_intent", DEFAULT_SEARCH_INTENT
                    ),
                    "content_template": DEFAULT_CONTENT_TEMPLATE,
                    "target_audience": facts["target_audience"],
                    "evidence_boundary": facts["evidence_boundary"],
                    # Recomputed, never copied: BlogPostPage.jsx builds the real
                    # canonical the same way, and the frontend has no /web/ prefix
                    # (see the 2026-09-04 canonical-URL defect).
                    "canonical_url": f"https://famtasticdesigns.com/blog/{slug}/",
                    "open_graph": {
                        "title": meta_title,
                        "description": meta_description,
                    },
                    "schema_types": list(DEFAULT_SCHEMA_TYPES),
                    "sources": facts["sources"],
                    "review_status": DEFAULT_REVIEW_STATUS,
                    "visual": visual,
                }

    if errors:
        raise ValidationError("Draft failed validation:\n  - " + "\n  - ".join(errors))

    computed_word_count = word_count(body_md)
    body_html = markdown_to_basic_html(body_md)

    payload = {
        "action": "publish",
        "content_key": slug,
        "slug": slug,
        "title": title,
        "body_html": body_html,
        "excerpt": excerpt[:600],
        "category": classification["category"],
        "category_label": classification["category_label"],
        "series": series_name,
        "series_order": series_order,
        # Opt-in, never inferred: the remote script refuses to create a
        # blog_series term unless this is explicitly true, so a typo can't
        # silently spawn a near-duplicate series.
        "allow_create_series": new_series,
        "tags": [{"key": t.lower().replace(" ", "-"), "label": t} for t in classification["tags"]],
        "author_uid": DEFAULT_AUTHOR_UID,
        "meta_title": meta_title,
        "meta_description": meta_description,
        "word_count": computed_word_count,
        # The five fields nid 156/157/158 shipped without. All validated above;
        # the remote script re-checks each one and refuses the write if any is
        # missing, so neither half of the pipeline can silently drop them again.
        "seo_brief": seo_brief,
        "capability_keys": capability_keys,
        "cta_text": CTA_LABEL,
        "cta_link_uri": f"internal:{cta_href}",
        "related_faq_keys": related_faq_keys,
        "status": 1,
    }
    return payload


def ssh_target() -> tuple[str, str, str]:
    import os

    return (
        os.environ.get("FAMTASTIC_SSH_TARGET", SSH_TARGET_DEFAULT),
        os.environ.get("FAMTASTIC_REMOTE_ROOT", REMOTE_ROOT_DEFAULT),
        os.environ.get("FAMTASTIC_REMOTE_DEPLOY_BASE", REMOTE_DEPLOY_BASE_DEFAULT),
    )


_REMOTE_HOME_CACHE: dict[str, str] = {}


def remote_home(target: str) -> str:
    """Resolve the remote account's absolute home directory once per run and
    cache it. scp's remote-path argument is not shell-expanded, so a literal
    "$HOME" in a scp destination fails ("No such file or directory") even
    though the same string works fine inside an `ssh ... "..."` command
    string (which IS run through a remote shell). Every absolute path below
    is built from this resolved value instead of the unexpanded literal.
    """
    if target not in _REMOTE_HOME_CACHE:
        result = subprocess.run(
            ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, "echo $HOME"],
            capture_output=True, text=True, timeout=15,
        )
        home = result.stdout.strip()
        if result.returncode != 0 or not home.startswith("/"):
            raise RuntimeError(f"Could not resolve remote $HOME: {result.stderr.strip()}")
        _REMOTE_HOME_CACHE[target] = home
    return _REMOTE_HOME_CACHE[target]


def remote_lookup_existing(content_key: str) -> dict | None:
    """Read-only existence check via Drush eval — safe to run during --dry-run."""
    target, remote_root, _ = ssh_target()
    php = (
        "$n = \\Drupal::entityTypeManager()->getStorage('node')"
        "->loadByProperties(['type' => 'blog_post', 'field_content_key' => "
        + json.dumps(content_key)
        + "]); $n = $n ? reset($n) : NULL; "
        "print $n ? json_encode(['nid' => (int) $n->id(), 'status' => (int) $n->isPublished(), "
        "'path' => '/blog/' . " + json.dumps(content_key) + "]) : 'null';"
    )
    cmd = [
        "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target,
        f"cd {remote_root} && vendor/bin/drush eval {shell_quote(php)}",
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(f"Read-only existence check failed: {result.stderr.strip()}")
    out = result.stdout.strip().splitlines()[-1] if result.stdout.strip() else "null"
    parsed = json.loads(out) if out != "null" else None
    return parsed


def remote_series_state(series_name: str) -> dict:
    """Read-only: does this blog_series term exist, and which series_order
    positions do its posts already occupy? Safe during --dry-run, and the
    answer is what an operator needs in order to choose a non-colliding
    series_order without opening Drupal. Two posts sharing an order would make
    the prev/next series nav ordering non-deterministic, so knowing the
    occupied set before the write is the whole point.
    """
    target, remote_root, _ = ssh_target()
    php = (
        "$t = \\Drupal::entityTypeManager()->getStorage('taxonomy_term')"
        "->loadByProperties(['vid' => 'blog_series', 'name' => " + json.dumps(series_name) + "]); "
        "$t = $t ? reset($t) : NULL; "
        "if (!$t) { print json_encode(['exists' => FALSE]); } else { "
        "$nodes = \\Drupal::entityTypeManager()->getStorage('node')"
        "->loadByProperties(['type' => 'blog_post', 'field_blog_series' => $t->id()]); "
        "$rows = []; foreach ($nodes as $n) { "
        "$o = $n->get('field_series_order'); "
        "$rows[] = ['nid' => (int) $n->id(), "
        "'key' => $n->get('field_content_key')->value, "
        "'order' => $o->isEmpty() ? NULL : (int) $o->value]; } "
        "usort($rows, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0)); "
        "print json_encode(['exists' => TRUE, 'tid' => (int) $t->id(), 'posts' => $rows]); }"
    )
    cmd = [
        "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target,
        f"cd {remote_root} && vendor/bin/drush eval {shell_quote(php)}",
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, timeout=45)
    if result.returncode != 0:
        raise RuntimeError(f"Series lookup failed: {result.stderr.strip()}")
    out = result.stdout.strip().splitlines()[-1] if result.stdout.strip() else ""
    if not out:
        raise RuntimeError("Series lookup returned no output.")
    return json.loads(out)


def shell_quote(s: str) -> str:
    return "'" + s.replace("'", "'\\''") + "'"


def stage_publish_script(remote_deploy_base: str, target: str) -> str:
    home = remote_home(target)
    """SCP the local backend/scripts/publish-single-blog-post.php to the
    remote deploy tmp dir and return its absolute remote path. Drush's
    `php:script` only resolves bare script names against a small set of
    default search paths (production's public_html/scripts among them) — this
    script does not live there, so it must be invoked by absolute path, the
    same way deploy-backend-godaddy.sh invokes seed-demand-content.php from
    its private release checkout rather than relying on script-path lookup.
    """
    local_script = BACKEND_DIR / PUBLISH_SCRIPT_REL
    remote_path = f"{home}/{remote_deploy_base}/tmp/publish-single-blog-post.php"
    mkdir_cmd = ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, f"mkdir -p {home}/{remote_deploy_base}/tmp"]
    subprocess.run(mkdir_cmd, capture_output=True, text=True, timeout=15, check=True)
    scp_cmd = ["scp", "-o", "BatchMode=yes", "-o", "ConnectTimeout=15", str(local_script), f"{target}:{remote_path}"]
    scp_result = subprocess.run(scp_cmd, capture_output=True, text=True, timeout=30)
    if scp_result.returncode != 0:
        raise RuntimeError(f"Failed to stage publish script on remote: {scp_result.stderr.strip()}")
    return remote_path


def remote_publish(payload: dict) -> dict:
    target, remote_root, remote_deploy_base = ssh_target()
    home = remote_home(target)
    remote_tmp = f"{home}/{remote_deploy_base}/tmp/publish-blog-draft-{payload['content_key']}-{int(time.time())}.json"
    payload_json = json.dumps(payload)

    remote_script = stage_publish_script(remote_deploy_base, target)

    write_cmd = ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, f"mkdir -p {home}/{remote_deploy_base}/tmp && cat > {remote_tmp}"]
    write_result = subprocess.run(write_cmd, input=payload_json, capture_output=True, text=True, timeout=30)
    if write_result.returncode != 0:
        raise RuntimeError(f"Failed to stage payload on remote: {write_result.stderr.strip()}")

    run_cmd = [
        "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=20", target,
        f"cd {remote_root} && vendor/bin/drush php:script {remote_script} -- {remote_tmp}",
    ]
    run_result = subprocess.run(run_cmd, capture_output=True, text=True, timeout=60)

    cleanup_cmd = ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, f"rm -f {remote_tmp} {remote_script}"]
    subprocess.run(cleanup_cmd, capture_output=True, text=True, timeout=15)

    if run_result.returncode != 0:
        raise RuntimeError(
            f"Drush publish script failed (exit {run_result.returncode}):\n"
            f"stdout: {run_result.stdout}\nstderr: {run_result.stderr}"
        )
    lines = [l for l in run_result.stdout.strip().splitlines() if l.strip()]
    try:
        return json.loads("\n".join(lines))
    except json.JSONDecodeError:
        raise RuntimeError(f"Could not parse Drush publish output as JSON:\n{run_result.stdout}")


def remote_delete(content_key: str, title: str) -> dict:
    target, remote_root, remote_deploy_base = ssh_target()
    home = remote_home(target)
    remote_tmp = f"{home}/{remote_deploy_base}/tmp/delete-blog-draft-{content_key}-{int(time.time())}.json"
    payload_json = json.dumps({"action": "delete", "content_key": content_key, "title": title})

    remote_script = stage_publish_script(remote_deploy_base, target)

    write_cmd = ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, f"mkdir -p {home}/{remote_deploy_base}/tmp && cat > {remote_tmp}"]
    write_result = subprocess.run(write_cmd, input=payload_json, capture_output=True, text=True, timeout=30)
    if write_result.returncode != 0:
        raise RuntimeError(f"Failed to stage delete payload: {write_result.stderr.strip()}")

    run_cmd = [
        "ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=20", target,
        f"cd {remote_root} && vendor/bin/drush php:script {remote_script} -- {remote_tmp}",
    ]
    run_result = subprocess.run(run_cmd, capture_output=True, text=True, timeout=60)
    cleanup_cmd = ["ssh", "-o", "BatchMode=yes", "-o", "ConnectTimeout=10", target, f"rm -f {remote_tmp} {remote_script}"]
    subprocess.run(cleanup_cmd, capture_output=True, text=True, timeout=15)

    if run_result.returncode != 0:
        raise RuntimeError(f"Drush delete failed: {run_result.stdout}\n{run_result.stderr}")
    lines = [l for l in run_result.stdout.strip().splitlines() if l.strip()]
    return json.loads("\n".join(lines))


def main() -> int:
    slug = value_of("--draft")
    if not slug:
        print(__doc__)
        print("ERROR: --draft <slug> is required.", file=sys.stderr)
        return 2

    confirm = flag("--confirm")
    unpublish_after = flag("--unpublish-after-confirm")

    try:
        payload = load_draft(slug)
    except ValidationError as exc:
        print(f"VALIDATION FAILED for '{slug}':\n{exc}", file=sys.stderr)
        return 1

    print(f"=== Validated draft: {slug} ===")
    print(json.dumps(payload, indent=2)[:4000])
    print(f"(computed word_count = {payload['word_count']})")

    print("\n=== Idempotency check (read-only) ===")
    try:
        existing = remote_lookup_existing(slug)
    except RuntimeError as exc:
        print(f"WARNING: could not check for an existing node: {exc}", file=sys.stderr)
        existing = "unknown"

    if existing == "unknown":
        print("Existing-node check inconclusive (SSH lookup failed) — see warning above.")
    elif existing:
        print(f"Existing node found: nid={existing['nid']} status={existing['status']} path={existing['path']}")
        print("A --confirm run will UPDATE this node, not duplicate it.")
    else:
        print("No existing node with this content_key — a --confirm run will CREATE one.")

    print("\n=== Series check (read-only) ===")
    print(f"Series: {payload['series']}   requested order: {payload['series_order']}")
    collision = None
    try:
        series_state = remote_series_state(payload["series"])
    except (RuntimeError, json.JSONDecodeError) as exc:
        print(f"WARNING: could not inspect the series: {exc}", file=sys.stderr)
        series_state = None

    if series_state is None:
        print("Series inspection inconclusive — see warning above.")
    elif not series_state.get("exists"):
        if payload["allow_create_series"]:
            print(
                f"NEW SERIES: '{payload['series']}' does not exist yet and this draft "
                "sets \"new_series\": True, so a --confirm run WILL CREATE a new "
                "blog_series term. Confirm that is intended and not a typo."
            )
        else:
            print(
                f"ERROR: series '{payload['series']}' does not exist and the draft did "
                "not opt in with \"new_series\": True. A --confirm run will be refused "
                "by the remote script.",
                file=sys.stderr,
            )
            return 1
    else:
        print(f"Series term exists (tid={series_state['tid']}). Current members:")
        for row in series_state["posts"]:
            mine = "  <- this post" if row["key"] == payload["content_key"] else ""
            print(f"  order {str(row['order']):>4}  nid{row['nid']}  {row['key']}{mine}")
        taken = {
            row["order"]: row
            for row in series_state["posts"]
            if row["order"] is not None and row["key"] != payload["content_key"]
        }
        if payload["series_order"] in taken:
            other = taken[payload["series_order"]]
            collision = other
            print(
                f"ERROR: series_order {payload['series_order']} is already held by "
                f"nid{other['nid']} ({other['key']}). Pick a free order — the next "
                f"unused position is {max(taken) + 1 if taken else 1}.",
                file=sys.stderr,
            )
        else:
            print(f"order {payload['series_order']} is free.")

    if collision:
        print(
            "\nREFUSED: fix the series_order collision above before publishing.",
            file=sys.stderr,
        )
        return 1

    if not confirm:
        print("\nDRY RUN complete. No write was made. Pass --confirm to publish for real.")
        return 0

    print(f"\n=== CONFIRM: publishing '{slug}' to production (status=1) ===")
    result = remote_publish(payload)
    print(json.dumps(result, indent=2))

    if unpublish_after:
        if slug in REAL_DRAFT_SLUGS:
            print(
                f"\nREFUSED: '{slug}' is a real draft (REAL_DRAFT_SLUGS) — "
                "--unpublish-after-confirm will not delete real editorial work. "
                "Remove it manually via Drupal if that is truly what you want.",
                file=sys.stderr,
            )
            return 1
        print(f"\n=== --unpublish-after-confirm: deleting test node for '{slug}' ===")
        delete_result = remote_delete(slug, payload["title"])
        print(json.dumps(delete_result, indent=2))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
