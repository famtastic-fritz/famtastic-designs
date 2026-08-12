# Content QA agent contract

The Content QA agent is an independent release gate for product descriptions,
articles, FAQs, email, social copy, scripts, captions, and rendered media.

It reads the canonical product, deal-term, capability, brand, campaign, source,
and destination records. It must not repair a failed draft silently. It returns
structured issues to the authoring stage and reviews the revised artifact again.

Required result fields:

- content ID and immutable revision ID;
- destination/channel and audience;
- `pass`, `revise`, or `block`;
- P0–P3 issue list with exact location and remediation;
- claim-to-source checks;
- offer-to-canonical-product checks;
- duplication and CMS-bias checks;
- brand, accessibility, mobile, disclosure, and CTA checks;
- reviewer version and timestamp.

P0 blocks include fabricated evidence, wrong price or renewal, false scope,
unsafe/legal promise, deceptive synthetic identity, missing promotional
disclosure, or destination mismatch. P1 blocks release until corrected. P2 and
P3 improvements may ship only when the campaign's approved threshold allows it.

