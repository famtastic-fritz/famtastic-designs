# Blog drafts

Ready-to-publish posts for the empty blog. Every factual claim traces back to
content already in this repository — the package prices and inclusions in
`famtastic_pipeline.settings.yml` and `seed-storefront.php`, and the FAQ answers
seeded on the site. No testimonials, statistics, or client names are invented.

## Publishing

Create a `blog_post` node per file and map the front matter:

| Front matter | Drupal field |
|---|---|
| `title` | `title` |
| `summary` | `field_summary` |
| body (below the `---`) | `body` |
| `slug` | URL alias, as `/blog/<slug>` |

`BlogPostPage` renders the title itself, so the body has no H1 — keep it that
way. Per-post metadata and `BlogPosting` structured data are applied
automatically from `title` and `summary` once the node exists.

## Verify before publishing

These are public claims by a real business. Check each one against what you
actually deliver:

- **Prices.** Posts state $199 and $499. If either changes, these are wrong.
- **Timelines.** "Two days" for Quick Start, "four to six weeks" for larger
  builds, taken from the seeded FAQ. Confirm you can hold that at volume.
- **Inclusions.** The $199 list matches the package config. Keep them in sync.
- **Revisions.** Posts say one round is included with Quick Start.

If a claim here disagrees with a package page, fix the source of truth first —
a post that contradicts your own pricing page costs more trust than it earns.

## Writing more

`tools/blog-generator/` drafts additional posts from the same grounding
material. It needs a Claude API key; these three did not, and were written
directly.
