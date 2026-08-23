# FAMtastic proof preservation and template library

Every generated proof package is evidence first. It is never deleted merely
because the customer selected another direction. This library creates two
separate records from completed local proof packages:

- `preservation-index.json` proves which source artifacts were retained and
  records their hashes without copying customer contact data into the index.
- `template-candidates.json` extracts reusable structural ideas from directions
  while excluding customer identity, raw intake, copy, and uploaded assets.

## Non-negotiable reuse rules

1. An unselected proof is not automatically a public portfolio piece.
2. Customer names, contact details, intake answers, uploads, logos, photos,
   testimonials, and business-specific copy are never promoted into a template.
3. Generated artwork still requires provenance, likeness, trademark, and owner
   review before reuse or publication.
4. Template candidates preserve layout logic, information architecture,
   interaction ideas, palette strategy, and creative rationale only.
5. Every candidate begins `internal_only` and `owner_review_required`.
6. A selected direction remains preserved for audit but defaults to
   `client_work_only`; it is not a template candidate unless separately cleared.
7. Public portfolio publication is a later explicit approval with rights and
   client-consent evidence where applicable.

## Run

```bash
node website-delivery-swarm/library/archive-template-ideas.mjs \
  artifacts/website-delivery-swarm/template-library \
  artifacts/website-delivery-swarm \
  website-delivery-swarm/pilots
```

The command is deterministic for unchanged inputs. It deduplicates concepts by
their working HTML hash and records skipped or legacy packages explicitly.
