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
    },
    # The five rows below are drafted but NOT published. They deliberately
    # carry no series/series_order: nobody has decided where they belong, and
    # this script's contract is to fail loud rather than pick one. Publishing
    # any of them stops at validation with a message naming the slug and
    # listing the real series names. Decide, add the two keys, then publish.
    "business-email-on-your-own-domain": {
        "category": "get-paid",
        "category_label": "Get Paid",
        "tags": ["Business Email", "Add-Ons", "Domain"],
    },
    "how-local-customers-find-your-business-online": {
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
        "category": "get-found",
        "category_label": "Get Found",
        "tags": ["SEO", "Local SEO", "Honest Marketing"],
    },
    "what-happens-when-first-year-hosting-ends": {
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


def known_series_titles() -> list[str]:
    try:
        manifest = json.loads(SERIES_MANIFEST_PATH.read_text())
    except (OSError, json.JSONDecodeError) as exc:
        raise ValidationError(
            f"Could not read the series manifest at {SERIES_MANIFEST_PATH}: {exc}"
        )
    return [item["title"] for item in manifest.get("series", []) if item.get("title")]

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
