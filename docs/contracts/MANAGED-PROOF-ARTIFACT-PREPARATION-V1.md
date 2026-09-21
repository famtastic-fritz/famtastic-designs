# Managed proof artifact preparation v1

September 21, 2026. Source groundwork only, based on `3bc4e9baf`.
Focused runtime verification: 109 tests / 463 assertions PASS with Git unavailable,
bounded callback child arguments and independently exercised DNA guards. A
subprocess-only DNA mutant fails all nine targeted cases as intended. The earlier
helper repair, original disk pause and failed receipts are retained below. This
is not an importer, producer or deliverable proof.

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
file fsync, owner-only read permissions and rechecked hashes/lengths. The complete
inventory is verified again before manifest creation. The returned manifest hash
is not a completion/QA receipt. Every manifest says private_preparation_only and
deliverable=false. Preparation tests deliberately omit creator credit and are
NOT deliverable customer artifacts.

There is no directory fsync. File fsync does not guarantee that directory entries
or the manifest survive a crash or power loss; no such durability or atomic
filesystem/DB publication is claimed. Preparation is never import authority.

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

## Frozen source outside the docroot and current receipt

The frozen old service and adjacent provenance now reside together in
`backend/tests/fixtures/managed-proof/`, outside `backend/web`. The parity test
uses that repository-relative location. Installed Drupal's actual `.htaccess`
(read from the matching dependency checkout's `backend/web/.htaccess`) denies
several source extensions but not `.fixture` or every custom-module tests path.
A non-autoload extension is not a web access boundary. Neither file remains in
the old module test directory; no webserver deny rule is assumed or added.

Both files are byte-for-byte unchanged by the move:

- `ProofCampaignService.pre-extraction.fixture`: 63,318 bytes, SHA256
  `549273b904ff05a263f2b3db5bc182f4b11e7fa2779ba3a69491eded1810e890`.
- `ProofCampaignService.pre-extraction.provenance.json`: 707 bytes, SHA256
  `d34b39db4cf8f866c04b94e097ce8a891379591bd55f9f4d93d48efa8bbb8708`.

Only the three focused files were rerun once after the move: **109 tests /
463 assertions PASS**, PHP 8.5.9 / PHPUnit 11.5.56, 0.826 seconds, 12 MiB, no
failures/skips/warnings/deprecations. Wrapper exit 0, elapsed 1.165 seconds,
stoppedFor=null, protectedDataUnchanged=true. Evidence:
`/tmp/famtastic-phase2-review.NVAfPl/managed-artifact-fixture-outside-docroot.5ZesXQ`.
The 200 MiB watch guard remained active; disk after the run was 740,264 KiB.
The previous nine-case intentional DNA mutation receipt below is retained, not
rerun or reclassified. No production code, guard or activation changed. Command:

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-artifact-fixture-outside-docroot "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && /usr/bin/env PATH=/nonexistent/famtastic-parity-no-executables FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackArtifactsTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackServiceParityTest.php"
```

## DNA guard isolation repair and retained receipts

Independent review found that the original prepare helper supplied default
normalized artifacts even for changed DNA. Those negative cases could pass on
raw/normalized mismatch if the intended DNA guard were removed. The focused pass
counts below remain historical receipts, not independent proof of those guards.

The dedicated nine-case DNA provider now normalizes the SAME input with the real
legacy validator, outside the rejection assertion. It proves that DNA survives
normalization, then asserts InvalidArgumentException, the exact intended guard
message and no private writes. It covers five sensitive/protected nested keys,
string/node/depth bounds and the separate serialized DNA byte bound. A positive
nondefault-DNA case verifies all three directions' exact canonical bytes and
manifest role/size/hash, plus raw callback preservation and no web writes.
Malformed asset cases still reach the store without pre-normalizing bad assets;
normalizer rejection is not counted as store verification.

`tests/src/Unit/Fixtures/ManagedProofDnaMutationBootstrap.php` is an explicitly
invoked, test-only bootstrap. It loads the matching existing vendor, checks the
store is unloaded and requires exactly one match for each mutation anchor. It
suppresses only the recursive DNA call and serialized-size predicate in memory
in a separate process. It never rewrites production source or disables equality,
asset, private-root or filesystem guards. Normal discovery does not invoke it.

Serial guarded verification, PHP 8.5.9 / PHPUnit 11.5.56:

- Real source, same three focused files: **109 tests / 463 assertions PASS**,
  0.785 seconds, 12 MiB, no failures/skips/warnings/deprecations. Wrapper exit 0,
  elapsed 1.122 seconds. Evidence: `managed-artifact-dna-focused.nRmwUO`.
- Intentional mutant, only the nine DNA cases: **9 tests / 18 assertions /
  9 failures**, 0.067 seconds, 10 MiB. Each fails the missing-rejection assertion
  because the mutant prepares the input. Wrapper exit 1, elapsed 0.285 seconds.
  Evidence: `managed-artifact-dna-mutation.gaWaaN`. This is executed negative
  mutation proof, not a bootstrap failure or a failure of unchanged real source.

Both evidence directories are under `/tmp/famtastic-phase2-review.NVAfPl/`;
both receipts report stoppedFor=null and protectedDataUnchanged=true. The same
200 MiB continuous guard and exclusions below apply. Available disk after both
runs was 734,728 KiB. Only test/fixture/docs changed from `b5831ea1`; production
source and its guards are unchanged. Exact executed commands:

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-artifact-dna-focused "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && /usr/bin/env PATH=/nonexistent/famtastic-parity-no-executables FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackArtifactsTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackServiceParityTest.php"
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-artifact-dna-mutation "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && /usr/bin/env PATH=/nonexistent/famtastic-parity-no-executables FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/Fixtures/ManagedProofDnaMutationBootstrap.php --no-configuration --do-not-cache-result --filter testDnaGuardsRejectMatchingNormalizedInput backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php"
```

## Shallow-checkout portability repair and prior receipt

The old differential required `git show 3bc4e9baf:...` at test runtime, but the
actual acceptance workflow uses actions/checkout's default shallow history. Keep
the public-callback differential, not a skip or broader fetch. The committed
`backend/tests/fixtures/managed-proof/ProofCampaignService.pre-extraction.fixture` is the full
old service, byte-identical to commit `3bc4e9baf0368db35d454ae8922c149430a866b4`,
path `backend/web/modules/custom/famtastic_pipeline/src/Service/ProofCampaignService.php`.
Provenance is in the adjacent `.provenance.json`:

- Git blob: `92a491c408ae8493afc0f7a40c7759f43078afeb`.
- Bytes: 63,318; enforced maximum: 65,536.
- SHA256: `549273b904ff05a263f2b3db5bc182f4b11e7fa2779ba3a69491eded1810e890`.

The non-PHP-autoload extension is explicit test data, never registered as a
service. Every differential case checks its size and hash before executing old
and new actual public callbacks and duplicate retries through the same existing
manual fixture. Trusted test-owned file paths avoid double-encoding the entire
old service into argv; each child code argument is asserted below 65,536 bytes.
The earlier argument exceeded 134 KB, an unnecessary CI portability risk.
No production source or guards changed in this repair.

All callback children receive an unusable PATH; a dedicated child probe proves
Git cannot run in that same environment. The entire PHPUnit invocation below
also has that PATH. PHP uses its absolute executable path. There is no runtime
history lookup or skip, and no workflow/fetch-depth change. This is local macOS /
PHP 8.5.9 verification, not a hosted Actions or PHP 8.3 execution receipt.

Only the same three focused files ran serially under the existing 200 MiB watch
guard: **106 tests / 414 assertions**, 0.829 seconds, 12 MiB, no failures, skips,
warnings or deprecations. Wrapper elapsed 1.189 seconds, exit 0, stoppedFor=null,
protectedDataUnchanged=true. Evidence:
`/tmp/famtastic-phase2-review.NVAfPl/managed-artifact-preparation-git-free-bounded.m2cENk`.
Disk was 275,680 KiB available immediately after the run. Exact command:

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-artifact-preparation-git-free-bounded "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && /usr/bin/env PATH=/nonexistent/famtastic-parity-no-executables FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackArtifactsTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ProofCallbackServiceParityTest.php"
```

Retained portability receipts, both protected inventories unchanged:

- `managed-artifact-preparation-git-free.ygXHy6`: exit 1, 106 tests / 398 assertions,
  one failure and warning. Missing Git correctly made proc_open return false,
  but the first probe assumed a resource. Handle both missing-executable forms
  in that probe; keep actual callback process creation strict. No skip.
- `managed-artifact-preparation-git-free-repaired.lwLQVC`: exit 0, 106 tests /
  400 assertions, 0.819 seconds, before the subsequent argv-bound assertions.

## Historical focused verification command and receipt

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

The frozen validator fixture is the pre-extraction block. This historical service
differential read `3bc4e9baf` via git show; the current frozen service fixture above
removes that runtime dependency. Both execute old and new public callback plus
duplicate through the same manual legacy fixture, stopping before its selected-build
flow. Compare outputs, mutations, hashes and failures.
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
