# Blog generator

Drafts blog posts for FAMtastic Designs from the site's own services, packages,
FAQs, and case studies.

It is deliberately **not** an auto-publisher. Drafts land on disk as Markdown and
a human edits and approves them. Fully-automatic publishing is what produces the
interchangeable filler that most AI blog tools generate — the review step is the
feature, not friction.

## Why the output isn't generic

The model is given only one source of truth about the business: a corpus pulled
from your live site (`sources.json`). It is instructed that every price,
timeline, inclusion, and result must trace back to that corpus, and that it may
not invent testimonials, statistics, or client names. Topics are chosen from
questions your own FAQ shows customers asking, and from the offer ladder you
actually publish. A post that could have been written about any web designer in
the country is a failure by that standard.

Tone and rules live in `house-style.md` — plain Markdown, passed to the model
verbatim on every run. Edit that file to change how every future draft reads; no
code change needed.

## Setup

Requires Node 22 (matches the repo `.nvmrc`) and Claude API credentials.

```bash
cd tools/blog-generator
npm install
export ANTHROPIC_API_KEY=sk-ant-...   # or run `ant auth login`
```

This tool keeps its own `package.json` so it never touches
`frontend/package-lock.json` or `backend/composer.lock`. Nothing is added to the
Drupal runtime — the repo's dependency policy requires a reviewed platform
migration for that, and generating blog copy does not justify one.

## Use

```bash
# 1. Pull real content from the site (anonymous JSON:API read, no credentials)
npm run sources -- --drupal=https://famtasticdesigns.com

# 2. Propose topics — cheap, and reviewable before you spend on drafts
npm run plan -- --count=6
#    → plan.json. Delete or edit entries you don't want.

# 3. Draft them
npm run draft
#    → drafts/*.md, one file per post

# Re-draft a single post after editing its plan entry
npm run draft -- --slug=how-much-does-a-website-cost
```

Each draft carries front matter that maps onto the `blog_post` content type:
`title`, `summary` (→ `field_summary`), and the Markdown body (→ `body`).

## Quality gate

Every draft is checked before it is written, and problems are printed and
summarised at the end of the run. A draft is flagged when it:

- falls outside 700–1100 words;
- has no link to the `/199` offer page;
- has no link to a service or package page;
- contains an H1 (the title is rendered separately by `BlogPostPage`);
- has a summary over 200 characters.

Flagged drafts are still written — the gate tells you where to look, it does not
silently discard work.

## Publishing

Deliberately manual. Read the draft, edit it, then create the `blog_post` node in
Drupal with the front-matter values. Two things worth checking every time:

1. **Every factual claim.** The model is instructed not to invent facts, but this
   is a real business making public claims about price and timeline. Verify them
   against what you actually deliver.
2. **The offer is consistent.** If a post says $199 and the package page says
   something else, the post is wrong — fix the source of truth first.

## Cost

Roughly a few cents per post at current Opus pricing. The corpus and house style
sit behind a prompt-cache breakpoint, so a six-post run pays for that prefix once
and reads it back on the other five — the `tokens:` line printed after each call
shows the cached portion.

## Files

| Path | What it is |
|---|---|
| `house-style.md` | Voice, hard rules, structure. Edit freely. |
| `sources.sample.json` | A committed sample corpus so the tool runs without Drupal. |
| `sources.json` | Generated. Real content pulled from the site. |
| `plan.json` | Generated. Proposed topics — edit before drafting. |
| `drafts/` | Generated. One Markdown file per post. |
