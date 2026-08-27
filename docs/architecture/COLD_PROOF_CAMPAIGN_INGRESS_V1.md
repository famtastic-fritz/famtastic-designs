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

Each accepted seed creates or reuses an immutable cohort record, then creates
one delivery-bound `proof_campaign` and one dedicated
`public-preview:generate:delivery:<id>:campaign:<id>`
`public_preview.generate` job. The campaign entity ID, public campaign ID,
opaque callback job ID, distinct callback event ID, delivery ID, and recorded
run-start time are persisted in the exact job payload before any worker can
run. The immutable `build_dna_run` tuple is:

```text
prospect_id, proof_campaign_id, campaign_id, job_id,
callback_event_id, run_started_at, source_lane=verified_cold
```

The numeric scheduler row is exposed only as `job.id` for audit; it is never
the callback-facing `job_id`. The lane never creates `proof.generate:prospect:*`,
`outreach.prepare`, or `outreach.send` work.

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

## Safe runner handoff

Before a local or remote builder starts, an operator can export one through
ten exact verified-cold delivery bindings:

```text
drush famtastic:cold-proof-handoff-export --ids=41
drush famtastic:cold-proof-handoff-export --ids=41,42 \
  --output=/configured/private/famtastic/cold-handoff.json --confirm=41,42
```

Stdout contains no recipient, outbox, approval, share-token, or sender data.
A file export requires exact-ID confirmation, a new non-symlink path below
Drupal's configured private filesystem, and mode `0600`. It is read-only: no
provider, callback, proof execution, email, approval, or dispatch occurs.

The emitted schema is `famtastic.verified-cold-proof-handoff.v1`. Each delivery
has direct binder fields named `prospect_id`, `proof_campaign_id`,
`campaign_id`, `job_id`, `callback_event_id`, `run_started_at`, and
`source_lane`; they are copied from the durable job, never generated on export.
A builder must return the exact job/event pair and copy the supplied
`build_dna_run` object unchanged into Build DNA `run` before its final hash is
calculated (the current binder retains `run_started_at` and mirrors it as
`run.started_at`).
`BuildTelemetryService` rejects a `verified_cold` manifest that lacks the
complete tuple or one SHA-256 asset-ledger entry per a/b/c direction. Staging
then rechecks the final run against its delivery/job packet and every imported
signed asset hash.

### Current local signed-media importer

The shipped Beauty / Hair / Braiding finalizer is intentionally the
three-direction `anonymous_safe_medium_ultra_v1` adapter only. It accepts the
frozen `a/b/c` profile and fails closed for another 1--6 profile; that is not a
global profile restriction, only an honest statement that a compatible
asset-capable finalizer must exist before another shape can be imported.

For that adapter, the local handoff is explicit and never uses
`scripts/promote-local-proof-godaddy.sh` (that historical promoter rejects
runtime-bound `verified_cold` jobs):

```text
# 1. Create signed assets locally; no Drupal or network action.
node website-delivery-swarm/cohorts/beauty-hair-braiding/serialize-signed-proof-assets.mjs \
  --bundle /absolute/finalized-bundle \
  --output /configured/private/famtastic/callback-assets.json

# 2. Assemble page HTML, thumbnails, design DNA, assets, and exact runtime IDs.
node website-delivery-swarm/cohorts/beauty-hair-braiding/assemble-verified-cold-callback.mjs \
  --bundle /absolute/finalized-bundle \
  --assets /configured/private/famtastic/callback-assets.json \
  --output /configured/private/famtastic/verified-cold-callback.json

# 3. Default is a no-write checksum/HMAC plan. `--apply-local` imports only
#    the exact delivery through the narrow Drush command.
scripts/import-verified-cold-proof.sh \
  --delivery=41 --confirm=pc-example \
  --callback=/configured/private/famtastic/verified-cold-callback.json \
  --build-dna=/absolute/finalized-bundle/build-dna.json
```

The assembler refuses an unbound/tampered bundle, missing a/b/c page or
thumbnail, missing signed asset, or an asset hash absent from Build DNA. The
Drush importer additionally requires private non-symlink input paths, exact
checksums, the configured callback HMAC, delivery/campaign/job/event/start-time
identity, and an immutable Build DNA projection. It records artifacts only;
owner review, public room staging, and email still remain separate gates.

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
