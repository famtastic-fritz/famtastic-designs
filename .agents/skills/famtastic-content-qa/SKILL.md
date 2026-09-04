# FAMtastic Content QA

Checks that every link in every published blog post actually resolves, live —
does not assume, does not trust the author, checks mechanically.

## Why this exists

One session (2026-09-04) found three separate live-broken-link incidents by
hand: a campaign post linking to a blog article that was never written, a
tracked-link base URL (`/onboarding`) that was never a real route and had
been 404ing behind every published post in a whole campaign, and a
Drupal-backend-route-prefix bug (`/web/...`) copied into every blog draft
written that day. None were caught before a human happened to click through.
See `.site-context/SITE-LEARNINGS.md` (2026-09-04 entry) for the full story.

## When to invoke this

- Before publishing any new blog post — run it against just that slug first.
- After any campaign or blog-content session that touched internal links.
- Periodically against the whole site, since old posts can rot (a linked page
  gets renamed/removed) even when nothing about the post itself changed.
- Whenever a human reports "I clicked a link and it 404'd" — confirm scope
  (is it just that one link, or a systemic bug like `/web/` was) before
  fixing just the one instance.

## How to run it

```bash
# Check every published post (read-only, no credential needed)
python3 scripts/qa-content-links.py

# Check just one post before/after publishing it
python3 scripts/qa-content-links.py --slug why-running-business-on-gmail-and-linktree-costs-revenue

# Machine-readable output for piping into a report or another script
python3 scripts/qa-content-links.py --json
```

Exit code 0 = clean. Exit code 1 = at least one broken link found, printed
with the exact broken URL, its HTTP status, and which post it's in.

## What it does NOT do

- It does not fix anything — read-only, report-only.
- It does not check content quality, SEO, or claims accuracy — see the
  `blog-seo-check` / `blog-factcheck` skills for those.
- It does not run on a schedule yet. If a post publishes with a bad link
  between manual runs, this will not catch it automatically — that's a known
  gap, not a false sense of safety this skill provides.

## After finding a broken link

1. Confirm the real correct destination resolves live before proposing a fix
   — never guess a replacement URL without checking it yourself.
2. If the broken link is a source file (a `marketing/blog/drafts/*/draft.md`),
   fix it there first, then republish via `scripts/publish-blog-draft.py --confirm`
   so the fix is in source control, not just live-patched.
3. If the broken link is in a post with no source draft (e.g. the original
   2026-08-11 bulk-seeded posts), fix it live via `drush eval` against the
   node's `body` field directly (see `.site-context/SITE-LEARNINGS.md` for the
   exact pattern used), AND fix the same string in whatever source config fed
   the original seed (`backend/config/famtastic-content-series.json`,
   `scripts/build-demand-library.py`) so a future re-seed can't reintroduce it.
4. Re-run this script against the affected post(s) to confirm the fix,
   before considering the task done.

## Known gap — not yet built

This is a manually-invoked script. Making it a scheduled/automatic gate (a
cron job, a CI check before deploy, or a step surfaced somewhere reachable
from a phone on the actual admin surface rather than a CLI-only report) is
explicitly deferred — see `plans/social-posting-capabilities/plan.md` at the
FAMtastic root for the open task.
