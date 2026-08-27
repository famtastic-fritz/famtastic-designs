# Verified cold proof campaign ingress v1

This lane is for a researched commercial cohort, not the legacy CSV outreach
importer. Its only write entry point is:

```text
drush famtastic:cold-proof-ingress /absolute/seed.json --dry-run
drush famtastic:cold-proof-ingress /absolute/seed.json --dry-run=0 --confirm=<cohort_key>
```

The seed schema is `famtastic.cold_proof_campaign_seed.v1`. Every lead must
carry a public source URL, provenance, date-bearing timeframe, corroborated
fact, non-factual proof teaser, recipient email, and a fact-backed website
observation. `unknown` website status is rejected: an omitted URL is never
treated as proof that a business has no website.

The enabled observation vocabulary is configuration-backed and currently
includes `confirmed_absent`, `observed_outdated`, `verified_present`, and
`exploratory`. The latter two create a respectful research-backed concept /
strategy review only: the proof and commercial email must not diagnose a weak
site, claim a missing website, or infer an offer from the observation. A
verified-present or observed-outdated observation requires its corroborated
public website URL; confirmed-absent may not carry one.

## Stored contract

Each accepted seed creates or reuses an immutable cohort record and creates a
dedicated `public-preview:proof.generate:delivery:<id>` job. It never creates
`proof.generate:prospect:*`, `outreach.prepare`, or `outreach.send` work.

`proof_profile` is selected per cohort and frozen on both delivery and job.
Profiles support one through six ordered directions (`a` through `f`); the
installed anonymous default remains three: Safe, Medium FAMtastic, Ultra
FAMtastic. The public room and callback validation use the frozen count, not a
global default.

Cold deliveries persist `source_lane: verified_cold`. That marker is passed to
the proof worker, Site Studio request, callback telemetry, and Build DNA. A
verified-cold direction must have signed media from the canonical asset
contract (`assets[]` with `asset_id`, `relative_path`, `media_type`, `base64`,
and `sha256`), and every asset hash must appear in Build DNA before staging.
The asset-capable callback/finalizer is the authority for actual media
promotion; ingress only fails closed until it supplies those artifacts.

## Owner and commercial-send gates

Proof generation does not send email. A ready set still needs Build DNA,
owner staging, an exact email review, and `approveAndHold`.

For `verified_cold`, staging creates a durable `famtastic_email_message` with
campaign attribution, sender snapshot, physical postal footer, unsubscribe
key, tracked click key, and the signed room URL stored server-side. The visible
CTA is the tracked click endpoint; it records a click and redirects only to
that message's validated signed room URL, never to a legacy prospect token.

Campaign approval is required when the owner holds/dispatches the message, not
when they stage it, so a full batch can be inspected before a campaign is
approved. The existing exact-ID dispatcher remains the only send boundary.
`famtastic:campaign-approve` deliberately excludes the
`verified_cold_preview` template, and the generic campaign sender rejects it
defensively. A verified-cold commercial message can only leave `held` through
the reviewed public-preview delivery ID.

Scheduled releases are optional and bounded:

```text
drush famtastic:cold-proof-scheduled-release --limit=10
drush famtastic:cold-proof-scheduled-release --limit=10 --execute=scheduled-owner-approved-cold-preview
```

The default is dry-run. The execute path selects only due `verified_cold`
deliveries already in `email_approved` with held outbox records and delegates
their exact IDs to the dedicated dispatcher. It never calls lifecycle/global
jobs or scans general outreach.

Historical generic jobs for the prior 260-send batch remain a separate manual
operator action:

```text
drush famtastic:campaign-proof-quarantine \
  --campaign=cold-260-aug-2026 \
  --confirm=cold-260-aug-2026 \
  --reason='Superseded by owner-gated verified-cold proof flow'
```

That command only quarantines exact queued legacy `proof.generate` records and
records ledger events. It does not send, rebuild, or alter unrelated work.
