# Selected staging repair release — 2026-09-18

Owner instruction: implement the official handoff repair after the Kakes manual
continuation. This is release work, not authorization to replay historical jobs,
send unrelated queued mail, charge a customer, or overwrite a completed site.

## Preserve

- Kakes' independent source and existing public staging deployment remain intact.
- Preserve the September 18 shared email-brand release (`45eedc1a`).
- Selection is not acceptance; only exact current client acceptance enables checkout.
- Source transfer is not a new concept/build request. Preserve verified completed pages.

## Release gates

- [x] Fetch both repositories and reconcile incoming main without discarding work.
- [x] Inventory actual deployed source, runtime, schedule and pending work read-only.
- [x] Prove the new services against an installed disposable Drupal database.
- [x] Connect opt-in Next server boot to the existing staging runtime and test it.
- [ ] Rerun combined cross-repository, portal, email and source-preservation checks.
- [ ] Commit/push exact reviewed source and deploy via canonical agency scripts.
- [ ] Bind private dispatch/callback credentials and a narrowly exposed transport.
- [x] Prove real protected hosting on an isolated synthetic target, with no client mail.
- [ ] Activate a selected-staging-only schedule using explicit CLI PHP.
- [ ] Record actual served receipts and honest remaining unsupported scope.

## Saved source — not a production release

Both repair branches were committed and pushed on September 18:

- Designs `codex/selected-staging-contract`: implementation `accf1ec2468db3f76b1603d8bf31821f0a8d3ada`.
- Studio `codex/selected-staging-continuation`: implementation `2e597d0423c37516094a72632b17bc0e122f58f9`.

Neither main was advanced, no canonical deploy script was applied, and the
running Studio was not restarted. Preserve this distinction in owner updates.
The dated status was also written to the existing local Drive mirror directory;
remote Drive synchronization was not verified. No customer notification, charge
or final acceptance occurred.

## Read-only production findings

Backend marker: `45eedc1aff207d8062ee4cb879cec0208a5ddc4d`.
Frontend marker: `71620bf12a56289a7c99fa753b196aa92441a2ff`.
Selected staging endpoint and callback/dispatch secrets are absent. Jobs 309 and
311 have each exhausted five attempts for missing endpoint/secret; they are not
new work to replay. The request projection still says queued. No claimable jobs
or notification outbox rows were present in the September 18 read-only inventory.

The existing broad lifecycle schedule still uses an implicit PHP runtime and
discards errors. Do not repair it by indiscriminately draining unrelated jobs.
A separate exact-type schedule can use the existing jobs-run type filter.

Next's reviewed consumer is not sufficient alone: server boot does not inject
the runtime into its HTTP module. The artifact HMAC secret also needs explicit
configuration. Installation-owned hosting bindings are required; these are not
inferred from arbitrary submitted host/path values.

An ephemeral SSH reverse-forward probe successfully carried a harmless health
response from the local machine to remote loopback. No Studio API was exposed,
no customer job was submitted, and the probe was stopped. A production transport
must restrict the exposed surface to the signed staging acceptance endpoint;
never tunnel the entire internal Studio UI/API.

The public callback mount is `/web/api/pipeline/site-studio/callback`: a read-only
HEAD probe returned Drupal 405/Allow: POST. The unmounted `/api/...` returned
Apache 404. The Next endpoint validator must explicitly support the deployed
`/web` mount; a local callback mock at the unmounted path did not prove this.

## Real hosting transport proof

`scripts/prove-selected-hosting.mjs --apply` in Next created an isolated synthetic
source, ran local source/browser QA, then used the actual cPanel transport at
`https://famtasticinc.com/selected-handoff-smoke-20260918/`. The target is protected
before artifact upload. Anonymous access and alternate hosts are denied; HTTPS
served bytes match manifest SHA-256
`861e128effa4eae2c2ac5d7eb3faacaa7a097d31d291823ab4730405b81279b3`.
One source build completed; no generation provider ran. The completion callback
was captured locally, NOT accepted by production Drupal, and no mail was sent.

