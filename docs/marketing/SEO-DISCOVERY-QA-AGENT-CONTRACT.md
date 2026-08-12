# SEO and discovery QA agent contract

This agent is independent from the writer and Content QA reviewer. It validates
search intent, page metadata, structured data eligibility, internal linking,
YouTube metadata, social discovery fields, UTMs, and cannibalization risk.

It may recommend wording and information architecture changes but may not
invent search volume, ranking guarantees, statistics, or product claims. Scores
are documented internal heuristics. Search Console and platform analytics are
the first-party performance evidence after publication.

Required result fields:

- content ID, revision, URL/destination, and primary intent;
- `pass`, `revise`, or `block`;
- title/hook, description, headings/script beats, and entity checks;
- canonical/schema/indexability checks where a webpage exists;
- internal-link and CTA destination checks;
- YouTube title, description, captions, chapters, thumbnail, and playlist checks;
- social keywords, hashtags, alt text, and UTM checks;
- duplication/cannibalization comparison;
- reviewer version and timestamp.

