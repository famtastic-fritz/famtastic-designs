# Managed proof package v1

September 21, 2026. Locally verified, unregistered groundwork based on
`ee8f6d3871d122dada20a844a86f48113332221f`, implemented as 9e2bb083 and integrated
as f370b2ef. Independent source review found no confirmed defect within this scope.
Focused 106 tests / 664 assertions and combined 848 tests / 5,228 assertions pass.
Main owns runtime and integration; exact receipts and measured limits are below.
No DB schema, service registration, route, receipt writer, importer, provider,
notification, activation or legacy reader change is included.

## Exact package and public APIs

`ManagedProofArtifactPackage` composes the existing `ManagedProofArtifactStore`
and pinned `SelectedCreatorCreditProjection`; `ManagedProofPackageFiles` is
internal bounded private I/O. All constructor dependencies are trusted server
configuration, never worker input. Defaults are NULL and fail closed.

- `prepare(preparedBundleId, expectedPreparedManifestHash)` calls the real
  `verifyPrepared`, reuses its canonical callback validation and complete
  inventory, and leaves every original byte/DNA/manifest unchanged. It creates
  a new server-random `mp-<32 lowercase hex>` directory, never adopts/reuses one.
  It returns package ID, package manifest hash and bounded manifest facts, NOT
  an import receipt. Repeating preparation allocates a distinct private package.
- `verifyPreparedPackage(packageId, expectedPackageHash, preparedBundleId,
  expectedPreparedHash)` reopens and verifies outside a DB transaction, before
  any committed receipt exists. It returns the same CONTENT-FACTS shape as
  preparation, with no file bytes, authorization or receipt. Both source ID and
  hash matter: an identical source under another bundle ID still rejects.
- `read(receiptId, direction, role, assetId = NULL)` resolves a trusted receipt
  grant first, then runs the same private full verification. It returns only
  `{bytes, media_type, sha256, size_bytes}` for the granted role. No filesystem
  paths, callback/DNA, manifest, original-HTML role, URL or authority is returned.

The generated manifest binds package ID, exact source bundle ID/hash, each
direction's exact projection, asset-ID mapping and complete role/MIME/hash/size
inventory. `status=private_package_only`, `deliverable=false` remain explicit.
The verifier never trusts or traverses paths from the stored manifest. It
regenerates expected bytes and paths from the independently verified source and
canonical server logo, requires exact manifest equality, then checks the finite
directory inventory and every file. Extra, missing, altered, writable, linked
or oversized files reject. Source originals must still exist and verify.

## Creator credit without a generic cap exception

Each A/B/C direction contains its own `index.html`, unchanged ordinary assets
under `assets/`, optional unchanged thumbnail, and the exact PNG at
`assets/brand/famtastic-designs-logo-v1.png`. Keeping the logo in each direction
makes the unchanged pinned relative credit row internally consistent. No public
route is installed to serve those relative references.

The original HTML remains in its separate prepared bundle; it is not overwritten
or recaptured as the customer's accepted original. Derivatives use the existing
projection byte-for-byte, including policy hash
`bb45adcade4cb87faac70ca84b14f8b1cb347395fbc8f3e14f50215d3704e914`.
An exact existing row is identity; ambiguous, duplicate, hidden, nested or legacy
credit is rejected, not repaired. The working legacy reader's `v1` marker and
canonical projection's `1` marker remain distinct and unchanged.

Original HTML remains capped at 500,000 bytes. Credited HTML is explicitly capped
at 500,000 + 694 exact row bytes + 1 newline = **500,695 bytes**. Ordinary assets
retain 2,000,000 bytes each, four assets / 3,000,000 bytes per direction; thumbnails
retain the existing 1,500,000-byte cap and legacy normalization behavior. No
extension, MIME or worker-asset exemption was added.

Only the trusted constructor-supplied, link-free canonical logo can occupy the
distinct `system_logo` role: exactly **2,020,725 bytes**, SHA256
`ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950`.
The input may be an existing 0600/0644 server file; no resizing or substitution.
Every private copy is 0400 under 0700 owned directories. Worker paths colliding
with that role reject, including case-folded aliases and file/directory conflicts.
No extra large PNG is checked into source or test fixtures.

## Receipt resolver is an uninstalled authority dependency

The injected trusted closure receives `(receiptId, direction, role, assetId)`.
It must independently authenticate/authorize the current reader, current tenant,
account/rights and exact committed authoritative import receipt. A worker array,
known hash, callback DNA, caller boolean or packaging result is NOT that authority.
No database resolver or tenant authorization implementation ships here.

On authorization it returns exactly these keys, with string identities/digests:

```text
receipt_id                 exact requested server receipt ID
package_id                 mp-<32 lowercase hex>
package_manifest_sha256    64 lowercase hex
prepared_bundle_id         32 lowercase hex
prepared_manifest_sha256   64 lowercase hex
allowed_roles              {a?: [...], b?: [...], c?: [...]}
```

Role lists are unique subsets of `html`, `asset`, `thumbnail`, `system_logo`.
An `asset` grant permits the named direction's ordinary manifest assets only;
`assetId` uses the existing lowercase-letter-first ID grammar, never a path.
It cannot select the system logo. The resolver may further restrict individual
asset requests because it receives the requested ID. Empty, missing, malformed,
foreign-receipt or insufficient grants reject before content verification.
Exceptions propagate; there is no fallback to generic or legacy reads.

## Filesystem and integration limits