Private receipt (includes synthetic source/QA/rollback evidence, no credentials):
`/var/folders/4z/76l8zpns7hvdlykrk2fkf8wr0000gn/T/selected-worker-YKLOsy/hosted-proof.json`.
Credentials are in a separate owner-only file and must not enter documentation.
The synthetic target and source are retained for verification/recovery; they are
not a customer deliverable or evidence that the production consumer is enabled.

## Runtime and policy limits

The implementation supports bounded static packaging and missing authored pages,
not arbitrary application generation, freeform edits, or automatic target/DNS
allocation. Existing public Kakes/PIT targets must not be replaced by protected
review access policies. A delivered manual source remains manual evidence until
the canonical association contract genuinely accepts it; no fabricated callback.

Rollback: stop only the new selected lane, retain its durable database/source
and hosting receipts, restore code from the canonical release backup if needed.
Do not restore the production database without separate explicit approval.

## September 18 implemented verification

- Next combined: **1,090 tests / 104 files passed**, 73.12 seconds, no skips.
  All agency PHP and actual page-copy browser harnesses enabled.
- Real installed Drupal 11.4.5 / PHP 8.5.9 / SQLite: **54 assertions passed**,
  including a fresh-process readback, ownership, callback signature, exact
  revision acceptance, outbox deduplication and injected late-write rollback.
  Versioned source receipt: `docs/evidence/selected-staging-drupal-2026-09-18.json`.
  This is not MySQL row-lock concurrency or browser login/session proof.
- Corrected integrity handling in OperationalLedger: SQLSTATE 23000 alone is
  not a duplicate key. Other failures now propagate to transaction rollback.
- Authored request updates and selected packet/job refresh now commit together.
- Real review component: blocked/failed state attention, one CSRF API request,
  stale-receipt checkbox reset, containment at 320/390/768/1280 and 44px targets.
- Portal DNA 34 checks, email presentation 72 checks, shared brand asset checks,
  PHP contract 42 assertions / 3 selection cases and Vite build passed.
- Agency PHPUnit: **217 tests / 1,142 assertions passed**, zero errors/failures.
  Existing PHPUnit deprecations remain. Two source-string contract tests were
  updated for the actual delegated serializers/raw-callback parameter, retaining
  the account and private-import boundaries and adding acceptance checks.
- Canonical customer-proof runner: **NOT PASSED**. Catalog and crawlable SEO
  checks passed, then `test-customer-journey.sh` line 175 expected the retired
  prospect-checkout behavior. Actual response: `account_checkout_required`.
  Later lifecycle stages were not reached; no security gate was weakened.
  Evidence: `.artifacts/selected-staging-drupal/20260918T151948Z-93824/canonical.json`.
  Production deployment remains held pending current account-bound journey QA.

These are separate receipts, not a claim that the entire production customer
journey ran. Activation remains closed: automatic target allocation is absent,
and no matching client bindings, production signing credentials, persistent
tunnel or exact-type scheduler have been installed. The owner was asked whether
future automatic previews should remain password-free/noindex like Kakes or use
the access-protected policy the current worker requires. Existing client sites
remain unchanged. Do not silently choose a new access policy to finish a release.

Reproduce the installed proof without touching a shared database:

```bash
export FAMTASTIC_BACKEND_VENDOR=/Users/famtastic-fritz/Development/FAMtastic/worktrees/client-messaging-proof-rescue/backend/vendor
bash scripts/test-selected-staging-drupal.sh
bash scripts/test-selected-staging-drupal.sh --phpunit
```

The runner requires a matching composer.lock and copies an allowlisted runtime,
uses synthetic identities/memory mail, disables outgoing Drupal networking and
removes only its disposable sandbox. `--canonical` additionally needs frontend
dependencies; explicit `FAMTASTIC_CANONICAL_PUBLIC_CMS_READ=1` permits only four
public JSON:API GET collections for the existing sitemap build. This does not
enable production mutations, provider mail, customer checkout or deployment.
