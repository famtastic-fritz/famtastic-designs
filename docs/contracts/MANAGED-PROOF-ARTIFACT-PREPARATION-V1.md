# Managed proof artifact preparation v1

September 21, 2026. Source groundwork only, based on `3bc4e9baf`.
Focused runtime verification: 105 tests / 362 assertions PASS after the test-helper
rename below. The original disk pause and failed receipts are retained. This is
not an importer, producer or deliverable proof.

## Scope and unchanged authority

`ProofCallbackArtifacts::normalize` is the unchanged validation block extracted
from `ProofCampaignService::acceptCallbackInternal`. Required directions and the
signed-assets policy remain trusted caller inputs. Legacy casts, optional fields,
aliases, ordering, thumbnail extension behavior, error classes/messages and caps
are preserved. Generic callback guarding, persistence, telemetry, delivery and
historical behavior are unchanged. The one manual require list found in tracked
source, `scripts/test-normal-selected-records.php`, loads the actual new class.

`ManagedProofArtifactStore` has no service registration, route, CLI, production
caller, Drupal bootstrap or DB dependency. Both constructor roots default NULL;
preparation rejects until a trusted caller supplies existing canonical absolute
directories. They must be disjoint, with private storage outside the document
root, owner-only permissions and no symlink components. No worker field can name
the root, bundle directory or absolute file destination. Installation must ensure
the private root is not exposed through another virtual host, alias or share.
This implementation assumes the OS principal and its private root are trusted;
it does not defend against another process with that same filesystem authority.

## Bounded input and complete inventory

`prepare(string $rawCallback, array $expectedNormalized): array` accepts only
canonical JSON (unescaped Unicode/slashes, preserved zero fractions), at most the
unchanged 24 MiB callback transport bound. A parse/re-encode equality check rejects
duplicate JSON keys and noncanonical bytes. The top-level exact required keys are
`schema`, `event_id`, `campaign_id`, `job_id`, `variants`; schema is
`famtastic.managed-proof-artifacts.v1`. Unknown fields, including lease credentials,
paths and arbitrary Build DNA envelopes, reject before writes. Correlation IDs
are syntax-checked data, NOT authenticated worker/account/claim authority.

Each of A/B/C requires direction_id, HTML and descriptive design_dna; optional
assets and thumbnail fields use the existing validator. Managed assets require
the five canonical asset fields, not legacy aliases or producer storage paths.
DNA is bounded to 32 KiB, 512 nodes, depth eight and 4096-byte strings. Credential
field names and upstream source_capture/asset_manifest/selected continuation
authority are prohibited recursively. This is not a secret-content scanner:
callers must never include secrets in free text, HTML or images. A future producer
must sanitize its evidence before this boundary; lease envelopes are never input.

The preparer independently normalizes the raw variants and strictly compares
every result field with the supplied normalized array before writing. Files retain
exact HTML, decoded image/thumbnail bytes, normalized direction DNA JSON and the
exact accepted raw callback. The manifest covers every file's path, role, SHA256
and length plus each direction's HTML/DNA/assets/optional thumbnail association.
It is not a validated `famtastic.build-dna.v1` record: immutable Build DNA and its
authenticated producer/claim binding remain future importer work.

At most 22 content files plus manifest are possible; aggregate preparation is
bounded to 40 MiB. All files use exclusive creation, verified full writes, flush,
fsync, owner-only read permissions and rechecked hashes/lengths. The complete
inventory is verified again before manifest creation. The returned manifest hash
is not a completion/QA receipt. Every manifest says private_preparation_only and
deliverable=false. Preparation tests deliberately omit creator credit and are
NOT deliverable customer artifacts.

Every invocation allocates a new random server directory. Existing directories,
files, symlinks or conflicting bytes reject; no overwrite, automatic adoption,
resume, retry refund, cleanup or orphan deletion exists. A failure can leave
partial private files, including a failed manifest write, but cannot return a
successful preparation. No reader may infer authority from a directory/manifest.
DB rollback cannot reverse these writes; a future importer only adopts a verified
private bundle through an authoritative DB receipt. Test cleanup is confined to
its own random temporary directory and does not follow symlinks.

## Caps, logo and static HTML boundary