Private and document roots must be existing canonical absolute disjoint
directories; private directories are owned and 0700. The installation must exclude these
private roots from *all* web aliases/docroots and other worker write access.
Files use exclusive create, exact write checks, file fsync and 0400 sealing. The
manifest is written last, only as a completeness marker. There is no directory
fsync or crash/power-loss durability guarantee. A failure can leave private
partial files; no automatic deletion, adoption or overwrite occurs. The filesystem
owner can alter permissions/bytes; this is not hostile-same-UID isolation. Reads
detect observed alterations through bounded regular-file checks and regenerated
bytes, not a claim of filesystem transactions or tamper-proof storage.

At most 21 content files plus one <=65,536-byte manifest exist. Package total is
bounded to 24 MiB; prepared source retains its separate 40 MiB bound. Reads inspect
the whole finite inventory, not just the requested file. A maximum content
inventory was measured locally below; installed Drupal headroom remains unverified.
Internal I/O methods are not independently
authorized APIs and must never be routed to requests.

A future importer must verify content outside its short transaction, then fence
live claim, generation, attempt/deadline, immutable producer identity, exact
request/account/brief/rights and prepared/package bindings before atomically
committing variants/request/job/receipt. This package grants none of that authority.
The managed generic-import deny guard remains closed. Independent review/QA,
notification, paid-operation uncertainty and cloud/provider activation are separate.
Static/script-free callback restrictions remain: credit alone does not make a
functional fresh creative proof, Commerce build or six-direction demo deliverable.
HTTP path resolution, response security headers, revocation semantics and tenant
authorization require their own reviewed adapter; none are implied by these bytes.

## Focused tests and executed verification

Main's receipts under `/tmp/famtastic-phase2-review.NVAfPl/`:

- `managed-proof-package-focused.v3cLky`: exact 9e2bb083, three focused files,
  **106 tests / 664 assertions**, 3.950s suite / 4.329s guarded, 10 MiB. Both new
  service syntax checks and whitespace pass; zero failed/skipped tests.
- `managed-package-maximum.dTRts5`: initial standalone sizing case, 1 test /
  12 assertions, 2.194s suite / 2.433s guarded. It fills all three 500,000-byte
  original HTML pages, four 750,000-byte assets and a 1,500,000-byte thumbnail per
  direction, plus the exact logo copies: 21 content files / 21,064,260 bytes.
- `managed-package-reviewed-php.CZdoxS`: full integrated module after the four
  sizing assets were made byte-distinct to exercise ID lookup. **848 tests /
  5,228 assertions**, 8.493s suite / 9.042s guarded, zero failed/skipped, same
  68 existing PHPUnit deprecations. Protected inventories unchanged throughout.
  Earlier `.OMiAMF` passes the same counts and is retained, not summed.

The reviewed maximum case measured source+package preparation at 0.796s,
explicit reverification at 0.268s, and the interval including four role reads at
2.123s. Setup/input construction and cleanup are outside that interval. Its PHP
allocator process-wide peak was 183,353,344 bytes (~174.86 MiB), not OS RSS or
incremental method memory. PHPUnit's final 60 MiB summary must not replace this
higher captured peak. The earlier standalone sample peaked at 122,273,792 bytes.
Both commands used an explicit 256 MiB CLI limit; no installed server setting
changed. Verify actual Drupal-kernel headroom before wiring this reader; do not
infer 128 MiB compatibility or another host's performance from this sample.
Signature-prefixed synthetic images exercise transport/storage sizing, not image
decoding, browser rendering or creative QA. The logo is the actual approved PNG.

`ManagedProofArtifactPackageTest.php` uses real private temp files and actual
normalizer/store/projection; only receipt authorization is a synthetic closure.
It covers projection parity and identity, originals unchanged, same/different
source identity, explicit pre-receipt verification, role grants, forged IDs,
paths/digests/logo, absent roles, exact HTML cap, unchanged worker-asset cap,
0600/0644 logo inputs, sealed reads, extra/missing/symlink/hardlink/tampered bytes,
oversized manifest, collision/no-overwrite, short writes and public-root rejection.
A FIFO case requires the specific nonregular-file error within one second under
a two-second alarm, restoring the prior signal handler/async setting/alarm. If
Unix FIFO/alarm support is absent it is explicitly skipped, not claimed proven.
These run with synthetic receipt grants, NOT installed authorization or delivery.

Full checkouts use `frontend/public/brand/famtastic-designs-logo-v1.png` directly.
Sparse test checkouts may provide `FAMTASTIC_TEST_CANONICAL_LOGO` pointing to an
existing canonical checkout file; the fixture resolves that trusted path and
requires exact size/hash without a skip. Production has no environment override.

Reproduction command through the protected-data/network-denial wrapper and
200 MiB watch guard (the helper authored source only; main executed it):

```sh
node /tmp/famtastic-phase2-review.NVAfPl/run-integration-check.mjs managed-proof-package-focused "cd /tmp/famtastic-fresh-admission.k98Gm4/designs && FAMTASTIC_TEST_CANONICAL_LOGO=/tmp/famtastic-phase2-review.NVAfPl/designs/frontend/public/brand/famtastic-designs-logo-v1.png FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor /opt/homebrew/bin/php /Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor/phpunit/phpunit/phpunit --bootstrap scripts/automation-test-bootstrap.php --no-configuration --do-not-cache-result backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactPackageTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/SelectedCreatorCreditProjectionTest.php backend/web/modules/custom/famtastic_pipeline/tests/src/Unit/ManagedProofArtifactStoreTest.php"
```

The helper authored source only. Main reviewed, ran guarded local tests/lint and
integrated documentation. No installed DB, Docker, dependency install, provider,
send, route/worker activation or deployment occurred. Legacy source is unchanged.
