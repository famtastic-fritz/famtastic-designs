# Shared proof claim groundwork v1

Later source milestone: FRESH-PROOF-ADMISSION-AND-IMPORT-V1.md now records
default-OFF portal create/draft-submit admission, immutable bindings, generic
import denial and actual-claim status projection. Its 177 focused PHP tests are
synthetic source evidence. The original claim-only checkpoint below remains
historical; production cost catalog, producer, fenced importer and activation
remain absent/closed. Shared policy and selected behavior are unchanged.

September 21, 2026. Source checkpoint based on `854463b62`.
This implements claim policy only, not connected automation, creative generation,
authoritative proof admission/import, provider authorization, QA or delivery.

## Authority and compatibility

The existing Drupal `WorkerCoordinator`, `famtastic_job`,
`famtastic_worker_claim`, `famtastic_worker_budget` and shared database lock
remain the only claim/budget authority. No Firestore work or new schema exists.

| Capability | Policy | Lease | Heartbeat | Execution | Replacement fence | Attempts |
| --- | --- | --- | --- | --- | --- | --- |
| selected-static-dispatch-v1 | bounded-workers-v1 | 90 s | 30 s | 300 s | 330 s | <=3 |
| proof-creative-v1 | bounded-proof-claims-v1 | 180 s | 30 s | 1800 s | 1830 s | <=3 |

Renewal clamps to the original execution deadline. Failure/expiry invalidates
the token but retains the absolute replacement fence, backoff and cost hold.
The monotonic attempt plus hashed token and authenticated worker fence old
generations. The global single-attempt constraint applies across capabilities.
The $25/month authorization, $20 reservation stop and previous-month unknown
holds are unchanged. Holds are not billed-cost receipts or a cloud spending cap.

`claim($worker, $authorizedCapabilities = [selected-static-dispatch-v1])` takes
trusted server grants only. HTTP derives those grants from the installed worker
registry. The optional claim body `capability` selects one granted capability;
it cannot add one. An omitted capability stays static-only, even for a multi-role
identity. The existing CLI tick, enrollment command and Node cloud worker remain
unchanged/static-only. No fresh-proof admission flag or scanner is installed.

`renew`, `fail` and `finish` accept trailing server-grant and attempt arguments.
Proof renew/fail require the exact integer attempt. Timing/policy comes from
the stored capability, not request-body timing/profile fields. `proof.review`
remains separately authenticated and cannot claim work. All proof `finish`
requests reject, including selected-dispatch receipts and duplicate-success
shortcuts: the authoritative importer does not exist in this checkpoint.

## Explicit, closed enrollment seam

`enrollProof($jobId, $jobKey, $payloadSha256, $reservationCents)` is a trusted
service seam only. It has no HTTP, CLI or automatic intake caller. The installed
coordinator has an EMPTY reviewed proof cost catalog, so proof enrollment fails
closed. A future reviewed source change must supply the fourth constructor
argument; no Settings, environment or HTTP body can install a cost policy.

The stored `proof.generate` payload must have exactly these v1 fields:

```text
schema = famtastic.proof-worker-input.v1
routine = website_proof.generate.v1
website_request_id, customer_id, organization_id, prospect_id, proof_campaign_id
website_request_public_id (UUID), campaign_id, studio_job_id
brief_version = 1, brief_sha256, website_discovery_v3
request_binding_sha256, asset_authority_sha256
direction_ids = [a, b, c]
recipe = {id, revision: 40-hex, sha256: 64-hex}
tool_allowlist = [unique bounded tool identifiers]
cost_policy = {id, revision: 40-hex, currency: USD,
               max_calls, max_cost_cents, reservation_cents}
```

Numeric identities must be positive integers; prospect must match the job.
The brief hash is SHA256 of PHP JSON_UNESCAPED_SLASHES brief encoding, and the
job key is `website_proof.generate.v1:request:<id>:brief:<brief_sha256>`.
The payload hash covers the exact stored JSON bytes. Additional payload fields
require a reviewed contract version, not silent interpretation.

