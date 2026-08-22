# Proof revision versioning v1

## Resumable implementation brief

**Title:** Account-owned selected-direction revision lane

**Purpose:** Turn an authenticated client's “Make changes” action into a
durable, source-bound refinement request without replacing the original proof,
changing commercial state, or exposing an unreviewed result.

**Goal:** A selected direction from a completed `refined_six` proof campaign
can produce exactly one revision candidate at a time. Its notes, baseline
artifact, Build DNA lineage, runner handoff, candidate artifact, review decision,
and customer-visible state must be independently retrievable.

**Tasks:**

- [x] Define immutable revision and artifact-lineage records.
- [x] Define the source-bound `proof.revision.generate` job envelope.
- [x] Define owner-only candidate acceptance and customer-visibility gate.
- [x] Add local fixture coverage for request, callback, approval, and no-checkout behavior.
- [ ] Connect the canonical proof-runner transport and signed callback to this contract.
- [ ] Exercise one real provider run and an owner-approved customer delivery before calling the lane production-proven.

**Status:** Implemented as a local backend contract; runner transport and real
provider evidence remain separate, explicit integration gates.

**Started:** 2026-08-22

**Ended:** Pending canonical runner integration and production evidence.

**Execution:** `codex/shay-website-delivery-swarm` in
`/Users/famtastic-fritz/Development/FAMtastic/worktrees/shay-website-delivery-swarm`.

**Research:** This contract extends the public-preview/detailed-refinement
boundary in `PUBLIC_PREVIEW_DELIVERY_V1.md` and the immutable evidence rules in
`BUILD_DNA_STANDARD_V1.md`.

**Review:** An owner reviews the exact generated replacement before it becomes
customer-visible. A customer request, a worker dispatch, a callback receipt,
and an owner approval are distinct states.

**Skills:** `prove-famtastic-customer-journey` (local, fixture-only validation).

**Blocked By:** The runner must add the revision profile and call the service
entrypoints below. No fallback renderer, chat-only page, or unrecorded provider
may substitute for it.

**Proof:** `scripts/e2e-proof-revision-versioning.sh` creates an isolated Drupal
SQLite installation and proves the state machine without a provider, SMTP send,
checkout, or production mutation.

## Lifecycle

```text
authenticated six-proof selection
  -> immutable revision request + baseline snapshot
  -> proof.revision.generate job
  -> canonical website_proof.generate.v1 / revision profile dispatch
  -> signed, Build-DNA-verified single-direction callback
  -> owner review
  -> explicit owner approval
  -> customer sees the replacement and may choose checkout separately
```

The base campaign and its original `proof_variant` are never overwritten. Each
revision has one `baseline` artifact and one `candidate` artifact. A later
revision snapshots the last owner-approved candidate as its new baseline, so
the full lineage remains queryable.

For the first revision, the service refuses to snapshot a merely ready-looking
campaign. The original selected direction must still resolve to the exact
completed `portal_refined_six.v1` Build DNA receipt whose request IDs and
`proof_phase=refined_six` match the account-owned request.

## Durable records

`famtastic_proof_revision` is the work-item and approval record. It stores the
account/request/campaign/direction correlation, cleaned customer notes and hash,
monotonic direction-local revision number, baseline/candidate Build DNA IDs and
hashes, queued job and provider identifiers, owner decision, and visibility
timestamps.

`famtastic_proof_revision_artifact` is append-only content lineage. It holds:

- `baseline`: the selected original proof or prior approved candidate;
- `candidate`: the replacement produced for this exact revision only.

Artifact bytes live beneath the request-owned proof campaign directory but in a
unique revision/version directory. Direct web access stays denied; the existing
authorized preview controller resolves the active candidate only after owner
approval. The original proof stays available as historical evidence.

## Runner handoff contract

The job type is `proof.revision.generate`; the only creative routine remains
`website_proof.generate.v1`. Its profile is
`portal_selected_direction_revision.v1`, with `proof_phase=revision` and
`proof_count=1`.

The payload must include the immutable revision identifiers, exact
website-request and campaign correlation, selected direction and revision
number, sanitized notes plus `notes_sha256`, baseline artifact and Build DNA
hashes, and an explicit one-direction contract. It must not request or accept a
six-direction replacement, change pricing, create an offer, create an order,
or initiate checkout.

After a declared provider transport is actually dispatched, it calls:

```php
$revisions->markRunnerDispatched(
  $revisionPublicId,
  $providerJobId,
  $buildId,
  $contractSha256,
);
```

Only after the normal signed callback and `ProofRunnerCallbackVerifier` accept
the final Build DNA may the callback adapter call:

```php
$revisions->acceptVerifiedCandidate(
  $revisionPublicId,
  $eventId,
  $providerJobId,
  $singleDirectionVariant,
  $runnerVerification,
);
```

`$singleDirectionVariant` contains exactly the selected direction's HTML,
matching SHA-256, optional thumbnail, and direction-specific design metadata.
`$runnerVerification` must be `status=verified`, reference the exact Build DNA
record, and repeat the request/campaign/revision/direction/version source
correlation. Its registered receipt must be a completed
`famtastic.build-dna.v1` `production_proof_completion`, with
`run.completion_state=provider_completed`. The service fails closed on a
missing, preflight/fixture, stale, mismatched, or multi-direction result.

## Owner and customer gates

The candidate callback queues an owner review alert and a neutral customer
status receipt, but no candidate link. The owner-only proof review form calls
`approveRevision()` only after confirming the exact selected-direction
replacement. That sets the candidate artifact to `customer_visible` and queues
a customer-ready notification. It does not send directly, change a price,
modify terms, create an order, register a Site Studio build packet, or begin
checkout.

The request remains selected; the customer can inspect the approved replacement
through the ordinary authenticated proof route and separately choose a package
and terms. An existing unlisted share resolves only the most recently
owner-approved version, never an owner-review candidate.

## Evidence classification

The automated script proves only local state, access, version lineage, queue
records, and fail-closed gates. It does **not** prove a creative provider,
browser quality, independent visual approval, actual mailbox delivery, Stripe,
or production deployment. Those must be recorded through Build DNA and the
normal owner-reviewed release process.