Generic caps are unchanged: 500,000-byte HTML, 1,500,000-byte PNG/JPEG thumbnail,
2,000,000 bytes per image, four images and 3,000,000 image bytes per direction.
The canonical server-owned PNG is 2,020,725 bytes, SHA256
`ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
It cannot pass ordinary callback or stored-manifest asset validation. No cap was
raised, alternate logo generated or asset smuggled through a different manifest.
A separately reviewed exact server-owned asset policy/materializer and matching
readers remain required for a portable credited bundle. Hosted credit is a render
dependency, not an immutable local asset. Preserve original HTML and receipt-bind
any future credited derivative, consistent with selected creator-credit v2.

Script/event-handler/iframe/object/embed/base restrictions remain unchanged. The
legacy regex is not a complete HTML sanitizer or functionality proof. Static/demo
HTML preparation does not prove live forms, commerce or an actual creative run.

## Focused verification command and receipt

The existing vendor's composer.json and composer.lock match this checkout.
Only the three new test files ran serially through the parent's existing wrapper:
PHP 8.5.9, PHPUnit 11.5.56, 105 tests / 362 assertions, 0.878 seconds, 12 MiB peak,
no failures, skips or deprecations. Wrapper elapsed 1.217 seconds, exit 0,
stoppedFor=null, protectedDataUnchanged=true. Evidence:
`/tmp/famtastic-phase2-review.NVAfPl/managed-artifact-preparation-focused.58jjE3`.
Source is checkpoint `82a04160` plus only the fixture helper rename; production
source is unchanged from that checkpoint. Nine PHP syntax checks and whitespace
checks pass. Disk was roughly 394-403 MiB during this verification.

The wrapper checks 200 MiB initially and every 500 ms, terminates the child process
group on low space, excludes protected DB/credential reads/writes from tests, and
compares both protected-data inventories before/after. Its existing network policy
denies external network but retains localhost allowances for other parent tests;
these three tests use no network. No installs, full suites, builds, authoritative
DB, provider, cloud, send or activation. Exact executed command:

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-artifact-preparation-focused "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackArtifactsTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackServiceParityTest.php"
```

The frozen validator fixture is the pre-extraction block. The service differential
also reads the actual `3bc4e9baf` service via git show, then executes old and new
public callback plus duplicate through the same manual legacy fixture, stopping
before its selected-build flow. Compare outputs, mutations, hashes and failures.
No production guard is mocked away; that fixture rejects seeded managed events.
Store cases cover unknown/sensitive wire fields, raw/normalized mismatch, caps,
complete manifests, path escape, roots, symlinks, existing files, short writes,
wrong sizes/hashes and retained unfinished preparation. Real temporary filesystem
operations and actual callback code use isolated fixture persistence, not installed
Drupal kernel or concurrent MySQL proof. Parent's earlier 482-test / 2631-assertion
baseline and Studio green checkpoint are separate, not added to these totals.

Retained failures (both inventories unchanged):

- Parent `managed-artifact-preparation-first.7Tnf7P`, exit 255: PHPUnit's final
  TestCase::result method conflicts with the new private helper name. Rename only
  that helper/callers to captureNormalizationOutcome; do not change assertions.
- `managed-artifact-preparation-rename.DczFpF`, exit 71: attempted nested sandbox
  was refused before PHPUnit ran. Use the parent's existing sandbox wrapper once;
  do not call this attempt a test failure or silently relax its exclusions.

The initial 82a04160 source checkpoint was correctly labeled untested because disk
fell below 200 MiB. Main recovered tracked campaign-asset sparse-view space without
deleting Git history or owner files. No assets were rehydrated for these tests.

## Still required before completion or activation

The generic managed callback guard and proof finish remain closed. Shared claim
profiles, $20 stop / $25 monthly ceiling, unknown holds and empty production cost
catalog are untouched. Required later work: serialized live request/account/asset
writers, authoritative lease/generation/deadline-fenced root transaction, direct
unique import receipt, exact retries, receipt-aware lifecycle projection, private
artifact readers/selected-source compatibility, immutable producer identity and
Build DNA, independent QA/current-rights binding, separate authorized notification,
real creative adapter, reviewed costs and uncertain paid-operation recovery.

No completion route, registration, provider/preflight, sends, production writes,
cloud action, deploy or push. Remote fetch and Drive mirroring are deferred to
the parent under this offline isolated scope. No capability is production-proven.
