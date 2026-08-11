# Content contract

## Required series fields

- Stable key, title, thesis, audience, funnel stage, pillar slug, status, and sequence.
- One primary category and a controlled set of tags.
- Capability keys that exist in the capability registry.
- A clear conversion path that does not assume the $199 product fits every reader.

## Required post fields

- Stable key, series key, sequence, title, slug, status, category, and tags.
- Search intent, reader problem, promised outcome, and evidence boundary.
- Excerpt, meta title, meta description, body HTML, primary CTA, FAQs, and internal links.
- CTA label, route, lifecycle stage, event name, and whether it requires approval.
- No unsupported performance claim, invented customer story, or unapproved guarantee.

## Taxonomy rules

- Categories describe the primary customer job and remain intentionally broad.
- Tags describe reusable subjects, technologies, business problems, and service contexts.
- A series is an ordered learning journey, not a synonym for a category.
- Prefer an existing normalized term over spelling, capitalization, or plural variants.

## CTA rules

- Awareness: assessment, checklist, explainer, or useful tool.
- Consideration: recommendation, comparison, demonstration, or workflow review.
- Decision: configured purchase, scoped consultation, or project start.
- Customer: support, documentation, add-on, renewal, referral, or relevant upgrade.
- Record `cta_clicked` with content, series, CTA, and destination identifiers.

## FAQ rules

- Answer a genuine customer question directly.
- Keep answers useful without requiring a sales call.
- Reuse a canonical FAQ when the answer is the same.
- Create a separate FAQ only when the audience, product, or operational answer materially differs.
- Do not promise FAQ rich results.