The trusted catalog is keyed by cost-policy ID, each entry containing the exact
`recipe`, `tool_allowlist` and `cost_policy` arrays. Matching uses strict PHP
array equality, including ordering/types. Calls are bounded to 1..32; tools to
1..16 unique identifiers. Explicit reservation must be 25..250 cents and cover
the positive integer maximum cost. There is NO automatic 25-cent creative
budget. A recipe exceeding these bounds is unsupported pending policy review.
The only catalog in these tests is synthetic; no real provider price is implied.

Enrollment verifies shape, immutable bytes and internal identity consistency.
It does NOT read live request/account/campaign/asset records. Their authoritative
existence, submitted status, consent and current binding must be validated by the
future admission writer before this seam is wired. Current legacy proof jobs do
not satisfy this schema and are not enrolled or replayed. Enrollment/queue-state
transition is transactional and admission reserves no money. Claim revalidates
the proof input/catalog before reserving; owned proof operations revalidate it.

## Source and regression map

- `Service/WorkerCapabilityPolicy.php`: profiles, frozen payload and reviewed cost bounds.
- `Service/WorkerCoordinator.php`: exact enrollment, filtered shared claims,
  stored-profile renewal, attempt fences, retained holds, closed proof finish.
- `Controller/WorkerCoordinatorController.php`: server registry grants, explicit
  capability narrowing, static defaults, independent reviewer identity.
- `tests/src/Unit/SharedProofWorkerClaimsTest.php`: paused-clock Mac/cloud
  contention, 180/1800/1830 boundaries, stale token/generation, static isolation,
  immutable input, malformed/unsupported cost policy, budget exhaustion,
  rollover holds, rollback, role revocation and proof completion rejection.

Paths above are under `backend/web/modules/custom/famtastic_pipeline/`.
No campaign/import/QA/selected payload producer, route, service wiring, worker
adapter, dependency or lockfile was changed.

## Verification receipt

88 PHP tests / 356 assertions and 8 existing Node worker tests passed. PHP
8.5.9, PHPUnit 11.5.56. The existing dependency runtime below matched this
checkout's composer.json and composer.lock byte for byte. No installation,
site bootstrap, authoritative database or credentials were used. SQLite is
in-memory; the lock is mocked, so this is NOT concurrent MySQL proof.

Run from the isolated checkout. Each test command was guarded above 200 MiB
free. OS sandbox denied all network; PHPUnit result caching was disabled.

```sh
test "$(df -k . | awk 'NR==2 {print $4}')" -ge 204800 && \
FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor \
sandbox-exec -p '(version 1)(allow default)(deny network*)' \
php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit \
  --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/SharedProofWorkerClaimsTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/WorkerCoordinatorControllerTest.php \
  backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/FreshSelectedJobAdmissionTest.php

test "$(df -k . | awk 'NR==2 {print $4}')" -ge 204800 && \
sandbox-exec -p '(version 1)(allow default)(deny network*)' \
node --test scripts/cloud-worker/worker.test.mjs
```

PHP syntax and git whitespace checks passed. No full suite, build, provider
preflight, deployment, push, source integration or production action occurred.
Work stayed in the assigned isolated checkout. Drive mirroring and remote fetch
were deferred to the integrating owner to preserve this offline scoped handoff.
Free space fell from 1,503 MiB to 332 MiB during this task; no further tests
were started after final verification. No cleanup or deletion was performed.
A sparse-omitted tracked learnings file initially appeared absent; final diff
review caught the replacement and restored all historical bytes before commit,
leaving only the new entry. Check the Git baseline before adding absent paths.

## Still closed

Next work must implement atomic fresh request/campaign admission, authoritative
current-input/rights checks, pre-provider authorization, durable operation
checkpoints and unknown-outcome reconciliation, fenced canonical import and
independent QA/notice provenance. A claim is NOT permission to call a provider;
lease expiry does not prove a prior paid operation stopped. The current retry
mechanism is ownership machinery, not permission to regenerate uncertain work.

There is no real creative adapter or unattended completion proof. The active
Mac customer workflow must be packaged explicitly; the six-direction fictional
benchmark and static packet bridge are not substitutes. Cloud shadow work proves
neither proof generation nor Commerce. Cloud activation and laptop independence
remain closed/unproven. No capabilities were promoted to production-proven.
